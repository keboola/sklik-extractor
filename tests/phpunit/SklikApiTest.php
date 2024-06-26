<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor\Tests;

use ColinODell\PsrTestLogger\TestLogger;
use Exception;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Keboola\Component\UserException;
use Keboola\SklikExtractor\Exception as SklikException;
use Keboola\SklikExtractor\SklikApi;
use PHPUnit\Framework\TestCase;

class SklikApiTest extends TestCase
{
    protected SklikApi $api;

    private TestLogger $logger;

    public function setUp(): void
    {
        parent::setUp();

        if (getenv('SKLIK_API_URL') === false) {
            throw new Exception('Sklik API url not set in env.');
        }
        if (getenv('SKLIK_API_TOKEN') === false) {
            throw new Exception('Sklik API token not set in env.');
        }

        $this->logger = new TestLogger();

        $this->api = new SklikApi($this->logger, getenv('SKLIK_API_URL'));
        $this->api->loginByToken(getenv('SKLIK_API_TOKEN'));
    }

    public function testApiLogin(): void
    {
        $result = $this->api->login();
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('session', $result);
        $this->assertNotEmpty($result['session']);
    }

    public function testApiGetListLimit(): void
    {
        $result = $this->api->getListLimit();
        $this->assertGreaterThan(0, $result);
    }

    public function testApiGetAccounts(): void
    {
        $result = $this->api->getAccounts();
        $this->assertGreaterThanOrEqual(1, count($result));
        $this->assertArrayHasKey('userId', $result[0]);
        $this->assertArrayHasKey('username', $result[0]);
    }

    public function testApiCreateReadReport(): void
    {
        $result = $this->api->createReport(
            'campaigns',
            [
                'dateFrom' => getenv('SKLIK_DATE_FROM'),
                'dateTo' => getenv('SKLIK_DATE_TO'),
            ],
            ['statGranularity' => 'daily'],
        );
        $this->assertArrayHasKey('reportId', $result);
        $this->assertNotEmpty($result['reportId']);

        $result = $this->api->readReport(
            'campaigns',
            $result['reportId'],
            true,
            ['id', 'name', 'clicks', 'impressions'],
        );
        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayHasKey('stats', $result[0]);
        $this->assertGreaterThanOrEqual(1, $result[0]['stats']);
        $this->assertArrayHasKey('clicks', $result[0]['stats'][0]);
        $this->assertArrayHasKey('impressions', $result[0]['stats'][0]);
    }

    public function testApiCreateReadReportWithoutEmptyStatistics(): void
    {
        $result = $this->api->createReport(
            'campaigns',
            [
                'dateFrom' => getenv('SKLIK_DATE_FROM'),
                'dateTo' => getenv('SKLIK_DATE_TO'),
            ],
            ['statGranularity' => 'daily'],
        );
        $this->assertArrayHasKey('reportId', $result);
        $this->assertNotEmpty($result['reportId']);

        $result = $this->api->readReport(
            'campaigns',
            $result['reportId'],
            false,
            ['id', 'name', 'clicks', 'impressions'],
        );
        $this->assertEmpty($result);
    }

    public function testLoginFailed(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Authentication failed');
        $this->api->loginByToken('unexistsToken');
    }

    public function testRetry(): void
    {
        try {
            $this->api->createReport(
                'unknownResource',
                [
                    'dateFrom' => getenv('SKLIK_DATE_FROM'),
                    'dateTo' => getenv('SKLIK_DATE_TO'),
                ],
                ['statGranularity' => 'daily'],
            );
            $this->fail('create report must throw exception.');
        } catch (SklikException $exception) {
            $this->assertStringContainsString(
                '"error":"Not Found","method":"unknownResource.createReport"',
                $exception->getMessage(),
            );
        }

        $this->assertTrue(
            $this->logger->hasInfo(
                'Client error: `POST https://api.sklik.cz/jsonApi/drak/unknownResource.createReport`'
                . ' resulted in a `404 Not Found` response. Retrying... [1x]',
            ),
            implode(array_map(function ($v) {
                return $v['message'];
            }, $this->logger->records)),
        );
        $this->assertTrue(
            $this->logger->hasInfo(
                'Client error: `POST https://api.sklik.cz/jsonApi/drak/unknownResource.createReport`' .
                ' resulted in a `404 Not Found` response. Retrying... [4x]',
            ),
            implode(array_map(function ($v) {
                return $v['message'];
            }, $this->logger->records)),
        );
    }

    public function testRetryOnInternalErrorWithSuccessHTTPCode(): void
    {
        if (getenv('SKLIK_API_TOKEN') === false) {
            throw new Exception('Sklik API token not set in env.');
        }

        $this->api = new SklikApi(
            $this->logger,
            getenv('SKLIK_API_URL') ?: null,
            HandlerStack::create(new MockHandler($this->getResponses(6))),
        );
        $this->api->loginByToken(getenv('SKLIK_API_TOKEN'));

        try {
            $this->api->createReport(
                'campaigns',
                [
                    'dateFrom' => getenv('SKLIK_DATE_FROM'),
                    'dateTo' => getenv('SKLIK_DATE_TO'),
                ],
                ['statGranularity' => 'daily'],
            );
            $this->fail('create report must throw exception.');
        } catch (SklikException $exception) {
            $this->assertStringContainsString(
                '{"status":"error","message":"Server error","code":500}',
                $exception->getMessage(),
            );
        }

        $this->assertTrue(
            $this->logger->hasError(
                'API Error, will be retried. Retry count: 1x',
            ),
            implode(array_map(function ($v) {
                return $v['message'];
            }, $this->logger->records)),
        );

        $this->assertTrue(
            $this->logger->hasError(
                'API Error, will be retried. Retry count: 5x',
            ),
            implode(array_map(function ($v) {
                return $v['message'];
            }, $this->logger->records)),
        );
    }

    /** @return Response[] */
    private function getResponses(int $count): array
    {
        $responses = [];

        for ($x = 0; $x < $count; $x++) {
            // @phpcs:ignore
            $responses[] = new Response(200, [], '{"status":200,"statusMessage":"OK","session":"1YGLTg9vEdngwPjochR59L-K3TCqjzsur_90WYb2IdPJBaFT8sAcyO0LqRg7dYZWFeUgoQBr1nPBo"}'); //login response
            // @phpcs:ignore
            $responses[] = new Response(200, [], '{"status":"error","message":"Server error","code":500}'); //request response
        }

        return $responses;
    }
}
