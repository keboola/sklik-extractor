<?php
/**
 * @package sklik-extractor
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\SklikExtractor;

use Keboola\Csv\CsvFile;
use Symfony\Component\Yaml\Yaml;

class UserStorage
{
    protected $tables;
    protected $path;
    protected $bucket;

    protected $files = [];

    public function __construct(array $tables, $path, $bucket)
    {
        $this->tables = $tables;
        $this->path = $path;
        $this->bucket = $bucket;
    }

    public function save($table, $data)
    {
        if (!isset($this->files[$table])) {
            $file = new CsvFile("$this->path/$this->bucket.$table.csv");
            $file->writeRow($this->tables[$table]['columns']);
            $this->files[$table] = $file;

            file_put_contents("$this->path/$this->bucket.$table.csv.manifest", Yaml::dump([
                'destination' => "$this->bucket.$table",
                'incremental' => 1
            ]));
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
}
