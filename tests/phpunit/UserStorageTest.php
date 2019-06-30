<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor\Tests;

use Keboola\SklikExtractor\UserStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class UserStorageTest extends TestCase
{
    public function testSaving(): void
    {
        $row1 = uniqid();
        $row2 = uniqid();
        $storage = new UserStorage(sys_get_temp_dir());
        $storage->addUserTable('table', ['first', 'second'], ['second']);
        $storage->save('table', ['first' => 'row1', 'second' => $row1]);
        $storage->save('table', ['first' => 'row2', 'second' => $row2]);

        $this->assertFileExists(sys_get_temp_dir().'/table.csv');
        $fp = fopen(sys_get_temp_dir().'/table.csv', 'r');
        if ($fp === false) {
            throw new \Exception(sys_get_temp_dir().'/table.csv not found');
        }
        $row = 0;
        while (($data = fgetcsv($fp, 1000, ',')) !== false) {
            $row++;
            $this->assertCount(2, $data);
            switch ($row) {
                case 1:
                    $this->assertEquals(['first', 'second'], $data);
                    break;
                case 2:
                    $this->assertEquals('row1', $data[0]);
                    $this->assertEquals($row1, $data[1]);
                    break;
                case 3:
                    $this->assertEquals('row2', $data[0]);
                    $this->assertEquals($row2, $data[1]);
                    break;
            }
        }
        $this->assertEquals(3, $row);
        fclose($fp);

        $this->assertFileExists(sys_get_temp_dir().'/table.csv.manifest');
        $manifestFile = file_get_contents(sys_get_temp_dir().'/table.csv.manifest');
        if ($manifestFile === false) {
            throw new \Exception(sys_get_temp_dir().'/table.csv.manifest not found');
        }
        $manifest = json_decode($manifestFile, true);
        $this->assertArrayHasKey('destination', $manifest);
        $this->assertEquals('table', $manifest['destination']);
        $this->assertArrayHasKey('incremental', $manifest);
        $this->assertEquals(true, $manifest['incremental']);
        $this->assertArrayHasKey('primary_key', $manifest);
        $this->assertEquals(['second'], $manifest['primary_key']);
    }

    public function testSaveReportNoNested(): void
    {
        $path = sys_get_temp_dir() . '/save-report-no-nested';
        (new Filesystem())->mkdir($path);

        $storage = new UserStorage($path);
        $storage->saveReport('campaigns', [
            [
                'id' => '1',
                'regions.id' => '1',
                'regions.parentId' => null,
                'regions.name' => 'Jihomoravsky',
            ],
        ], 112233);

        $this->assertFileExists($path . '/campaigns.csv');
        $this->assertEquals(<<<CSV
"id","accountId","regions.id","regions.name","regions.parentId"
"1","112233","1","Jihomoravsky",""\n
CSV
, file_get_contents($path . '/campaigns.csv'));
    }

    public function testSaveReportNestedLevel1(): void
    {
        $path = sys_get_temp_dir() . '/save-report-nested-level-1';
        (new Filesystem())->mkdir($path);

        $storage = new UserStorage($path);
        $storage->saveReport('campaigns', [
            [
                'id' => '1',
                'level1' => [
                    'regions.id' => '1',
                    'regions.parentId' => null,
                    'regions.name' => 'Jihomoravsky',
                ],

            ],
        ], 112233);

        $this->assertFileExists($path . '/campaigns.csv');
        $this->assertEquals(<<<CSV
"id","accountId","level1_regions.id","level1_regions.name","level1_regions.parentId"
"1","112233","1","Jihomoravsky",""\n
CSV
            , file_get_contents($path . '/campaigns.csv'));
    }

    public function testSaveReportNestedLevel2(): void
    {
        $path = sys_get_temp_dir() . '/save-report-nested-level-2';
        (new Filesystem())->mkdir($path);

        $storage = new UserStorage($path);
        $storage->saveReport('campaigns', [
            [
                'id' => '1',
                'level1' => [
                    'level2' => [
                        'regions.id' => '1',
                        'regions.parentId' => null,
                        'regions.name' => 'Jihomoravsky',
                    ],
                ],

            ],
        ], 112233);

        $this->assertFileExists($path . '/campaigns.csv');
        $this->assertEquals(<<<CSV
"id","accountId","level1_level2_regions.id","level1_level2_regions.name","level1_level2_regions.parentId"
"1","112233","1","Jihomoravsky",""\n
CSV
            , file_get_contents($path . '/campaigns.csv'));
    }

    public function testSaveReportWithCampaignRegions(): void
    {
        $path = sys_get_temp_dir() . '/save-report-campaign-with-regions';
        (new Filesystem())->mkdir($path);

        $storage = new UserStorage($path);
        $storage->saveReport('campaigns', [
            [
                'id' => '1',
                'name' => 'Campaign with regions',
                'regions' => [
                    [
                        'id' => '1',
                        'parentId' => null,
                        'name' => 'Cela CR',
                    ],
                    [
                        'id' => '2',
                        'parentId' => '1',
                        'name' => 'Jihomoravsky',
                    ],
                    [
                        'id' => '3',
                        'parentId' => '1',
                        'name' => 'Pardubicky',
                    ],
                ],
            ],
        ], 112233);

        // campaigns

        $this->assertFileExists($path . '/campaigns.csv');
        $this->assertEquals(<<<CSV
"id","accountId","name"
"1","112233","Campaign with regions"\n
CSV
            , file_get_contents($path . '/campaigns.csv'));

        $this->assertFileExists($path . '/campaigns.csv.manifest');
        $this->assertEquals(<<<JSON
{"destination":"campaigns","incremental":true,"primary_key":["id"]}
JSON
            , file_get_contents($path . '/campaigns.csv.manifest'));

        // campaigns regions

        $this->assertFileExists($path . '/campaigns-regions.csv');
        $this->assertEquals(<<<CSV
"campaignId","id","name","parentId"
"1","1","Cela CR",""
"1","2","Jihomoravsky","1"
"1","3","Pardubicky","1"\n
CSV
            , file_get_contents($path . '/campaigns-regions.csv'));

        $this->assertFileExists($path . '/campaigns-regions.csv.manifest');
        $this->assertEquals(<<<JSON
{"destination":"campaigns-regions","incremental":true,"primary_key":["campaignId","id"]}
JSON
            , file_get_contents($path . '/campaigns-regions.csv.manifest'));
    }
}
