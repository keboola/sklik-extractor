<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Keboola\Csv\CsvWriter;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class UserStorage
{
    /**
     * @var array
     */
    protected $tables = [
        'accounts' => [
            'primary' => ['userId'],
            'columns' => ['userId', 'username', 'access', 'relationName', 'relationStatus', 'relationType',
                'walletCredit', 'walletCreditWithVat', 'walletVerified', 'accountLimit', 'dayBudgetSum'],
        ],
    ];
    /**
     * @var string
     */
    protected $path;
    /**
     * @var array
     */
    protected $files = [];

    public function __construct(string $path)
    {
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
            $data = (array) $data;
        }
        $dataToSave = [];
        foreach ($this->tables[$table]['columns'] as $c) {
            $dataToSave[$c] = $data[$c] ?? null;
        }

        /** @var CsvWriter $file */
        $file = $this->files[$table];
        $file->writeRow($dataToSave);
    }

    public function saveReport(string $name, array $data, int $accountId) : void
    {
        if (!count($data)) {
            return;
        }

        foreach ($data as $row) {
            $rowId = $row['id'];
            if (isset($row['stats'])) {
                // save stats to a separate table
                foreach ($row['stats'] as $stat) {
                    if (!isset($stat['date'])) {
                        $stat['date'] = null;
                    }
                    ksort($stat);
                    $save = ['id' => $rowId] + $stat;

                    if (!isset($this->tables["$name-stats"])) {
                        $this->tables["$name-stats"] = ['columns' => array_keys($save), 'primary' => ['id', 'date']];
                    }
                    $this->save("$name-stats", $save);
                }
                unset($row['stats']);
            }

            // flatten nested arrays
            foreach ($row as $colName => $colValue) {
                if (is_array($colValue)) {
                    foreach ($colValue as $colNestedName => $colNestedValue) {
                        $row["{$colName}_{$colNestedName}"] = $colNestedValue;
                    }
                    unset($row[$colName]);
                }
            }

            unset($row['id']);
            ksort($row);
            $row = ['id' => $rowId, 'accountId' => $accountId] + $row;

            if (!isset($this->tables[$name])) {
                $this->tables[$name] = ['columns' => array_keys($row), 'primary' => ['id']];
            }
            $this->save($name, $row);
        }
    }

    public function createManifest(string $fileName, string $table, array $primary = []) : void
    {
        if (!file_exists("$fileName.manifest")) {
            $jsonEncode = new JsonEncode();
            file_put_contents("$fileName.manifest", $jsonEncode->encode([
                'destination' => $table,
                'incremental' => true,
                'primary_key' => $primary,
            ], JsonEncoder::FORMAT));
        }
    }
}
