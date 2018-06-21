<?php

namespace Keboola\SklikExtractor\Tests;

use Keboola\Component\Logger;
use Keboola\SklikExtractor\SklikApi;
use PHPUnit\Framework\TestCase;

class SklikApiTest extends TestCase
{
    /** @var  SklikApi */
    protected $api;

    public function setUp()
    {
        parent::setUp();

        $this->api = new SklikApi(SKLIK_API_TOKEN, new Logger(), SKLIK_API_URL);
    }

    public function testApiLogin()
    {
        $result = $this->api->login();
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('session', $result);
        $this->assertNotEmpty($result['session']);
    }

    public function testApiGetListLimit()
    {
        $result = $this->api->getListLimit();
        $this->assertGreaterThan(0, $result);
    }

    public function testApiGetAccounts()
    {
        $result = $this->api->getAccounts();
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('userId', $result[0]);
        $this->assertArrayHasKey('username', $result[0]);
    }

    public function testApiCreateReadReport()
    {
        $result = $this->api->createReport(
            'campaigns',
            ['dateFrom' => '2018-01-01', 'dateTo' => '2018-06-01'],
            ['statGranularity' => 'daily']
        );print_r($result);
        $this->assertArrayHasKey('reportId', $result);
        $this->assertNotEmpty($result['reportId']);

        $result = $this->api->readReport('campaigns', $result['reportId'], ['id','name'], true);
        print_r($result);
    }
}
