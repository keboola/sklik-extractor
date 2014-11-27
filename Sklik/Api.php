<?php
/**
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\SklikExtractorBundle\Sklik;

use Keboola\ExtractorBundle\Common\Logger;
use Syrup\ComponentBundle\Exception\UserException;
use Zend\XmlRpc\Client;
use Zend\Http\Client\Adapter\Exception\RuntimeException;

class ApiException extends UserException
{

}

class Api
{
	const API_URL = 'https://api.sklik.cz/cipisek/RPC2';
	private $username;
	private $password;
	private $client;
	private $session;

	private $config, $jobId, $runId;

	public function __construct($username, $password, $config, $jobId, $runId)
	{
		$this->username = $username;
		$this->password = $password;
		$this->config = $config;
		$this->jobId = $jobId;
		$this->runId = $runId;

		$client = new \Zend\Http\Client(self::API_URL, array(
			'adapter'   => 'Zend\Http\Client\Adapter\Curl',
			'curloptions' => array(CURLOPT_SSL_VERIFYPEER => false),
		));
		$this->client = new Client(self::API_URL, $client);
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
			$this->call('client.logout', array('user' => array('session' => $this->session)));
		}
	}

	public function getListLimit()
	{
		$limit = 100;
		$limits = $this->request('api.limits');
		foreach($limits['batchCallLimits'] as $l) {
			if ($l['name'] == 'global.list') {
				$limit = $l['limit'];
				break;
			}
		}
		return $limit;
	}

	public function request($method, array $args=array())
	{
		$args = array_merge_recursive(array('user' => array('session' => $this->session)), $args);
		return $this->call($method, $args);
	}

	private function call($method, array $args=array())
	{
		$maxRepeatCount = 10;
		$repeatCount = 0;
		do {
			$start = time();
			$repeatCount++;
			$exception = null;
			try {
				$result = $this->client->call($method, $args);
				Logger::log(\Monolog\Logger::DEBUG, 'API call ' . $method . ' finished', array(
					'params' => $args,
					'status' => $result['status'],
					'message' => isset($result['statusMessage'])? $result['statusMessage'] : null,
					'duration' => time() - $start
				));
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
					$e->setData(array(
						'method' => $method,
						'args' => $args,
						'result' => $result
					));
					throw $e;
				}
			} catch (RuntimeException $e) {
				switch ($e->getCode()) {
					case 401: // Session expired or Authentication failed
					case 502: // Bad gateway
						$this->logout();
						$this->login();
						break;
					case 404: // Not found
						Logger::log(\Monolog\Logger::WARNING, 'API call ' . $method . ' finished with code 404', array(
							'params' => $args,
							'duration' => time() - $start
						));
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
				$e->setData(array(
					'method' => $method,
					'args' => $args
				));
				throw $e;
			}

			Logger::log(\Monolog\Logger::WARNING, 'API call ' . $method . ' will be repeated', array(
				'params' => $args,
				'duration' => time() - $start,
				'error' => $exception? array(
					'type' => get_class($exception),
					'code' => $exception->getCode(),
					'message' => $exception->getMessage()
				) : null
			));

		} while (true);
	}
}
