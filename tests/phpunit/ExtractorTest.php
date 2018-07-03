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
    /**
     * @var Temp
     */
    protected $temp;
    /** @var Extractor */
    protected $extractor;

    public function setUp() : void
    {
        parent::setUp();

        $this->temp = new Temp('sklik-test');
        $this->temp->initRunFolder();
        $logger = new Logger();
        $api = new SklikApi(getenv('SKLIK_API_TOKEN'), $logger, getenv('SKLIK_API_URL'));
        $userStorage = new UserStorage($this->temp->getTmpFolder());

        $this->extractor = new Extractor($api, $userStorage, $logger);
    }

    public function testExtractorRun() : void
    {
        $config = new Config([
            'parameters' => [
                '#token' => getenv('SKLIK_API_TOKEN'),
                'reports' => [
                    [
                        'name' => 'report1',
                        'resource' => 'campaigns',
                        'restrictionFilter' => json_encode([
                            'dateFrom' => getenv('SKLIK_DATE_FROM'),
                            'dateTo' => getenv('SKLIK_DATE_TO'),
                        ]),
                        'displayOptions' => json_encode(['statGranularity' => 'daily']),
                        'displayColumns' => 'name, clicks, impressions, budget.name',
                    ],
                ],
            ],
        ], new ConfigDefinition());

        $this->extractor->run($config);

        $this->assertFileExists($this->temp->getTmpFolder() . '/accounts.csv');
        $this->assertFileExists($this->temp->getTmpFolder() . '/report1.csv');
        $metaFile = file($this->temp->getTmpFolder() . '/report1.csv');
        $this->assertEquals('"id","budget_name","name"', trim($metaFile[0]));
        $this->assertFileExists($this->temp->getTmpFolder() . '/report1-stats.csv');
        $statsFile = file($this->temp->getTmpFolder() . '/report1-stats.csv');
        $this->assertEquals('"id","clicks","date","impressions"', trim($statsFile[0]));
    }

    /**
     * No account should be downloaded
     */
    public function testExtractorRunChooseAccounts() : void
    {
        $config = new Config([
            'parameters' => [
                '#token' => getenv('SKLIK_API_TOKEN'),
                'accounts' => '123,456',
                'reports' => [
                    [
                        'name' => 'report1',
                        'resource' => 'campaigns',
                        'restrictionFilter' => json_encode([
                            'dateFrom' => getenv('SKLIK_DATE_FROM'),
                            'dateTo' => getenv('SKLIK_DATE_TO'),
                        ]),
                        'displayOptions' => json_encode(['statGranularity' => 'daily']),
                        'displayColumns' => 'name, clicks, impressions',
                    ],
                ],
            ],
        ], new ConfigDefinition());

        $this->extractor->run($config);

        $this->assertFileNotExists($this->temp->getTmpFolder() . '/accounts.csv');
        $this->assertFileNotExists($this->temp->getTmpFolder() . '/report1.csv');
        $this->assertFileNotExists($this->temp->getTmpFolder() . '/report1-stats.csv');
    }
}
