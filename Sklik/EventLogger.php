<?php
/**
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\SklikExtractorBundle\Sklik;

use Keboola\StorageApi\Client;
use Keboola\StorageApi\Event;

class EventLogger
{
	/**
	 * @var Client
	 */
	private $storageApi;
	private $config;
	private $runId;

	public function setConfig($config)
	{
		$this->config = $config;
	}

	public function setRunId($runId)
	{
		$this->runId = $runId;
	}

	public function setStorageApi($storageApi)
	{
		$this->storageApi = $storageApi;
	}

	public function log($message, $duration=null, $type=null, $params=array())
	{
		if (!$this->storageApi) {
			throw new \Exception('Storage API client not set');
		}

		$event = new Event();
		$event
			->setMessage($message)
			->setType($type? $type : Event::TYPE_INFO)
			->setComponent('ex-sklik')
			->setConfigurationId($this->config)
			->setRunId($this->runId);
		if ($duration) {
			$event->setDuration($duration);
		}
		if (count($params)) {
			$event->setParams($params);
		}
		$this->storageApi->createEvent($event);
	}

}