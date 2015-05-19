<?php
/**
 * @package sklik-extractor
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\SklikExtractor\Service;

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Client;
use Keboola\Syrup\Exception\ApplicationException;
use Keboola\Syrup\Exception\UserException;
use Keboola\Temp\Temp;
use Monolog\Logger;
use Symfony\Component\Debug\Exception\ContextErrorException;

class UserStorage
{
    /**
     * @var \Keboola\StorageApi\Client
     */
    protected $storageApiClient;
    /**
     * @var \Keboola\Temp\Temp
     */
    protected $temp;
    protected $appName;
    protected $files = [];
    protected $tables = [];

    /** @var Logger */
    protected $logger;

    public function __construct($appName, Client $storageApi, Temp $temp, Logger $logger)
    {
        $this->appName = $appName;
        $this->storageApiClient = $storageApi;
        $this->temp = $temp;
        $this->logger = $logger;
    }

    public function getBucketId($configId)
    {
        return 'in.c-'.$this->appName.'-'.$configId;
    }

    public function save($table, $data)
    {
        if (!isset($this->files[$table])) {
            $file = new CsvFile($this->temp->createTmpFile());
            $file->writeRow($this->tables[$table]['columns']);
            $this->files[$table] = $file;
        }

        if (!is_array($data)) {
            $data = (array)$data;
        }
        $dataToSave = [];
        foreach ($this->tables[$table]['columns'] as $c) {
            $dataToSave[$c] = isset($data[$c]) ? $data[$c] : null;
        }

        /** @var CsvFile $file */
        $file = $this->files[$table];
        $file->writeRow($dataToSave);
    }

    public function uploadData($configId)
    {
        if (!$this->storageApiClient->bucketExists($this->getBucketId($configId))) {
            $this->storageApiClient->createBucket($this->appName.'-'.$configId, 'in', $this->appName.' Data Storage');
        }

        foreach ($this->files as $name => $file) {
            $this->uploadTable(
                $configId,
                $name,
                $file,
                !empty($this->tables['primaryKey']) ? $this->tables['primaryKey'] : null
            );
        }
    }

    public function uploadTable($configId, $name, $file, $primaryKey = null)
    {
        $tableId = $this->getBucketId($configId) . "." . $name;
        try {
            $options = [];
            if ($primaryKey) {
                $options['primaryKey'] = $primaryKey;
            }

            if ($this->storageApiClient->tableExists($tableId)) {
                $this->storageApiClient->dropTable($tableId);
            }

            // Allow three tries to upload
            $success = false;
            for($i = 0; $i <= 2 && !$success; $i++) {
                try {
                    $this->storageApiClient->createTableAsync($this->getBucketId($configId), $name, $file, $options);
                    $success = true;
                } catch (ContextErrorException $e) {

                    $this->logger->alert("Error saving data", [
                        'tableId' => $tableId,
                        'configId' => $configId,
                        'name' => $name,
                        'file' => $file,
                        'options' => $options,
                        'exception' => $e
                    ]);
                }
            }

            if (!$success) {
                throw new ApplicationException("Table was not uploaded", [
                    'tableId' => $tableId,
                    'configId' => $configId,
                    'name' => $name,
                    'file' => $file,
                    'options' => $options
                ]);
            }

        } catch (\Keboola\StorageApi\ClientException $e) {
            throw new UserException(sprintf('Error during upload of table %s to Storage API. %s', $tableId, $e->getMessage()), $e);
        }
    }
}
