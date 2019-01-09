<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor\Tests;

use Keboola\Component\Logger;
use Keboola\SklikExtractor\SklikApi;
use PHPUnit\Framework\TestCase;

class SklikApiTest extends TestCase
{
    /** @var  SklikApi */
    protected $api;

    public function setUp() : void
    {
        parent::setUp();

        $this->api = new SklikApi(new Logger(), getenv('SKLIK_API_URL'));
        $this->api->loginByToken(getenv('SKLIK_API_TOKEN'));
    }

    public function testApiLogin() : void
    {
        $result = $this->api->login();
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('session', $result);
        $this->assertNotEmpty($result['session']);
    }

    public function testApiLoginByPassword() : void
    {
        $result = $this->api->loginByPassword(
            getenv('SKLIK_API_USERNAME'),
            getenv('SKLIK_API_PASSWORD')
        );
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('session', $result);
        $this->assertNotEmpty($result['session']);
    }

    public function testApiGetListLimit() : void
    {
        $result = $this->api->getListLimit();
        $this->assertGreaterThan(0, $result);
    }

    public function testApiGetAccounts() : void
    {
        $result = $this->api->getAccounts();
        $this->assertGreaterThanOrEqual(1, count($result));
        $this->assertArrayHasKey('userId', $result[0]);
        $this->assertArrayHasKey('username', $result[0]);
    }

    public function testApiCreateReadReport() : void
    {
        $result = $this->api->createReport(
            'campaigns',
            [
                'dateFrom' => getenv('SKLIK_DATE_FROM'),
                'dateTo' => getenv('SKLIK_DATE_TO'),
            ],
            ['statGranularity' => 'daily']
        );
        $this->assertArrayHasKey('reportId', $result);
        $this->assertNotEmpty($result['reportId']);

        $result = $this->api->readReport(
            'campaigns',
            $result['reportId'],
            ['id', 'name', 'clicks', 'impressions']
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
