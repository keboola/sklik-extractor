<?php
/**
 * @package sklik-extractor
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\SklikExtractor\Extractor;

class UserStorage extends \Keboola\SklikExtractor\Service\UserStorage
{
    protected $tables = [
        'accounts' => [
            'columns' => ['userId', 'username', 'access', 'relationName', 'relationStatus', 'relationType',
                'walletCredit', 'walletCreditWithVat', 'walletVerified', 'accountLimit', 'dayBudgetSum']
        ],
        'campaigns' => [
            'columns' => ['accountId', 'id', 'name', 'deleted', 'status', 'dayBudget', 'exhaustedDayBudget',
                'adSelection', 'createDate', 'totalBudget', 'exhaustedTotalBudget', 'totalClicks',
                'exhaustedTotalClicks']
        ],
        'stats' => [
            'columns' => ['accountId', 'campaignId', 'date', 'target', 'impressions', 'clicks', 'ctr', 'cpc', 'price',
                'avgPosition', 'conversions', 'conversionRatio', 'conversionAvgPrice', 'conversionValue',
                'conversionAvgValue', 'conversionValueRatio', 'transactions', 'transactionAvgPrice',
                'transactionAvgValue', 'transactionAvgCount']
        ]
    ];
}
