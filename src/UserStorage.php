<?php
declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Keboola\Csv\CsvWriter;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class UserStorage
{
    protected $tables;
    protected $path;

    protected $files = [];

    public function __construct(array $tables, string $path)
    {
        $this->tables = $tables;
        $this->path = $path;
    }

    public function addUserTable(string $table, array $columns, array $primary = []) : void
    {
        $this->tables[$table] = ['columns' => $columns, 'primary' => $primary];
    }

    public function save(string $table, array $data) : void
    {
        if (!isset($this->files[$table])) {
            $file = new CsvWriter("$this->path/$table.csv");
            $file->writeRow($this->tables[$table]['columns']);
            $this->files[$table] = $file;

            $this->createManifest(
                "$this->path/$table.csv",
                $table,
                $this->tables[$table]['primary'] ?? []
            );
        }

        if (!is_array($data)) {
            $data = (array)$data;
        }
        $dataToSave = [];
        foreach ($this->tables[$table]['columns'] as $c) {
            $dataToSave[$c] = $data[$c] ?? null;
        }

        /** @var CsvWriter $file */
        $file = $this->files[$table];
        $file->writeRow($dataToSave);
    }

    public function createManifest(string $fileName, string $table, array $primary = []) : void
    {
        if (!file_exists("$fileName.manifest")) {
            $jsonEncode = new JsonEncode();
            file_put_contents("$fileName.manifest", $jsonEncode->encode([
                'destination' => $table,
                'incremental' => true,
                'primary_key' => $primary
            ], JsonEncoder::FORMAT));
        }
    }
}
