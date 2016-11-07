<?php
/**
* @package ex-sklik
* @copyright 2015 Keboola
* @author Jakub Matejka <jakub@keboola.com>
*/
namespace Keboola\SklikExtractor;

class Extractor
{
    protected static $userTables = [
        'accounts' => [
            'primary' => ['userId'],
            'columns' => ['userId', 'username', 'access', 'relationName', 'relationStatus', 'relationType',
                'walletCredit', 'walletCreditWithVat', 'walletVerified', 'accountLimit', 'dayBudgetSum']
        ],
        'campaigns' => [
            'primary' => ['id'],
            'columns' => ['id', 'name', 'deleted', 'status', 'dayBudget', 'exhaustedDayBudget',
                'adSelection', 'createDate', 'totalBudget', 'exhaustedTotalBudget', 'totalClicks',
                'exhaustedTotalClicks', 'accountId']
        ],
        'stats' => [
            'primary' => ['accountId', 'campaignId', 'date', 'target'],
            'columns' => ['accountId', 'campaignId', 'date', 'target', 'impressions', 'clicks', 'ctr', 'cpc',
                'price', 'avgPosition', 'conversions', 'conversionRatio', 'conversionAvgPrice', 'conversionValue',
                'conversionAvgValue', 'conversionValueRatio', 'transactions', 'transactionAvgPrice',
                'transactionAvgValue', 'transactionAvgCount', 'impressionShare']
        ]
    ];

    /** @var UserStorage */
    protected $userStorage;

    /** @var  Api */
    protected $api;
    protected $apiLimit;

    public function __construct($username, $password, $folder, $bucket, $apiUrl = null)
    {
        $this->api = new Api($username, $password, $apiUrl);
        $this->apiLimit = $this->api->getListLimit();
        $this->userStorage = new UserStorage(self::$userTables, $folder, $bucket);
    }

    public function run(\DateTime $startDate, \DateTime $endDate, $impressionShare)
    {
        try {
            foreach ($this->api->getAccounts() as $account) {
                $this->userStorage->save('accounts', $account);
                try {
                    $campaignIds = [];
                    foreach ($this->api->getCampaigns($account['userId']) as $campaign) {
                        $campaign['accountId'] = $account['userId'];
                        $this->userStorage->save('campaigns', $campaign);
                        $campaignIds[] = $campaign['id'];
                    }

                    $blocksCount = ceil(count($campaignIds) / $this->apiLimit);
                    for ($i = 0; $i < $blocksCount; $i++) {
                        $campaignIdsBlock = array_slice($campaignIds, $this->apiLimit * $i, $this->apiLimit);

                        $this->getStats($account['userId'], $campaignIdsBlock, $startDate, $endDate, true, $impressionShare);
                        $this->getStats($account['userId'], $campaignIdsBlock, $startDate, $endDate, false, $impressionShare);
                    }
                } catch (Exception $e) {
                    error_log("Error when downloading data for client '{$account['username']}': {$e->getMessage()}");
                }
            }
        } catch (\Exception $e) {
            error_log('Extraction failed' . (($e instanceof Exception) ? ': ' . $e->getMessage() : null));
        }
        $this->api->logout();
    }

    private function getStats($userId, $campaignIdsBlock, \DateTime $startDate, \DateTime $endDate, $impressionShare, $context = false)
    {
        $newStartDate = new \DateTime($startDate->format('Y-m-d'));
        $days = 10;

        do {
            $newEndDate = new \DateTime($newStartDate->format('Y-m-d'));
            $newEndDate->modify(sprintf("+%d days", $days));
            $dateInterval = date_diff($endDate, $newStartDate, true);

            if (intval($dateInterval->format('%a')) <= $days) {
                $newEndDate = $endDate;
            }

            $stats = $this->api->getStats($userId, $campaignIdsBlock, $newStartDate, $newEndDate, $context, $impressionShare);

            $target = $context ? 'context' : 'fulltext';
            foreach ($stats as $campaignReport) {
                foreach ($campaignReport['stats'] as $stats) {
                    $stats['accountId'] = $userId;
                    $stats['campaignId'] = $campaignReport['campaignId'];
                    $stats['target'] = $target;
                    $this->userStorage->save('stats', $stats);
                }
            }

            $newStartDate->modify(sprintf("+%d days", $days+1));
        } while (intval($dateInterval->format('%a')) > $days);
    }
}
