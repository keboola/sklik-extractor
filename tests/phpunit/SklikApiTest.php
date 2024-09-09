<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor\Tests;

use Exception;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Keboola\Component\UserException;
use Keboola\SklikExtractor\Exception as SklikException;
use Keboola\SklikExtractor\SklikApi;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class SklikApiTest extends TestCase
{
    protected SklikApi $api;

    private TestHandler $testHandler;
    private Logger $logger;

    public function setUp(): void
    {
        parent::setUp();

        if (getenv('SKLIK_API_URL') === false) {
            throw new Exception('Sklik API url not set in env.');
        }
        if (getenv('SKLIK_API_TOKEN') === false) {
            throw new Exception('Sklik API token not set in env.');
        }

        $this->testHandler = new TestHandler();

        $this->logger = new Logger('SklikApiTest');
        $this->logger->pushHandler($this->testHandler);

        $this->api = new SklikApi($this->logger, getenv('SKLIK_API_URL'));
        $this->api->loginByToken(getenv('SKLIK_API_TOKEN'));
    }

    public function testApiLogin(): void
    {
        $result = $this->api->login();
        self::assertArrayHasKey('status', $result);
        self::assertEquals(200, $result['status']);
        self::assertArrayHasKey('session', $result);
        self::assertNotEmpty($result['session']);
    }

    public function testApiGetListLimit(): void
    {
        $result = $this->api->getListLimit();
        self::assertGreaterThan(0, $result);
    }

    public function testApiGetAccounts(): void
    {
        $result = $this->api->getAccounts();
        self::assertGreaterThanOrEqual(1, count($result));
        self::assertArrayHasKey('userId', $result[0]);
        self::assertArrayHasKey('username', $result[0]);
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
        self::assertArrayHasKey('reportId', $result);
        self::assertNotEmpty($result['reportId']);

        $result = $this->api->readReport(
            'campaigns',
            $result['reportId'],
            true,
            ['id', 'name', 'clicks', 'impressions'],
        );
        self::assertGreaterThanOrEqual(1, $result);
        self::assertArrayHasKey('id', $result[0]);
        self::assertArrayHasKey('name', $result[0]);
        self::assertArrayHasKey('stats', $result[0]);
        self::assertGreaterThanOrEqual(1, $result[0]['stats']);
        self::assertArrayHasKey('clicks', $result[0]['stats'][0]);
        self::assertArrayHasKey('impressions', $result[0]['stats'][0]);
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
        self::assertArrayHasKey('reportId', $result);
        self::assertNotEmpty($result['reportId']);

        $result = $this->api->readReport(
            'campaigns',
            $result['reportId'],
            false,
            ['id', 'name', 'clicks', 'impressions'],
        );
        self::assertEmpty($result);
    }

    public function testLoginFailed(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Authentication failed');
        $this->api->loginByToken('unexistsToken');
    }

    public function testRetry(): void
    {
        $this->testHandler->clear();

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
            self::assertStringContainsString(
                '"error":"Not Found","method":"unknownResource.createReport"',
                $exception->getMessage(),
            );
        }

        self::assertTrue($this->testHandler->hasInfo(
            'Client error: `POST https://api.sklik.cz/jsonApi/drak/unknownResource.createReport`'
            . ' resulted in a `404 Not Found` response. Retrying... [1x]',
        ));

        self::assertTrue($this->testHandler->hasInfo(
            'Client error: `POST https://api.sklik.cz/jsonApi/drak/unknownResource.createReport`' .
            ' resulted in a `404 Not Found` response. Retrying... [4x]',
        ));
    }

    public function testRetryOnInternalErrorWithSuccessHTTPCode(): void
    {
        $this->testHandler->clear();

        $this->api = new SklikApi(
            $this->logger,
            getenv('SKLIK_API_URL') ?: null,
            HandlerStack::create(new MockHandler($this->getResponses())),
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
            self::assertStringContainsString(
                '{"status":"error","message":"Server error","code":500}',
                $exception->getMessage(),
            );
        }

        self::assertTrue($this->testHandler->hasError(
            'API Error, will be retried. Retry count: 1x',
        ));

        self::assertTrue($this->testHandler->hasError(
            'API Error, will be retried. Retry count: 5x',
        ));
    }

    /** @return Response[] */
    private function getResponses(): array
    {
        $responses = [];

        for ($x = 0; $x <= SklikApi::RETRY_MAX_ATTEMPTS; $x++) {
            // @phpcs:ignore
            $responses[] = new Response(200, [], '{"status":200,"statusMessage":"OK","session":"1YGLTg9vEdngwPjochR59L-K3TCqjzsur_90WYb2IdPJBaFT8sAcyO0LqRg7dYZWFeUgoQBr1nPBo"}'); //login response
            // @phpcs:ignore
            $responses[] = new Response(200, [], '{"status":"error","message":"Server error","code":500}'); //request response
        }

        return $responses;
    }
}
