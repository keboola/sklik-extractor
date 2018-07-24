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

    public function run(Config $config, ?int $limit = null) : void
    {
        $accountsToGet = $config->getAccounts();
        $listLimit = $this->api->getListLimit();

        foreach ($this->api->getAccounts() as $account) {
            if (!isset($account['userId'])) {
                throw new Exception('Account response is missing userId: ' . json_encode($account));
            }
            if (count($accountsToGet) > 0 && !in_array($account['userId'], $accountsToGet)) {
                continue;
            }

            $this->userStorage->save('accounts', $account);

            foreach ($config->getReports() as $report) {
                $dateFrom = $report['restrictionFilter']['dateFrom'] ?? '-1 day';
                $report['restrictionFilter']['dateFrom'] = date('Y-m-d', strtotime($dateFrom));
                $dateTo = $report['restrictionFilter']['dateTo'] ?? 'today';
                $report['restrictionFilter']['dateTo'] = date('Y-m-d', strtotime($dateTo));

                if (!in_array('id', $report['displayColumns']) && $report['resource'] !== 'queries') {
                    $report['displayColumns'][] = 'id';
                }

                $result = $this->api->createReport(
                    $report['resource'],
                    $report['restrictionFilter'],
                    $report['displayOptions'],
                    $account['userId']
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

                    $this->userStorage->saveReport($report['name'], $data, $account['userId']);
                    $offset += $limit;
                } while (count($data) > 0);
            }
        }
    }
}
