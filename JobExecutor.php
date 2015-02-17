<?php

namespace Keboola\SklikExtractor;

use Keboola\SklikExtractor\Extractor\ConfigurationStorage;
use Keboola\SklikExtractor\Extractor\UserStorage;
use Keboola\SklikExtractor\Sklik\ApiException;
use Keboola\SklikExtractor\Service\EventLogger;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Event;
use Keboola\Syrup\Exception\UserException;
use Keboola\Syrup\Job\Metadata\Job;
use Keboola\Temp\Temp;
use Monolog\Logger;

class JobExecutor extends \Keboola\Syrup\Job\Executor
{
    /**
     * @var Client
     */
    protected $storageApi;
    /**
     * @var UserStorage
     */
    protected $userStorage;
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var EventLogger
     */
    protected $eventLogger;

    protected $appName;

    public function __construct($appName, Temp $temp, Logger $logger)
    {
        $this->appName = $appName;
        $this->temp = $temp;
        $this->logger = $logger;
    }

    public function execute(Job $job)
    {
        $configurationStorage = new ConfigurationStorage($this->appName, $this->storageApi);
        $this->eventLogger = new EventLogger($this->appName, $this->storageApi, $job->getId());
        $this->userStorage = new UserStorage($this->appName, $this->storageApi, $this->temp);

        $params = $job->getParams();
        $configIds = isset($params['config'])? array($params['config']) : $configurationStorage->getConfigurationsList();
        $since = isset($params['since']) ? $params['since'] : '-1 day';
        $until = isset($params['until']) ? $params['until'] : '-1 day';

        $startDate = \DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d 00:00:01', strtotime($since)));
        $endDate = \DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d 23:59:59', strtotime($until)));

        ini_set('memory_limit', '2048M');
        foreach ($configIds as $configId) {
            $configuration = $configurationStorage->getConfiguration($configId);
            $this->extract($configuration['attributes'], $startDate, $endDate);
        }

        $this->userStorage->uploadData();
    }


    public function extract($attributes, $startDate, $endDate)
    {
        $timerAll = time();
        try {
            $api = new Sklik\Api($attributes['username'], $attributes['password'], $this->logger);
            $limit = $api->getListLimit();

            foreach ($api->getAccounts() as $account) {
                $timer = time();
                $this->userStorage->save('accounts', $account);
                try {
                    $campaignIds = array();
                    foreach ($api->getCampaigns($account['userId']) as $campaign) {
                        $campaign['accountId'] = $account['userId'];
                        $this->userStorage->save('campaigns', $campaign);
                        $campaignIds[] = $campaign['id'];
                    }

                    $blocksCount = ceil(count($campaignIds) / $limit);
                    for ($i = 0; $i < $blocksCount; $i++) {
                        $campaignIdsBlock = array_slice($campaignIds, $limit * $i, $limit);

                        $this->getStats($api, $account['userId'], $campaignIdsBlock, $startDate, $endDate, true);
                        $this->getStats($api, $account['userId'], $campaignIdsBlock, $startDate, $endDate, false);
                    }

                    $this->eventLogger->log('Data for client ' . $account['username'] . ' downloaded', [], time() - $timer);
                } catch (ApiException $e) {
                    $this->eventLogger->log(
                        'Error when downloading data for client ' . $account['username'] . ': ' . $e->getMessage(),
                        [],
                        time() - $timer,
                        Event::TYPE_WARN
                    );
                }
            }

            $this->userStorage->uploadData();
            $this->eventLogger->log('Extraction complete', [], time() - $timerAll, Event::TYPE_SUCCESS);
        } catch (\Exception $e) {
            $message = 'Extraction failed' . (($e instanceof UserException)? ': ' . $e->getMessage() : null);
            $this->eventLogger->log($message, [], time() - $timerAll, Event::TYPE_ERROR);
            throw $e;
        }
    }

    private function getStats(Sklik\Api $api, $userId, $campaignIdsBlock, $startDate, $endDate, $context = false)
    {
        $stats = $api->getStats($userId, $campaignIdsBlock, $startDate, $endDate, $context);
        foreach ($stats as $campaignReport) {
            foreach ($campaignReport['stats'] as $stats) {
                $stats['accountId'] = $userId;
                $stats['campaignId'] = $campaignReport['campaignId'];
                $stats['target'] = $context ? 'context' : 'fulltext';
                $this->userStorage->save('stats', $stats);
            }
        }
    }
}
