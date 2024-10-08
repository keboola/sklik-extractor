<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor\Tests;

use Exception;
use Keboola\Component\Logger;
use Keboola\SklikExtractor\Config;
use Keboola\SklikExtractor\ConfigDefinition;
use Keboola\SklikExtractor\Extractor;
use Keboola\SklikExtractor\SklikApi;
use Keboola\SklikExtractor\UserStorage;
use Keboola\Temp\Temp;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;

class ExtractorTest extends TestCase
{
    private Temp $temp;
    private Extractor $extractor;
    private TestHandler $testHandler;

    public function setUp(): void
    {
        parent::setUp();

        if (getenv('SKLIK_API_URL') === false) {
            throw new Exception('Sklik API url not set in env.');
        }
        if (getenv('SKLIK_API_TOKEN') === false) {
            throw new Exception('Sklik API token not set in env.');
        }

        $this->temp = new Temp('sklik-test');
        $logger = new Logger();
        $this->testHandler = new TestHandler();
        $logger->pushHandler($this->testHandler);
        $api = new SklikApi($logger, getenv('SKLIK_API_URL'));
        $api->loginByToken(getenv('SKLIK_API_TOKEN'));
        $userStorage = new UserStorage($this->temp->getTmpFolder());

        $this->extractor = new Extractor($api, $userStorage, $logger);
    }

    public function testExtractorRun(): void
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
                    [
                        'name' => 'queries',
                        'resource' => 'queries',
                        'restrictionFilter' => json_encode([
                            'dateFrom' => '-9 days',
                            'dateTo' => 'now',
                        ]),
                        'displayOptions' => json_encode(['statGranularity' => 'daily']),
                        'displayColumns' => 'query,group.name,keyword.id',
                    ],
                ],
            ],
        ], new ConfigDefinition());

        $this->extractor->run($config);

        $this->assertFileExists($this->temp->getTmpFolder() . '/accounts.csv');
        $this->assertFileExists($this->temp->getTmpFolder() . '/report1.csv');
        $this->assertFileExists($this->temp->getTmpFolder() . '/queries.csv');
        $metaFile = file($this->temp->getTmpFolder() . '/report1.csv');
        $this->assertIsArray($metaFile);
        $this->assertEquals('"id","accountId","budget_name","name"', trim($metaFile[0]));
        $this->assertFileExists($this->temp->getTmpFolder() . '/report1-stats.csv');
        $statsFile = file($this->temp->getTmpFolder() . '/report1-stats.csv');
        $this->assertIsArray($statsFile);
        $this->assertEquals('"id","clicks","date","impressions"', trim($statsFile[0]));
        $metaFile = file($this->temp->getTmpFolder() . '/queries.csv');
        $this->assertIsArray($metaFile);
        $this->assertEquals('"query","accountId","group_name","keyword_id"', trim($metaFile[0]));

        $this->testHandler->hasInfo('Downloading report with id');
    }

    public function testIgnoreExtraKeys(): void
    {
        $config = new Config([
            'parameters' => [
                'username' => 'testUsername',
                '#password' => 'testPassword',
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
                        'displayColumns' => 'name, impressions, clicks, totalMoney',
                    ],
                ],
            ],
        ], new ConfigDefinition());

        $this->assertArrayNotHasKey('#password', $config->getParameters());
        $this->assertArrayNotHasKey('username', $config->getParameters());
    }


    public function testDevicesStats(): void
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
                            'deviceType' => [
                                'devicePhone',
                                'deviceDesktop',
                            ],
                        ]),
                        'displayOptions' => json_encode(['statGranularity' => 'daily']),
                        'displayColumns' => 'name, impressions, clicks, totalMoney',
                    ],
                ],
            ],
        ], new ConfigDefinition());

        $this->extractor->run($config);

        $this->assertFileExists($this->temp->getTmpFolder() . '/accounts.csv');
        $this->assertFileExists($this->temp->getTmpFolder() . '/report1.csv');
        $metaFile = file($this->temp->getTmpFolder() . '/report1.csv');
        $this->assertIsArray($metaFile);
        $this->assertEquals('"id","accountId","name"', trim($metaFile[0]));
        $this->assertFileExists($this->temp->getTmpFolder() . '/report1-stats.csv');
        $statsFile = file($this->temp->getTmpFolder() . '/report1-stats.csv');
        $this->assertIsArray($statsFile);
        // @phpcs:disable
        $this->assertEquals('"id","date","deviceDesktop_clicks","deviceDesktop_impressions","deviceDesktop_totalMoney","devicePhone_clicks","devicePhone_impressions","devicePhone_totalMoney"', trim($statsFile[0]));
        // @phpcs:enable
    }

    /**
     * No account should be downloaded
     */
    public function testExtractorRunChooseAccounts(): void
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

        $this->assertFileDoesNotExist($this->temp->getTmpFolder() . '/accounts.csv');
        $this->assertFileDoesNotExist($this->temp->getTmpFolder() . '/report1.csv');
        $this->assertFileDoesNotExist($this->temp->getTmpFolder() . '/report1-stats.csv');
    }

    public function testConfigIncompleteRestrictionFilter(): void
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
                            'dateTo' => getenv('SKLIK_DATE_TO'),
                        ]),
                        'displayOptions' => json_encode(['statGranularity' => 'daily']),
                        'displayColumns' => 'name, clicks, impressions',
                    ],
                ],
            ],
        ], new ConfigDefinition());
        $reports =  $config->getReports();
        $this->assertCount(1, $reports);
        $this->assertArrayHasKey('restrictionFilter', $reports[0]);
        $this->assertArrayHasKey('dateFrom', $reports[0]['restrictionFilter']);
        $this->assertEquals('-1 day', $reports[0]['restrictionFilter']['dateFrom']);
    }

    public function testExtractorRunNoDisplayOptions(): void
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
                        ]),
                        'displayOptions' => json_encode([]),
                        'displayColumns' => 'name, clicks, impressions, budget.name',
                    ],
                ],
            ],
        ], new ConfigDefinition());

        $this->extractor->run($config);

        $this->assertFileExists($this->temp->getTmpFolder() . '/accounts.csv');
        $this->assertFileExists($this->temp->getTmpFolder() . '/report1.csv');
        $metaFile = file($this->temp->getTmpFolder() . '/report1.csv');
        $this->assertIsArray($metaFile);
        $this->assertEquals('"id","accountId","budget_name","name"', trim($metaFile[0]));
        $this->assertFileExists($this->temp->getTmpFolder() . '/report1-stats.csv');
        $statsFile = file($this->temp->getTmpFolder() . '/report1-stats.csv');
        $this->assertIsArray($statsFile);
        $this->assertEquals('"id","clicks","date","impressions"', trim($statsFile[0]));
    }

    public function testConfigAllowEmptyStatisticsDisabled(): void
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
                        ]),
                        'displayOptions' => json_encode([]),
                        'displayColumns' => 'name, clicks, impressions, budget.name',
                        'allowEmptyStatistics' => false,
                    ],
                ],
            ],
        ], new ConfigDefinition());

        $this->extractor->run($config);

        $this->assertFileExists($this->temp->getTmpFolder() . '/accounts.csv');
        $this->assertFileExists($this->temp->getTmpFolder() . '/report1.csv');
        $metaFile = file($this->temp->getTmpFolder() . '/report1.csv');
        $this->assertIsArray($metaFile);
        $this->assertNotEquals('"1320846","196379","","Kampaň č. 1"', trim($metaFile[1]));
        $this->assertFileExists($this->temp->getTmpFolder() . '/report1-stats.csv');
        $statsFile = file($this->temp->getTmpFolder() . '/report1-stats.csv');
        $this->assertIsArray($statsFile);
        $this->assertNotEquals('"1320846","0","","0"', trim($statsFile[1]));
    }
}
