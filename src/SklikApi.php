<?php
declare(strict_types=1);

namespace Keboola\SklikExtractor;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

class SklikApi
{
    const API_URL = 'https://api.sklik.cz/jsonApi/drak/';
    const RETRIES_COUNT = 5;

    private $token;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var Client
     */
    private $client;
    private $session;

    public function __construct($token, $logger, $apiUrl = null)
    {
        $this->token = $token;
        $this->logger = $logger;
        $this->client = $this->initClient($apiUrl);
        $this->login();
    }

    public function login() : array
    {
        return $this->request('client.loginByToken', [$this->token]);
    }

    public function getListLimit()
    {
        $limit = 100;
        $limits = $this->requestAuthenticated('api.limits');
        foreach ($limits['batchCallLimits'] as $l) {
            if ($l['name'] == 'global.list') {
                $limit = $l['limit'];
                break;
            }
        }
        return $limit;
    }

    public function getAccounts() : array
    {
        $accounts = $this->requestAuthenticated('client.get');
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
            'dayBudgetSum' => $accounts['user']['dayBudgetSum']
        ]);
        return $accounts['foreignAccounts'];
    }

    public function createReport($resource, $restrictionFilter = [], $displayOptions = []) : array
    {
        if (!count($restrictionFilter)) {
            $restrictionFilter = new \stdClass();
        }
        if (!count($displayOptions)) {
            $displayOptions = new \stdClass();
        }
        return $this->requestAuthenticated("$resource.createReport", [$restrictionFilter, $displayOptions]);
    }

    public function readReport($resource, $reportId, $displayColumns = [], $allowEmptyStatistics = false) : array
    {
        $listLimit = $this->getListLimit();
        //@TODO pagination
        return $this->requestAuthenticated("$resource.readReport", [$reportId, [
            'offset' => 0,
            'limit' => $listLimit,
            'allowEmptyStatistics' => $allowEmptyStatistics,
            'displayColumns' => $displayColumns
        ]]);
    }

    protected function requestAuthenticated($method, $args = []) : array
    {
        array_unshift($args, ['session' => $this->session]);
        return $this->request($method, $args);
    }

    protected function request($method, $args = [], $retries = self::RETRIES_COUNT) : array
    {
        $decoder = new JsonDecode(true);
        try {
            $response = $this->client->post($method, ['json' => $args]);
            $responseJson = $decoder->decode($response->getBody(), JsonEncoder::FORMAT);
            if (isset($responseJson['session'])) {
                // refresh session token
                $this->session = $responseJson['session'];
            }
            return $responseJson;
        } catch (\Exception $e) {
            $response = $e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()
                ? $e->getResponse() : null;
            if ($response) {
                try {
                    $responseJson = $decoder->decode($response->getBody(), JsonEncoder::FORMAT);
                } catch (NotEncodableValueException $e) {
                    $responseJson = [];
                }

                $message = $responseJson['message'] ?? $response->getReasonPhrase();

                if ($response->getStatusCode() === 401) {
                    if ($method == 'client.loginByToken') {
                        throw Exception::apiError($message, $method, [], 401, $responseJson);
                    }
                    $this->logger->error('Error 401', [
                        'response' => $responseJson,
                        'method' => $method,
                        '$params' => Exception::filterParamsForLog($args)
                    ]);
                    if ($retries <= 0) {
                        throw Exception::apiError(
                            'API keeps failing on error 401',
                            $method,
                            $args,
                            $response->getStatusCode(),
                            $responseJson
                        );
                    }
                    sleep(rand(5, 10));
                    $this->login();
                    return $this->request($method, $args, $retries-1);
                }

                throw Exception::apiError($message, $method, $args, $response->getStatusCode(), $responseJson);
            }

            throw $e;
        }
    }

    protected function initClient($apiUrl = self::API_URL) : Client
    {
        $handlerStack = HandlerStack::create();

        $handlerStack->push(Middleware::retry(
            function ($retries,
                /** @noinspection PhpUnusedParameterInspection */
                RequestInterface $request, ResponseInterface $response = null, $error = null) {
                if ($retries >= self::RETRIES_COUNT) {
                    return false;
                } elseif ($response && $response->getStatusCode() > 499) {
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

        $handlerStack->push(Middleware::log(
            new \Monolog\Logger('ex-sklik', [new \Monolog\Handler\StreamHandler('php://stdout')]),
            new \GuzzleHttp\MessageFormatter("{method} {uri} HTTP/{version} {req_body}\nRESPONSE: {code} - {res_body})")
        ));

        return new Client([
            'base_uri' => $apiUrl,
            'handler' => $handlerStack,
            'timeout' => 600,
            'headers' => [
                'accept' => 'application/json',
                'content-type' => 'application/json; charset=utf-8'
            ],
        ]);
    }
}
