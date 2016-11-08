<?php
/**
 * @package ex-sklik
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\SklikExtractor;

use Zend\XmlRpc\Client;
use Zend\Http\Client\Adapter\Exception\RuntimeException;

class Api
{
    const API_URL = 'https://api.sklik.cz/cipisek/RPC2';

    private $username;
    private $password;
    private $client;
    private $session;

    public function __construct($username, $password, $apiUrl = null)
    {
        $this->username = $username;
        $this->password = $password;

        if (!$apiUrl) {
            $apiUrl = self::API_URL;
        }
        $client = new \Zend\Http\Client($apiUrl, [
            'adapter' => 'Zend\Http\Client\Adapter\Curl',
            'curloptions' => [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 120
            ],
            'timeout' => 120
        ]);
        $this->client = new Client($apiUrl, $client);
        $this->login();
    }

    public function login()
    {
        return $this->call('client.login', [$this->username, $this->password]);
    }

    public function logout()
    {
        if ($this->session) {
            return $this->call('client.logout', ['user' => ['session' => $this->session]]);
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

    protected function request($method, array $args = [])
    {
        $args = array_merge_recursive(['user' => ['session' => $this->session]], $args);
        return $this->call($method, $args);
    }

    protected function call($method, array $args = [])
    {
        $maxRepeatCount = 10;
        $repeatCount = 0;
        do {
            $repeatCount++;
            $exception = null;
            try {
                $result = $this->client->call($method, $args);

                if ($result['status'] == 200) {
                    if (isset($result['session'])) {
                        // refresh session token
                        $this->session = $result['session'];
                    }
                    return $result;
                } elseif ($result['status'] == 401) {
                    if ($method == 'client.login') {
                        throw new Exception($result['statusMessage']);
                    } else {
                        $this->logout();
                        $this->login();
                    }
                } else {
                    $message = (isset($result['status']) ? "{$result['status']}: " : null)
                        . (isset($result['message'])? $result['message'] : null);
                    throw Exception::apiError($message, $method, $args, $result);
                }
            } catch (Exception $e) {
                throw $e;
            } catch (RuntimeException $e) {
                switch ($e->getCode()) {
                    case 401: // Session expired or Authentication failed
                    case 502: // Bad gateway
                        $this->logout();
                        $this->login();
                        break;
                    case 404: // Not found
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
                $result = [];
                if ($exception) {
                    $result = [
                        'exception' => $exception->getMessage(),
                        'code' => $exception->getCode()
                    ];
                }
                throw Exception::apiError('API max repeats error', $method, $args, $result);
            }
        } while (true);
    }

    public function getStats($userId, $campaignIdsBlock, $startDate, $endDate, $impressionShare = false, $context = false)
    {
        $args = [
            'user' => [
                'userId' => (int)$userId
            ],
            'campaignIds' => $campaignIdsBlock,
            'params' => [
                'dateFrom' => $startDate,
                'dateTo' => $endDate,
                'granularity' => 'daily',
                'includeFulltext' => $context ? false : true,
                'includeContext' => $context ? true : false,
                'includeImpressionShare' => (bool)$impressionShare
            ]
        ];

        $stats = $this->request('campaigns.stats', $args);
        if (isset($stats['report'])) {
            return $stats['report'];
        } else {
            throw Exception::apiError('Stats have bad format', 'campaign.stats', $args, $stats);
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
