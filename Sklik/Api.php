<?php
/**
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\SklikExtractorBundle\Sklik;

use Syrup\ComponentBundle\Exception\UserException;
use Zend\XmlRpc\Client;
use Zend\XmlRpc\Client\Exception\HttpException;

class ApiException extends UserException
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

		$this->client = new Client(self::API_URL);
		$this->client->getHttpClient()->setOptions(array(
			'timeout' => 30
		));
		$this->client->getHttpClient()->getAdapter()->setOptions(array('sslverifypeer' => false));
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
		$maxRepeatCount = 10;
		$repeatCount = 0;
		do {
			$repeatCount++;
			$exception = null;
			try {
				$result = $this->client->call($method, $args);
				if ($result['status'] == 401) {
					if ($method == 'client.login') {
						throw new ApiException($result['statusMessage']);
					} else {
						$this->logout();
						$this->login();
					}
				} else {
					if (isset($result['session'])) {
						// refresh session token
						$this->session = $result['session'];
					}
					return $result;
				}
			} catch (HttpException $e) {
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

			if ($exception) {
				$e = new ApiException($exception->getMessage(), $exception);
				$e->setData(array(
					'method' => $method,
					'args' => $args
				));
				throw $e;
			}

			if ($repeatCount >= $maxRepeatCount) {
				$e = new ApiException('Sklik Api max repeats error', $exception);
				$e->setData(array(
					'method' => $method,
					'args' => $args
				));
				throw $e;
			}

		} while (true);
	}
}
