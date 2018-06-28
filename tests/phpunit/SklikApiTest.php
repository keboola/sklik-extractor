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
            [
                'dateFrom' => SKLIK_DATE_FROM,
                'dateTo' => SKLIK_DATE_TO,
            ],
            ['statGranularity' => 'daily']
        );
        $this->assertArrayHasKey('reportId', $result);
        $this->assertNotEmpty($result['reportId']);

        $result = $this->api->readReport(
            'campaigns',
            $result['reportId'],
            ['id', 'name', 'clicks', 'impressions'],
            true
        );
        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayHasKey('stats', $result[0]);
        $this->assertGreaterThanOrEqual(1, $result[0]['stats']);
        $this->assertArrayHasKey('clicks', $result[0]['stats'][0]);
        $this->assertArrayHasKey('impressions', $result[0]['stats'][0]);
    }
}
