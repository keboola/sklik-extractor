<?php
/**
 * @package wr-tableau-server
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\SklikExtractor;

abstract class AbstractTest extends \PHPUnit_Framework_TestCase
{
    /** @var  \Zend\XmlRpc\Client */
    protected $client;
    protected $session;

    protected function getClient()
    {
        if (!$this->client) {
            $this->client = new \Zend\XmlRpc\Client(EX_SK_API_URL, new \Zend\Http\Client(EX_SK_API_URL, [
                'adapter' => 'Zend\Http\Client\Adapter\Curl',
                'curloptions' => [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT => 120
                ],
                'timeout' => 120
            ]));
        }
        return $this->client;
    }

    protected function getSession()
    {
        if (!$this->session) {
            $result = $this->getClient()->call('client.login', [EX_SK_USERNAME, EX_SK_PASSWORD]);
            $this->session = $result['session'];
        }
        return $this->session;
    }

    protected function setUp()
    {
        $campaigns = $this->getClient()->call('campaigns.list', [
            'user' => ['userId' => (int)EX_SK_USER_ID, 'session' => $this->getSession()]
        ]);
        if (isset($campaigns['campaigns']) && count($campaigns['campaigns'])) {
            $this->getClient()->call('campaigns.remove', [
                'user' => ['userId' => (int)EX_SK_USER_ID, 'session' => $this->getSession()],
                'campaignIds' => array_column($campaigns['campaigns'], 'id')
            ]);
        }
    }

    protected function createCampaign($name)
    {
        $result = $this->getClient()->call('campaigns.create', [
            'user' => [
                'session' => $this->getSession()
            ],
            'campaigns' => [[
                'name' => $name,
                'dayBudget' => 10000
            ]]
        ]);
        return $result['campaignIds'][0];
    }
}
