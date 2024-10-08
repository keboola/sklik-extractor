<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Keboola\Component\UserException;
use Psr\Log\LoggerInterface;

class Extractor
{
    protected SklikApi $api;
    protected UserStorage $userStorage;
    protected LoggerInterface $logger;

    public function __construct(SklikApi $api, UserStorage $userStorage, LoggerInterface $logger)
    {
        $this->api = $api;
        $this->userStorage = $userStorage;
        $this->logger = $logger;
    }

    protected static function formatDate(string $input): string
    {
        $inputFixed = empty($input) ? '-1 day' : $input;
        $inputTime = strtotime($inputFixed);
        if ($inputTime === false) {
            throw new UserException("Date '$input' in restrictionFilter is not valid.");
        }
        return date('Y-m-d', $inputTime);
    }

    public function run(Config $config, ?int $limit = null): void
    {
        $accountsToGet = $config->getAccounts();
        $listLimit = $this->api->getListLimit();

        foreach ($this->api->getAccounts() as $account) {
            if (!isset($account['userId'])) {
                throw new Exception('Account response is missing userId: ' . json_encode($account));
            }

            $userId = $account['userId'];
            if (count($accountsToGet) > 0 && !in_array($userId, $accountsToGet)) {
                continue;
            }

            $this->userStorage->save('accounts', $account);

            foreach ($config->getReports() as $report) {
                $report['restrictionFilter']['dateFrom']
                    = Extractor::formatDate($report['restrictionFilter']['dateFrom']);
                $report['restrictionFilter']['dateTo']
                    = Extractor::formatDate($report['restrictionFilter']['dateTo']);
                $primary = ($report['resource'] === 'queries') ? 'query' : 'id';

                if (!in_array('id', $report['displayColumns'])) {
                    $report['displayColumns'][] = $primary;
                }

                $result = $this->api->createReport(
                    $report['resource'],
                    $userId,
                    $report['restrictionFilter'],
                    $report['displayOptions'],
                );

                $offset = 0;
                if (!$limit) {
                    $limit = SklikApi::getReportLimit(
                        $report['restrictionFilter']['dateFrom'],
                        $report['restrictionFilter']['dateTo'],
                        $listLimit,
                        $report['displayOptions']['statGranularity'] ?? null,
                    );
                }

                $this->logger->info(sprintf('Downloading report with id "%s"', $result['reportId']));

                do {
                    try {
                        $data = $this->api->readReport(
                            $report['resource'],
                            $result['reportId'],
                            $report['allowEmptyStatistics'],
                            $report['displayColumns'],
                            $offset,
                            $limit,
                        );
                    } catch (ApiCallLimitException $e) {
                        $waitingTime = $e->getWaitingTimeInSeconds();
                        $this->logger->debug(sprintf('API call limit reached, waiting for %s seconds.', $waitingTime));
                        sleep($waitingTime);
                        continue;
                    }

                    $offset += $limit;
                    $this->userStorage->saveReport($report['name'], $data, $userId, $primary);
                } while ($offset < $result['totalCount']);
            }
        }
    }
}
