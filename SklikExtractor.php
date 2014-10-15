<?php

namespace Keboola\SklikExtractorBundle;

use Keboola\Csv\CsvFile;
use Keboola\ExtractorBundle\Common\Logger;
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
			'columns' => array('accountId', 'campaignId', 'date', 'target', 'impressions', 'clicks', 'ctr', 'cpc',
				'price', 'avgPosition', 'conversions', 'conversionRatio', 'conversionAvgPrice', 'conversionValue',
				'conversionAvgValue', 'conversionValueRatio', 'transactions', 'transactionAvgPrice', 'transactionAvgValue',
				'transactionAvgCount')
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

		$jobId = $params['config'] . '|' . $this->getSyrupJob()->getId();
		if (!$this->storageApi->getRunId()) {
			$this->storageApi->setRunId($this->getSyrupJob()->getId());
		}

		$this->eventLogger->setStorageApi($this->storageApi);
		$this->eventLogger->setRunId($this->getSyrupJob()->getRunId());
		$this->eventLogger->setConfig($jobId);

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

			$this->prepareFiles();

			$api = new Sklik\Api($config['attributes']['username'], $config['attributes']['password'],
				$params['config'], $this->getSyrupJob()->getId(), $this->storageApi->getRunId());
			$limit = $api->getListLimit();

			$accounts = $api->request('client.get');
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
				$campaigns = $api->request('campaigns.list', array('user' => array('userId' => $account['userId'])));

				if (isset($campaigns['campaigns']) && count($campaigns['campaigns'])) {
					$campaignIds = array();
					foreach ($campaigns['campaigns'] as $campaign) {
						$campaign['accountId'] = $account['userId'];
						$this->saveToFile('campaigns', $campaign);
						$campaignIds[] = $campaign['id'];
					}

					$blocksCount = ceil(count($campaignIds) / $limit);
					for ($i = 0; $i < $blocksCount; $i++) {
						$campaignIdsBlock = array_slice($campaignIds, $limit * $i, $limit);

						$this->getStats($api, true, $account['userId'], $campaignIdsBlock, $startDate, $endDate);
						$this->getStats($api, false, $account['userId'], $campaignIdsBlock, $startDate, $endDate);
					}

					continue;
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

	private function getStats(Sklik\Api $api, $context=false, $userId, $campaignIdsBlock, $startDate, $endDate)
	{
		$stats = $api->request('campaigns.stats', array(
			'user' => array(
				'userId' => $userId
			),
			'campaignIds' => $campaignIdsBlock,
			'params' => array(
				'dateFrom' => $startDate,
				'dateTo' => $endDate,
				'granularity' => 'daily',
				'includeFulltext' => $context? false : true,
				'includeContext' => $context? true : false
			)
		));
		if (isset($stats['report'])) foreach ($stats['report'] as $campaignReport) {
			foreach ($campaignReport['stats'] as $stats) {
				$stats['accountId'] = $userId;
				$stats['campaignId'] = $campaignReport['campaignId'];
				$stats['target'] = $context? 'context' : 'fulltext';
				$this->saveToFile('stats', $stats);
			}
		} else {
			Logger::log(\Monolog\Logger::ALERT, 'Bad stats format', array(
				'stats' => $stats
			));
		}
	}
}
