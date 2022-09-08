<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor\Tests\Functional;

use Keboola\DatadirTests\AbstractDatadirTestCase;
use Keboola\DatadirTests\DatadirTestSpecification;

class DatadirTest extends AbstractDatadirTestCase
{

    public function testRun(): void
    {
        if (getenv('SKLIK_API_TOKEN') === false) {
            throw new \Exception('Sklik API token not set in env.');
        }
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
            null, // anything
            ''
        );
        $tempDatadir = $this->getTempDatadir($specification);
        file_put_contents($tempDatadir->getTmpFolder() . '/config.json', \GuzzleHttp\json_encode($config));
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
