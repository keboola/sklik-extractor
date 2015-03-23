<?php
/**
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\SklikExtractor\Sklik;

use Monolog\Logger;
use Zend\XmlRpc\Client;
use Zend\Http\Client\Adapter\Exception\RuntimeException;

class Api
{
    const API_URL = 'https://api.sklik.cz/cipisek/RPC2';
    private $username;
    private $password;
    private $client;
    private $session;
    /**
     * @var Logger
     */
    private $logger;

    public function __construct($username, $password, Logger $logger)
    {
        $this->username = $username;
        $this->password = $password;
        $this->logger = $logger;

        $client = new \Zend\Http\Client(self::API_URL, [
            'adapter' => 'Zend\Http\Client\Adapter\Curl',
            'curloptions' => [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 120
            ],
            'timeout' => 120
        ]);
        $this->client = new Client(self::API_URL, $client);
        $this->login();
    }

    public function __destruct()
    {
        $this->logout();
    }

    public function login()
    {
        $this->call('client.login', [$this->username, $this->password]);
    }

    public function logout()
    {
        if ($this->session) {
            $this->call('client.logout', ['user' => ['session' => $this->session]]);
        }
    }

    public function getListLimit()
    {
        $limit = 100;
        $limits = $this->request('api.limits');
        foreach ($limits['batchCallLimits'] as $l) {
            if ($l['name'] == 'global.list') {
                $limit = $l['limit'];
                break;
            }
        }
        return $limit;
    }

    public function request($method, array $args = [])
    {
        $args = array_merge_recursive(['user' => ['session' => $this->session]], $args);
        return $this->call($method, $args);
    }

    private function call($method, array $args = [])
    {
        $maxRepeatCount = 10;
        $repeatCount = 0;
        do {
            $start = time();
            $repeatCount++;
            $exception = null;
            try {
                $result = $this->client->call($method, $args);
                $this->logger->log(Logger::DEBUG, 'API call ' . $method . ' finished', [
                    'params' => $args,
                    'status' => $result['status'],
                    'message' => isset($result['statusMessage'])? $result['statusMessage'] : null,
                    'duration' => time() - $start
                ]);
                if ($result['status'] == 200) {
                    if (isset($result['session'])) {
                        // refresh session token
                        $this->session = $result['session'];
                    }
                    return $result;
                } elseif ($result['status'] == 401) {
                    if ($method == 'client.login') {
                        throw new ApiException($result['statusMessage']);
                    } else {
                        $this->logout();
                        $this->login();
                    }
                } else {
                    $message = 'API Error ' . (isset($result['status'])? $result['status'] . ': ' : null)
                        . (isset($result['message'])? $result['message'] : null);
                    $e = new ApiException($message);
                    $e->setData([
                        'method' => $method,
                        'args' => $args,
                        'result' => $result
                    ]);
                    throw $e;
                }
            } catch (ApiException $e) {
                throw $e;
            } catch (RuntimeException $e) {
                switch ($e->getCode()) {
                    case 401: // Session expired or Authentication failed
                    case 502: // Bad gateway
                        $this->logout();
                        $this->login();
                        break;
                    case 404: // Not found
                        $this->logger->log(Logger::WARNING, 'API call ' . $method . ' finished with code 404', [
                            'params' => $args,
                            'duration' => time() - $start
                        ]);
                        return false;
                        break;
                    case 415: // Too many requests
                        sleep(rand(10, 30));
                        break;
                    default:
                        $exception = $e;
                }
            } catch (\Exception $e) {
                $exception = $e;
            }

            if ($repeatCount >= $maxRepeatCount) {
                $e = new ApiException('Sklik API max repeats error', $exception);
                $e->setData([
                    'method' => $method,
                    'args' => $args
                ]);
                throw $e;
            }

            $this->logger->log(Logger::WARNING, 'API call ' . $method . ' will be repeated', [
                'params' => $args,
                'duration' => time() - $start,
                'error' => $exception? [
                    'type' => get_class($exception),
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage()
                ] : null
            ]);

        } while (true);
    }

    public function getStats($userId, $campaignIdsBlock, $startDate, $endDate, $context = false)
    {
        $stats = $this->request('campaigns.stats', [
            'user' => [
                'userId' => $userId
            ],
            'campaignIds' => $campaignIdsBlock,
            'params' => [
                'dateFrom' => $startDate,
                'dateTo' => $endDate,
                'granularity' => 'daily',
                'includeFulltext' => $context ? false : true,
                'includeContext' => $context ? true : false
            ]
        ]);
        if (isset($stats['report'])) {
            return $stats['report'];
        } else {
            $this->logger->log(Logger::ALERT, 'Bad stats format', [
                'stats' => $stats
            ]);
        }
    }

    public function getCampaigns($userId)
    {
        $campaigns = $this->request('campaigns.list', ['user' => ['userId' => $userId]]);
        if (isset($campaigns['campaigns']) && count($campaigns['campaigns'])) {
            return $campaigns['campaigns'];
        } else {
            return array();
        }
    }

    public function getAccounts()
    {
        $accounts = $this->request('client.get');
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
}
