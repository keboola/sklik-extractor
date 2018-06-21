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
        return $this->getValue(['parameters', 'accounts'], []);
    }

    public function getAllowEmptyStatistics() : bool
    {
        return $this->getValue(['parameters', 'allowEmptyStatistics'], false);
    }

    public function getReports() : array
    {
        return $this->getValue(['parameters', 'reports'], []);
    }
}
