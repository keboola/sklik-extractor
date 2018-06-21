<?php
declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Psr\Log\LoggerInterface;

class SklikExtractor
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SklikApi
     */
    private $api;
    /**
     * @var UserStorage
     */
    private $userStorage;

    protected static $userTables = [
        'accounts' => [
            'primary' => ['userId'],
            'columns' => ['userId', 'username', 'access', 'relationName', 'relationStatus', 'relationType',
                'walletCredit', 'walletCreditWithVat', 'walletVerified', 'accountLimit', 'dayBudgetSum'],
        ],
    ];

    public function __construct(Config $config, LoggerInterface $logger, string $tablesDir)
    {
        $this->config = $config;
        $this->logger = $logger;

        $this->api = new SklikApi($config->getToken(), $logger);
        $this->userStorage = new UserStorage(self::$userTables, $tablesDir);
    }

    public function execute(): void
    {
        $accounts = $this->config->getAccounts() ?: $this->api->getAccounts();

        foreach ($accounts as $account) {
            if (!isset($account['userId'])) {
                throw new Exception('Account response is missing userId: ' . json_encode($account));
            }

            $this->userStorage->save('accounts', $account);

            foreach ($this->config->getReports() as $report) {
                $this->userStorage->addUserTable($report['name'], $report['displayColumns'], $report['primary']);
                $result = $this->api->createReport($report['resource'], $report['restrictionFilter'], $report['displayOptions']);
                // @TODO pagination
                $data = $this->api->readReport(
                    $report['resource'],
                    $result['reportId'],
                    $report['displayColumns'],
                    $this->config->getAllowEmptyStatistics()
                );

                foreach ($data['report']['stats'] as $row) {
                    $this->userStorage->save($report['name'], $row);
                }
            }
        }
    }
}
