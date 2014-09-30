<?php
/**
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\SklikExtractorBundle\Sklik;

use fXmlRpc\Client;
use fXmlRpc\Transport\Guzzle4Bridge;
use GuzzleHttp\Subscriber\Retry\RetrySubscriber;
use Syrup\ComponentBundle\Exception\SyrupComponentException;

class ApiException extends SyrupComponentException
{

}

class Api
{
	const API_URL = 'https://api.sklik.cz/RPC2';
	private $username;
	private $password;
	private $client;
	private $session;

	public function __construct($username, $password)
	{
		$this->username = $username;
		$this->password = $password;

		$httpClient = new \GuzzleHttp\Client();
		$httpClient->getEmitter()->attach(new RetrySubscriber(array(
			'filter' => RetrySubscriber::createChainFilter(array(
					RetrySubscriber::createCurlFilter(),
					RetrySubscriber::createStatusFilter([415, 500, 503])
				))
		)));
		$this->client = new Client(self::API_URL, new Guzzle4Bridge($httpClient));
		$this->login();
	}

	public function __destruct()
	{
		$this->logout();
	}

	public function login()
	{
		$this->call('client.login', array($this->username, $this->password));
	}

	public function logout()
	{
		if ($this->session) {
			$this->call('client.logout', array($this->session));
		}
	}

	public function request($method, array $args=array())
	{
		$args = array_merge(array($this->session), $args);
		return $this->call($method, $args);
	}

	private function call($method, array $args=array())
	{
		$result = $this->client->call($method, $args);
		if(isset($result['status'])) {
			switch ($result['status']) {
				case 200:
					if (isset($result['session'])) {
						// refresh session token
						$this->session = $result['session'];
					}
					return $result;
					break;
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
					return $this->call($method, $args);
					break;
				default:

			}
		}

		$e = new ApiException(400, 'Sklik Api error');
		$e->setData(array(
			'method' => $method,
			'args' => $args,
			'result' => $result
		));
		throw $e;
	}
}
