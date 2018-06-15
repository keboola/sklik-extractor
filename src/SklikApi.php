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

    public function login()
    {
        return $this->call('client.loginByToken', [$this->token]);
    }

    private function call($method, $params, $retries = self::RETRIES_COUNT)
    {
        try {
            $response = $this->client->post($method, ['json' => $params]);
            return $response;
        } catch (\Exception $e) {
            $response = $e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()
                ? $e->getResponse() : null;
            if ($response) {
                $decoder = new JsonDecode(true);
                $responseJson = $decoder->decode($response->getBody(), JsonEncoder::FORMAT);

                if ($response->getStatusCode() === 200) {
                    if (isset($responseJson['session'])) {
                        // refresh session token
                        $this->session = $responseJson['session'];
                    }
                    return $responseJson;
                }

                if ($response->getStatusCode() === 401) {
                    if ($method == 'client.loginByToken') {
                        throw Exception::apiError($responseJson['statusMessage'], $method, [], 401, $responseJson);
                    }
                    $this->logger->error('Error 401', [
                        'response' => $responseJson,
                        'method' => $method,
                        '$params' => Exception::filterParamsForLog($params)
                    ]);
                    if ($retries <= 0) {
                        throw Exception::apiError('API keeps failing on error 401', $method, $params, $response->getStatusCode(), $responseJson);
                    }
                    sleep(rand(5, 10));
                    $this->login();
                    return $this->call($method, $params, $retries-1);
                }

                $message = (isset($responseJson['status']) ? "{$responseJson['status']}: " : null)
                    . (isset($responseJson['message'])? $responseJson['message'] : null);
                throw Exception::apiError($message, $method, $params, $response->getStatusCode(), $responseJson);
            }

            throw $e;
        }
    }

    protected function initClient($apiUrl = self::API_URL)
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
