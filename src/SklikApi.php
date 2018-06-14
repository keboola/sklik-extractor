<?php
declare(strict_types=1);

namespace Keboola\SklikExtractor;

class SklikApi
{
    public function __construct(BaseConfig $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }
}
