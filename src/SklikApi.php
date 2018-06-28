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

    public function login(): array
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

    public function getAccounts(): array
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

    public static function getReportLimit($from, $to, $listLimit, $granularity = null)
    {
        if (!$granularity) {
            return $listLimit;
        }

        $numberOfDays = (int)((new \DateTime($to))->diff(new \DateTime($from)))->format('%a');
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
        return (int)$limit;
    }

    public function createReport($resource, $restrictionFilter = [], $displayOptions = []): array
    {
        if (!count($displayOptions)) {
            $displayOptions = new \stdClass();
        }
        if (!isset($restrictionFilter['dateFrom'])) {
            throw new Exception('Setting of dateFrom on restrictionFilter is required');
        }
        if (!isset($restrictionFilter['dateTo'])) {
            throw new Exception('Setting of dateTo on restrictionFilter is required');
        }

        return $this->requestAuthenticated("$resource.createReport", [$restrictionFilter, $displayOptions]);
    }

    public function readReport(
        $resource,
        $reportId,
        $displayColumns = [],
        $offset = 0,
        $limit = 100
    ): array {
        $result = $this->requestAuthenticated("$resource.readReport", [
            $reportId,
            [
                'offset' => $offset,
                'limit' => $limit,
                'allowEmptyStatistics' => true,
                'displayColumns' => $displayColumns
            ]
        ]);
        return $result['report'];
    }

    protected function requestAuthenticated($method, $args = []): array
    {
        array_unshift($args, ['session' => $this->session]);
        return $this->request($method, $args);
    }

    protected function request($method, $args = [], $retries = self::RETRIES_COUNT): array
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
                    return $this->request($method, $args, $retries - 1);
                }

                throw Exception::apiError($message, $method, $args, $response->getStatusCode(), $responseJson);
            }

            throw $e;
        }
    }

    protected function initClient($apiUrl = self::API_URL): Client
    {
        $handlerStack = HandlerStack::create();

        $handlerStack->push(Middleware::retry(
            function (
                $retries,
                /** @noinspection PhpUnusedParameterInspection */
                RequestInterface $request,
                ResponseInterface $response = null,
                $error = null
            ) {
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
                return (int)pow(2, $retries - 1) * 1000;
            }
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
