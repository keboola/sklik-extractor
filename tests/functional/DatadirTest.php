<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor\Tests\Functional;

use Exception;
use Keboola\DatadirTests\AbstractDatadirTestCase;
use Keboola\DatadirTests\DatadirTestSpecification;
use function GuzzleHttp\json_encode;

class DatadirTest extends AbstractDatadirTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('SKLIK_API_TOKEN') === false) {
            throw new Exception('Sklik API token not set in env.');
        }
    }

    public function testRun(): void
    {
        putenv('KBC_COMPONENT_RUN_MODE=run');

        $config = [
            'action' => 'run',
            'parameters' => [
                '#token' => getenv('SKLIK_API_TOKEN'),
                'reports' => [
                    [
                        'name' => 'queries',
                        'resource' => 'queries',
                        'restrictionFilter' => '{"dateFrom":"-9 days","dateTo":"now"}',
                        'displayOptions' => '{"statGranularity":"daily"}',
                        'displayColumns' => 'query,campaign.name,impressions,clicks',
                    ],
                ],
            ],
        ];

        $specification = new DatadirTestSpecification(
            __DIR__ . '/run/source/data',
            0,
            '',
            '',
        );
        $tempDatadir = $this->getTempDatadir($specification);
        file_put_contents($tempDatadir->getTmpFolder() . '/config.json', json_encode($config));
        $process = $this->runScript($tempDatadir->getTmpFolder());
        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());
        $this->assertFileExists($tempDatadir->getTmpFolder() . '/out/tables/accounts.csv');
        $this->assertFileExists($tempDatadir->getTmpFolder() . '/out/tables/queries.csv');
        $this->assertFileExists($tempDatadir->getTmpFolder() . '/out/tables/queries-stats.csv');
        $this->assertFileExists($tempDatadir->getTmpFolder() . '/out/tables/accounts.csv.manifest');
        $this->assertFileExists($tempDatadir->getTmpFolder() . '/out/tables/queries.csv.manifest');
        $this->assertFileExists($tempDatadir->getTmpFolder() . '/out/tables/queries-stats.csv.manifest');
    }

    public function testDebugRun(): void
    {
        putenv('KBC_COMPONENT_RUN_MODE=debug');

        $config = [
            'action' => 'run',
            'parameters' => [
                '#token' => getenv('SKLIK_API_TOKEN'),
                'reports' => [
                    [
                        'name' => 'queries',
                        'resource' => 'queries',
                        'restrictionFilter' => '{"dateFrom":"16.6.2024","dateTo":"25.6.2024"}',
                        'displayOptions' => '{"statGranularity":"daily"}',
                        'displayColumns' => 'query,campaign.name,impressions,clicks',
                    ],
                ],
            ],
        ];

        $expectedStdout = file_get_contents(__DIR__ . '/run-debug/expected/expected-stdout');
        if ($expectedStdout === false) {
            throw new Exception('Cannot read expected stdout.');
        }

        $specification = new DatadirTestSpecification(
            __DIR__ . '/run-debug/source/data',
            0,
            $expectedStdout,
            '',
        );
        $tempDatadir = $this->getTempDatadir($specification);
        file_put_contents($tempDatadir->getTmpFolder() . '/config.json', json_encode($config));
        $process = $this->runScript($tempDatadir->getTmpFolder());
        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());
        $this->assertFileExists($tempDatadir->getTmpFolder() . '/out/tables/accounts.csv');
        $this->assertFileExists($tempDatadir->getTmpFolder() . '/out/tables/queries.csv');
        $this->assertFileExists($tempDatadir->getTmpFolder() . '/out/tables/queries-stats.csv');
        $this->assertFileExists($tempDatadir->getTmpFolder() . '/out/tables/accounts.csv.manifest');
        $this->assertFileExists($tempDatadir->getTmpFolder() . '/out/tables/queries.csv.manifest');
        $this->assertFileExists($tempDatadir->getTmpFolder() . '/out/tables/queries-stats.csv.manifest');
    }
}
