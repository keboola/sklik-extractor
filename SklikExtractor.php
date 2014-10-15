<?php

namespace Keboola\SklikExtractorBundle;

use Keboola\Csv\CsvFile;
use Keboola\ExtractorBundle\Extractor\Extractors\JsonExtractor as Extractor;
use Keboola\SklikExtractorBundle\Sklik\EventLogger;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Event;
use Syrup\ComponentBundle\Exception\UserException;

class SklikExtractor extends Extractor
{
	/**
	 * @var Client
	 */
	protected $storageApi;
	/**
	 * @var Sklik\EventLogger
	 */
	protected $eventLogger;
	protected $name = "sklik";
	protected $files;
	protected $tables = array(
		'accounts' => array(
			'columns' => array('userId', 'username', 'access', 'relationName', 'relationStatus', 'relationType',
				'walletCredit', 'walletCreditWithVat', 'walletVerified', 'accountLimit', 'dayBudgetSum')
		),
		'campaigns' => array(
			'columns' => array('accountId', 'id', 'name', 'deleted', 'status', 'dayBudget', 'exhaustedDayBudget',
				'adSelection', 'createDate', 'totalBudget', 'exhaustedTotalBudget', 'totalClicks', 'exhaustedTotalClicks')
		),
		'stats' => array(
			'columns' => array('accountId', 'campaignId', 'date', 'target', 'conversions', 'transactions', 'money', 'value',
				'avgPosition', 'impressions', 'clicks')
		)
	);

	public function __construct(EventLogger $eventLogger)
	{
		$this->eventLogger = $eventLogger;
	}

	protected function prepareFiles()
	{
		$this->incrementalUpload = false;
		foreach ($this->tables as $k => $v) {
			$f = new CsvFile($this->temp->createTmpFile());
			$f->writeRow($v['columns']);
			$this->files[$k] = $f;
		}
	}

	protected function saveToFile($table, $data)
	{
		if (!isset($this->tables[$table])) {
			throw new \Exception('Table ' . $table . ' not configured for the Extractor');
		}
		/** @var CsvFile $f */
		$f = $this->files[$table];

		$dataToSave = array();
		foreach ($this->tables[$table]['columns'] as $c) {
			$dataToSave[$c] = isset($data[$c])? $data[$c] : null;
		}

		$f->writeRow($dataToSave);
	}

	protected function uploadFiles()
	{
		$this->sapiUpload($this->files);
	}

	public function run($config)
	{
		$params = $this->getSyrupJob()->getParams();
		$this->eventLogger->setStorageApi($this->storageApi);
		$this->eventLogger->setRunId($this->getSyrupJob()->getRunId());
		if (isset($params['config'])) {
			$this->eventLogger->setConfig($params['config']);
		}

		$timerAll = time();
		try {
			ini_set('memory_limit', '2048M');
			$params = $this->getSyrupJob()->getParams();
			$since = isset($params['since']) ? $params['since'] : '-1 day';
			$until = isset($params['until']) ? $params['until'] : '-1 day';

			if (!isset($config['attributes']['username'])) {
				throw new UserException('Sklik username is not configured in configuration table');
			}
			if (!isset($config['attributes']['password'])) {
				throw new UserException('Sklik password is not configured in configuration table');
			}

			$startDate = \DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d 00:00:01', strtotime($since)));
			$endDate = \DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d 23:59:59', strtotime($until)));
			$interval = \DateInterval::createFromDateString('1 day');
			$downloadPeriod = new \DatePeriod($startDate, $interval, $endDate);

			$this->prepareFiles();

			$sk = new Sklik\Api($config['attributes']['username'], $config['attributes']['password'], $this->eventLogger);

			$limit = 100;
			$limits = $sk->request('api.limits');
			foreach($limits['limits']['batchCallLimits'] as $l) {
				if ($l['name'] == 'global.list') {
					$limit = $l['limit'];
					break;
				}
			}

			$accounts = $sk->request('client.get');
			// Add user itself to check for reports
			array_unshift($accounts['foreignAccounts'], array(
				'userId' => $accounts['user']['userId'],
				'username' => $accounts['user']['username'],
				'access' => null,
				'relationName' => null,
				'relationStatus' => null,
				'relationType' => null,
				'walletCredit' => $accounts['user']['walletCredit'],
				'walletCreditWithVat' => $accounts['user']['walletCreditWithVat'],
				'walletVerified' => $accounts['user']['walletVerified'],
				'accountLimit' => $accounts['user']['accountLimit'],
				'dayBudgetSum' => $accounts['user']['dayBudgetSum']
			));

			foreach ($accounts['foreignAccounts'] as $account) {
				$timer = time();
				$this->saveToFile('accounts', $account);
				$campaigns = $sk->request('campaigns.list', array('user' => array('userId' => $account['userId'])));

				if (isset($campaigns['campaigns']) && count($campaigns['campaigns'])) {
					$campaignIds = array();
					foreach ($campaigns['campaigns'] as $campaign) {
						$campaign['accountId'] = $account['userId'];
						$this->saveToFile('campaigns', $campaign);
						$campaignIds[] = $campaign['id'];

						foreach ($downloadPeriod as $date) {
							/** @var \DateInterval $date */
							$d = new \DateTime($date->format('c'));


							// Fulltext stats
							$stats = $sk->request('campaigns.stats', array(
								'user' => array(
									'userId' => $account['userId']
								),
								'campaignIds' => array($campaign['id']),
								'params' => array(
									'dateFrom' => $d,
									'dateTo' => $d,
									'granularity' => 'daily',
									'includeFulltext' => true,
									'includeContext' => false
								)
							));
							foreach ($stats['report'] as $campaignReport) {
								foreach ($campaignReport['stats'] as $stats) {
									$stats['accountId'] = $account['userId'];
									$stats['campaignId'] = $campaign['id'];
									$stats['target'] = 'fulltext';
									$this->saveToFile('stats', $stats);
								}
							}

							// Context stats
							$stats = $sk->request('campaigns.stats', array(
								'user' => array(
									'userId' => $account['userId']
								),
								'campaignIds' => array($campaign['id']),
								'params' => array(
									'dateFrom' => $d,
									'dateTo' => $d,
									'granularity' => 'daily',
									'includeFulltext' => false,
									'includeContext' => true
								)
							));
							foreach ($stats['report'] as $campaignReport) {
								foreach ($campaignReport['stats'] as $stats) {
									$stats['accountId'] = $account['userId'];
									$stats['campaignId'] = $campaign['id'];
									$stats['target'] = 'context';
									$this->saveToFile('stats', $stats);
								}
							}
						}
					}
				}
				$this->eventLogger->log('Data for client ' . $account['username'] . ' downloaded', time() - $timer);
			}

			$this->uploadFiles();
			$this->eventLogger->log('Extraction complete', time() - $timerAll, Event::TYPE_SUCCESS);
		} catch (\Exception $e) {
			$message = 'Extraction failed' . (($e instanceof UserException)? ': ' . $e->getMessage() : null);
			$this->eventLogger->log($message, time() - $timerAll, Event::TYPE_ERROR);
			throw $e;
		}
	}
}
