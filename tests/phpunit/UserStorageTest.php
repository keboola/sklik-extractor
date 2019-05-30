<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor\Tests;

use Keboola\SklikExtractor\UserStorage;
use PHPUnit\Framework\TestCase;

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
}
