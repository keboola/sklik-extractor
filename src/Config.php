<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Keboola\Component\Config\BaseConfig;

class Config extends BaseConfig
{
    public function getToken() : string
    {
        return $this->getValue(['parameters', '#token']);
    }

    public function getAccounts() : array
    {
        $accounts = $this->getValue(['parameters', 'accounts'], '');
        if (!strlen($accounts)) {
            return [];
        }
        return array_map('trim', explode(',', $accounts));
    }

    public function getReports() : array
    {
        $reports = $this->getValue(['parameters', 'reports'], '');
        foreach ($reports as &$report) {
            $report['displayColumns'] = strlen($report['displayColumns']) > 0
                ? array_map('trim', explode(',', $report['displayColumns'])) : [];
        }
        return $reports;
    }
}
