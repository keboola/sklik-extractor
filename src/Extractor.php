<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Keboola\Component\UserException;
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

    protected static function formatDate(string $input): string
    {
        $inputFixed = empty($input) ? '-1 day' : $input;
        $inputTime = strtotime($inputFixed);
        if ($inputTime === false) {
            throw new UserException("Date '$input' in restrictionFilter is not valid.");
        }
        return date('Y-m-d', $inputTime);
    }

    public function run(Config $config, ?int $globalLimit = null): void
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
                // Filter by user ID
                if (isset($report['allowedUserIDs'])) {
                    $allowedUserIds = array_map(function ($v) {
                        return (string) $v;
                    }, (array) $report['allowedUserIDs']);
                    if ($allowedUserIds && !in_array((string) $account['userId'], $allowedUserIds, true)) {
                        $this->logger->info(sprintf('Skipping user ID "#%s".', $account['userId']));
                        continue;
                    }
                }

                // Log only long-running batches, once per minute
                $lastLogAt = new \DateTimeImmutable();

                // Format date
                $report['restrictionFilter']['dateFrom']
                    = Extractor::formatDate($report['restrictionFilter']['dateFrom']);
                $report['restrictionFilter']['dateTo']
                    = Extractor::formatDate($report['restrictionFilter']['dateTo']);
                $primary = ($report['resource'] === 'queries') ? 'query' : 'id';

                if (!in_array('id', $report['displayColumns'])) {
                    $report['displayColumns'][] = $primary;
                }

                // Create report
                $this->logger->info(sprintf(
                    'Creating report for resource "%s" for user ID "#%s".',
                    $report['resource'],
                    $account['userId']
                ));
                $result = $this->api->createReport(
                    $report['resource'],
                    $report['restrictionFilter'],
                    $report['displayOptions'],
                    $account['userId']
                );
                $this->logger->info(sprintf(
                    'Created report for resource "%s" for user ID "#%s".',
                    $report['resource'],
                    $account['userId']
                ));

                // Get limit (batch size)
                $limit = $globalLimit;
                if (isset($report['limit'])) {
                    $limit = (int) $report['limit'];
                }
                if (!$limit) {
                    $limit = SklikApi::getReportLimit(
                        $report['restrictionFilter']['dateFrom'],
                        $report['restrictionFilter']['dateTo'],
                        $listLimit,
                        $report['displayOptions']['statGranularity'] ?? null
                    );
                }
                $this->logger->info(sprintf('Batch size set to "%d".', $limit));

                // Get start offset, skip N first records
                $offset = 0;
                if (isset($report['skip'])) {
                    $offset = (int) $report['skip'];
                }
                $start = $offset;

                // Get last record, if totalLimit is configured
                $lastRecord = null;
                if (isset($report['totalLimit'])) {
                    $lastRecord = $offset + (int) $report['totalLimit'];
                }

                // Log which records will be read
                if ($offset || $lastRecord) {
                    $this->logger->info(sprintf(
                        'Reading records <%d;%d> from "%s" report for user ID "#%s".',
                        $offset,
                        $lastRecord,
                        $report['resource'],
                        $account['userId']
                    ));
                } else {
                    $this->logger->info(sprintf(
                        'Reading all records from "%s" report for user ID "#%s".',
                        $report['resource'],
                        $account['userId']
                    ));
                }

                // Load all batches
                $batch = 0;
                while (true) {
                    $batch++;

                    // Short last batch size
                    if ($lastRecord && $offset + $limit > $lastRecord) {
                        $limit = $lastRecord - $offset;
                    }

                    // Log max one message per minute
                    $now = new \DateTimeImmutable();
                    if ($now->sub(new \DateInterval('PT1M')) > $lastLogAt) {
                        $lastLogAt = $now;
                        $this->logger->info(sprintf('Reading %d. batch <%d;%d>.', $batch, $offset, $offset + $limit));
                    }

                    // Read report
                    try {
                        $data = $this->api->readReport(
                            $report['resource'],
                            $result['reportId'],
                            $report['displayColumns'],
                            $offset,
                            $limit
                        );
                        $this->userStorage->saveReport($report['name'], $data, $account['userId'], $primary);
                    } catch (\Throwable $e) {
                        // Log in which batch is the problem.
                        $this->logger->error(sprintf(
                            'Error when reading %d. batch <%d;%d> from "%s" report for user ID "#%s" (%s).',
                            $batch,
                            $offset,
                            $offset + $limit,
                            $report['resource'],
                            $account['username'],
                            $account['userId']
                        ));
                        throw $e;
                    }

                    // Increment offset for next batch
                    $offset += $limit;

                    // Stop if there is no data
                    if (count($data) === 0) {
                        break;
                    }

                    // Stop on last record
                    if ($lastRecord && $offset >= $lastRecord) {
                        break;
                    }
                }

                $this->logger->info(sprintf(
                    'Records <%d;%d> have been read from "%s" report for user ID "#%s".',
                    $start,
                    $offset,
                    $report['resource'],
                    $account['userId']
                ));
            }
        }
    }
}
