<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Psr\Log\LoggerInterface;

class Extractor
{
    /**
     * @var SklikApi
     */
    protected $api;
    /**
     * @var UserStorage
     */
    protected $userStorage;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(SklikApi $api, UserStorage $userStorage, LoggerInterface $logger)
    {
        $this->api = $api;
        $this->userStorage = $userStorage;
        $this->logger = $logger;
    }

    public function run(Config $config, $limit = null): void
    {
        $accounts = $config->getAccounts() ?: $this->api->getAccounts();
        $listLimit = $this->api->getListLimit();

        foreach ($accounts as $account) {
            if (!isset($account['userId'])) {
                throw new Exception('Account response is missing userId: ' . json_encode($account));
            }

            $this->userStorage->save('accounts', $account);

            foreach ($config->getReports() as $report) {
                if (!isset($report['restrictionFilter']['dateFrom'])) {
                    $report['restrictionFilter']['dateFrom'] = date('Y-m-d', strtotime('-1 day'));
                }
                if (!isset($report['restrictionFilter']['dateTo'])) {
                    $report['restrictionFilter']['dateTo'] = date('Y-m-d');
                }
                if (!in_array('id', $report['displayColumns'])) {
                    $report['displayColumns'][] = 'id';
                }

                $result = $this->api->createReport(
                    $report['resource'],
                    $report['restrictionFilter'],
                    $report['displayOptions']
                );

                $offset = 0;
                if (!$limit) {
                    $limit = SklikApi::getReportLimit(
                        $report['restrictionFilter']['dateFrom'],
                        $report['restrictionFilter']['dateTo'],
                        $listLimit,
                        $report['displayOptions']['statGranularity'] ?? null
                    );
                }
                do {
                    $data = $this->api->readReport(
                        $report['resource'],
                        $result['reportId'],
                        $report['displayColumns'],
                        $offset,
                        $limit
                    );

                    $this->userStorage->saveReport($report['name'], $data);
                    $offset += $limit;
                } while (count($data) > 0);
            }
        }
    }
}
