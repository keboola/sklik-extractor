<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Keboola\Component\Config\BaseConfig;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

class Config extends BaseConfig
{
    public function getToken(): string
    {
        return $this->getValue(['parameters', '#token']);
    }

    public function getAccounts(): array
    {
        $accounts = $this->getValue(['parameters', 'accounts'], '');
        if (!strlen($accounts)) {
            return [];
        }
        return array_map('trim', explode(',', $accounts));
    }

    public function getLimit(): ?int
    {
        $limit = $this->getValue(['parameters', 'accounts'], '');
        if (empty($limit)) {
            return null;
        }
        return $limit;
    }

    public function getReports(): array
    {
        $decoder = new JsonDecode([JsonDecode::ASSOCIATIVE => true ]);
        $reports = $this->getValue(['parameters', 'reports'], '');
        foreach ($reports as &$report) {
            try {
                $report['restrictionFilter'] = strlen($report['restrictionFilter']) > 0
                    ? $decoder->decode($report['restrictionFilter'], JsonEncoder::FORMAT) : [];
            } catch (NotEncodableValueException $e) {
                throw new Exception("Restriction filter for report {$report['name']} is not valid json");
            }
            try {
                $report['displayOptions'] = strlen($report['displayOptions']) > 0
                    ? $decoder->decode($report['displayOptions'], JsonEncoder::FORMAT) : [];
            } catch (NotEncodableValueException $e) {
                throw new Exception("Display options for report {$report['name']} is not valid json");
            }
            $report['displayColumns'] = strlen($report['displayColumns']) > 0
                ? array_map('trim', explode(',', $report['displayColumns'])) : [];

            if (!isset($report['restrictionFilter']['dateFrom'])) {
                $report['restrictionFilter']['dateFrom'] = '-1 day';
            }
            if (!isset($report['restrictionFilter']['dateTo'])) {
                $report['restrictionFilter']['dateTo'] = '-1 day';
            }
        }
        return $reports;
    }
}
