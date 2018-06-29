<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor\Tests;

use Keboola\Component\Logger;
use Keboola\SklikExtractor\Config;
use Keboola\SklikExtractor\ConfigDefinition;
use Keboola\SklikExtractor\Extractor;
use Keboola\SklikExtractor\SklikApi;
use Keboola\SklikExtractor\UserStorage;
use Keboola\Temp\Temp;
use PHPUnit\Framework\TestCase;

class ExtractorTest extends TestCase
{
    public function testExtractorRun() : void
    {
        $temp = new Temp('sklik-test');
        $temp->initRunFolder();
        $logger = new Logger();
        $api = new SklikApi(getenv('SKLIK_API_TOKEN'), $logger, getenv('SKLIK_API_URL'));
        $userStorage = new UserStorage($temp->getTmpFolder());

        $config = new Config([
            'parameters' => [
                '#token' => getenv('SKLIK_API_TOKEN'),
                'reports' => [
                    [
                        'name' => 'report1',
                        'resource' => 'campaigns',
                        'restrictionFilter' => [
                            'dateFrom' => getenv('SKLIK_DATE_FROM'),
                            'dateTo' => getenv('SKLIK_DATE_TO'),
                        ],
                        'displayOptions' => ['statGranularity' => 'daily'],
                        'displayColumns' => ['id', 'name', 'clicks', 'impressions'],
                    ],
                ],
            ],
        ], new ConfigDefinition());

        $component = new Extractor($api, $userStorage, $logger);
        $component->run($config);

        $this->assertFileExists($temp->getTmpFolder() . '/accounts.csv');
        $this->assertFileExists($temp->getTmpFolder() . '/report1.csv');
        $metaFile = file($temp->getTmpFolder() . '/report1.csv');
        $this->assertEquals('"id","name"', trim($metaFile[0]));
        $this->assertFileExists($temp->getTmpFolder() . '/report1-stats.csv');
        $statsFile = file($temp->getTmpFolder() . '/report1-stats.csv');
        $this->assertEquals('"id","clicks","date","impressions"', trim($statsFile[0]));
    }
}
