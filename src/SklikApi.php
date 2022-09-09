<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor;

use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Keboola\Component\UserException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\Policy\SimpleRetryPolicy;
use Retry\RetryProxy;
use stdClass;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Throwable;

class SklikApi
{
    protected const API_URL = 'https://api.sklik.cz/drak/json/';
    protected const RETRIES_COUNT = 5;

    /** @var string */
    private $loginMethod;
    /** @var array */
    private $loginParams;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var Client
     */
    private $client;
    /**
     * @var string
     */
    private $session;

    private const RETRY_MAX_ATTEMPTS = 5;

    private const RETRY_INITIAL_INTERVAL = 1000;

    public function __construct(LoggerInterface $logger, ?string $apiUrl = null)
    {
        $this->logger = $logger;
        $this->client = $this->initClient($apiUrl);
    }

    public function loginByToken(string $token): array
    {
        $this->loginMethod = 'client.loginByToken';
        $this->loginParams = [$token];
        $loginStatus = $this->login();
        if ($loginStatus['status'] !== 200) {
            throw new UserException($loginStatus['statusMessage'], $loginStatus['status']);
        }
        return $loginStatus;
    }

    public function login(): array
    {
        return $this->request($this->loginMethod, $this->loginParams);
    }

    public function getListLimit(): int
    {
        $limit = 100;
        $limits = $this->requestAuthenticated('api.limits');
        if (!isset($limits['batchCallLimits'])) {
            $message = 'API returned unexpected result to api.limits request. It is missing \'batchCallLimits\'.';
            $this->logger->error($message, [
                'method' => 'api.limits',
                'response' => $limits,
            ]);
            throw new UserException($message);
        }
        foreach ($limits['batchCallLimits'] as $l) {
            if ($l['name'] === 'global.list') {
                $limit = $l['limit'];
                break;
            }
        }
        return $limit;
    }

    public function getAccounts(): array
    {
        $accounts = $this->requestAuthenticated('client.get');
        if (!isset($accounts['user'])) {
            $message = 'API returned unexpected result to client.get request. It is missing \'user\' information.';
            $this->logger->error($message, [
                'method' => 'client.get',
                'response' => $accounts,
            ]);
            throw new UserException($message);
        }
        // Add user itself to check for reports
        array_unshift($accounts['foreignAccounts'], [
            'userId' => $accounts['user']['userId'],
            'username' => $accounts['user']['username'],
            'access' => null,
            'relationName' => null,
            'relationStatus' => null,
            'relationType' => null,
            'walletCredit' => $accounts['user']['walletCredit'],
            'walletCreditWithVat' => $accounts['user']['walletCreditWithVat'],
            'walletVerified' => $accounts['user']['walletVerified'],
            'accountLimit' => $accounts['user']['accountLimit'],
            'dayBudgetSum' => $accounts['user']['dayBudgetSum'],
        ]);
        return $accounts['foreignAccounts'];
    }

    public static function getReportLimit(string $from, string $to, int $listLimit, ?string $granularity = null): int
    {
        if (!$granularity) {
            return $listLimit;
        }

        $numberOfDays = (int) ((new DateTime($to))->diff(new DateTime($from)))->format('%a');
        switch ($granularity) {
            case 'daily':
                $unitDivider = 1;
                break;
            case 'weekly':
                $unitDivider = 7;
                break;
            case 'monthly':
                $unitDivider = 28;
                break;
            case 'quarterly':
                $unitDivider = 84;
                break;
            default:
                $unitDivider = 365;
        }

        $numberOfUnits = $numberOfDays / $unitDivider;
        $numberOfUnits = $numberOfUnits > 1 ? $numberOfUnits : 1;

        $limit = floor($listLimit / $numberOfUnits);
        if ($limit < 1) {
            throw new Exception('Data limit exceeded. Decrease date interval or granularity.');
        }
        return (int) $limit;
    }

    public function createReport(
        string $resource,
        ?array $restrictionFilter = [],
        ?array $displayOptions = [],
        ?int $userId = null
    ): array {
        if (!$restrictionFilter) {
            $restrictionFilter = new stdClass();
        }
        if (!$displayOptions) {
            $displayOptions = new stdClass();
        }
        $result = $this->requestAuthenticated(
            "$resource.createReport",
            [$restrictionFilter, $displayOptions],
            $userId
        );
        if (empty($result['reportId'])) {
            throw Exception::apiError(
                'Report Id is missing from createReport API call',
                "$resource.createReport",
                [$restrictionFilter, $displayOptions],
                200,
                $result
            );
        }
        return $result;
    }

    public function readReport(
        string $resource,
        string $reportId,
        ?array $displayColumns = [],
        ?int $offset = 0,
        ?int $limit = 100
    ): array {
        $args = [
            'offset' => $offset,
            'limit' => $limit,
            'allowEmptyStatistics' => true,
            'displayColumns' => $displayColumns,
        ];
        $result = $this->requestAuthenticated("$resource.readReport", [$reportId, $args]);
        if (!isset($result['report'])) {
            throw Exception::apiError(
                'Result is missing "report" field.',
                "$resource.readReport",
                $args,
                200,
                $result
            );
        }
        return $result['report'];
    }

    protected function requestAuthenticated(string $method, ?array $args = [], ?int $userId = null): array
    {
        $user = ['session' => $this->session];
        if ($userId) {
            $user['userId'] = $userId;
        }
        array_unshift($args, $user);
        return $this->request($method, $args);
    }

    protected function request(string $method, ?array $args = [], ?int $retries = self::RETRIES_COUNT): array
    {
        $decoder = new JsonDecode([JsonDecode::ASSOCIATIVE => true ]);
        $retryPolicy = new SimpleRetryPolicy(
            self::RETRY_MAX_ATTEMPTS,
            [RequestException::class, ClientException::class]
        );
        $retryProxy = new RetryProxy(
            $retryPolicy,
            new ExponentialBackOffPolicy(self::RETRY_INITIAL_INTERVAL),
            $this->logger
        );

        try {
            $response = $retryProxy->call(function () use ($method, $args): ResponseInterface {
                return $this->client->post($method, ['json' => $args]);
            });

            $responseJson = $decoder->decode((string) $response->getBody(), JsonEncoder::FORMAT);
            if (isset($responseJson['session'])) {
                // refresh session token
                $this->session = $responseJson['session'];
            }
            return $responseJson;
        } catch (Throwable $e) {
            $response = $e instanceof RequestException && $e->hasResponse()
                ? $e->getResponse() : null;
            if ($response) {
                try {
                    $responseJson = $decoder->decode((string) $response->b, JsonEncoder::FORMAT);
                } catch (NotEncodableValueException $e) {
                    $responseJson = [];
                }

                $message = $responseJson['message'] ?? $response->getReasonPhrase();

                // Get status code
                // The API returns some errors (500, 429) with HTTP code OK 200, but with a status code in the body.
                $statusCode = $response->getStatusCode();
                if ($statusCode === 200 && isset($responseJson['code'])) {
                    $statusCode = (int) $responseJson['code'];
                }

                // Throw on wrong credentials
                if ($statusCode === 401 && $method === 'client.loginByToken') {
                    throw Exception::apiError($message, $method, [], $response->getStatusCode(), $responseJson);
                }

                // Throw on other user error or 500 after retries
                if ($statusCode < 500 || $retries <= 0) {
                    throw Exception::apiError($message, $method, $args, $response->getStatusCode(), $responseJson);
                }

                // Retry 500 errors
                $this->logger->error('API Error, will be retried', [
                    'response' => $responseJson,
                    'method' => $method,
                    'params' => Exception::filterParamsForLog($args),
                ]);
                sleep(rand(5, 10));
                $this->login();
                return $this->request($method, $args, $retries - 1);
            }

            throw $e;
        }
    }

    protected function initClient(?string $apiUrl = null): Client
    {
        if (!$apiUrl) {
            $apiUrl = self::API_URL;
        }
        $handlerStack = HandlerStack::create();

        $handlerStack->push(Middleware::retry(
            function (
                $retries,
                RequestInterface $request,
                ?ResponseInterface $response = null,
                ?string $error = null
            ) {
                // Get status code
                $statusCode = $response === null ? null : $response->getStatusCode();

                if ($retries >= self::RETRIES_COUNT) {
                    return false;
                } elseif ($statusCode && ($statusCode > 499 || $statusCode === 429)) {
                    return true;
                } elseif ($error) {
                    return true;
                } else {
                    return false;
                }
            },
            function ($retries) {
                return (int) pow(2, $retries - 1) * 1000;
            }
        ));

        return new Client([
            'base_uri' => $apiUrl,
            'handler' => $handlerStack,
            'timeout' => 600,
            'headers' => [
                'accept' => 'application/json',
                'content-type' => 'application/json; charset=utf-8',
            ],
        ]);
    }
}
