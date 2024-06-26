<?php

declare(strict_types=1);

namespace Keboola\SklikExtractor;

use Keboola\Csv\CsvWriter;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use function Keboola\Utils\flattenArray;

class UserStorage
{
    /** @var array<string, array<string, string[]>>  */
    protected array $tables = [
        'accounts' => [
            'primary' => ['userId'],
            'columns' => ['userId', 'username', 'access', 'relationName', 'relationStatus', 'relationType',
                'walletCredit', 'walletCreditWithVat', 'walletVerified', 'accountLimit', 'dayBudgetSum'],
        ],
    ];
    protected string $path;

    /** @var array<string, CsvWriter> */
    protected array $files = [];

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function addUserTable(string $table, array $columns, array $primary = []): void
    {
        $this->tables[$table] = ['columns' => $columns, 'primary' => $primary];
    }

    public function save(string $table, array $data): void
    {
        if (!isset($this->files[$table])) {
            $file = new CsvWriter("$this->path/$table.csv");
            $file->writeRow($this->tables[$table]['columns']);
            $this->files[$table] = $file;

            $this->createManifest(
                "$this->path/$table.csv",
                $table,
                $this->tables[$table]['primary'] ?? [],
            );
        }

        if (!is_array($data)) {
            $data = (array) $data;
        }
        $dataToSave = [];
        foreach ($this->tables[$table]['columns'] as $c) {
            $dataToSave[$c] = $data[$c] ?? '';
        }

        /** @var CsvWriter $file */
        $file = $this->files[$table];
        $file->writeRow($dataToSave);
    }

    public function saveReport(string $name, array $data, int $accountId, string $primary = 'id'): void
    {
        if (!count($data)) {
            return;
        }

        foreach ($data as $row) {
            $rowId = $row[$primary];
            if (isset($row['stats'])) {
                // save stats to a separate table
                foreach ($row['stats'] as $stat) {
                    if (!isset($stat['date'])) {
                        $stat['date'] = null;
                    }
                    $save = [$primary => $rowId] + $this->prepareDataToSave($stat);

                    if (!isset($this->tables["$name-stats"])) {
                        $this->tables["$name-stats"] = [
                            'columns' => array_keys($save),
                            'primary' => [$primary, 'date'],
                        ];
                    }
                    $this->save("$name-stats", $save);
                }
                unset($row['stats']);
            }

            // save regions to a separate table
            if (isset($row['regions']) && is_array($row['regions']) && !empty($row['regions'])) {
                $this->processRegions($name, (string) $rowId, $row['regions']);
                unset($row['regions']);
            }

            // flatten nested arrays
            $row = flattenArray($row, '', '_');

            unset($row[$primary]);
            ksort($row);
            $row = [$primary => $rowId, 'accountId' => $accountId] + $row;

            if (!isset($this->tables[$name])) {
                $this->tables[$name] = ['columns' => array_keys($row), 'primary' => [$primary]];
            }
            $this->save($name, $row);
        }
    }

    public function createManifest(string $fileName, string $table, array $primary = []): void
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

    private function processRegions(string $parentName, string $campaignId, array $regionsData): void
    {
        $reportName = $parentName . '-regions';
        foreach ($regionsData as $item) {
            ksort($item);
            $dataToSave = [
                'campaignId' => $campaignId,
            ] + $item;

            if (!isset($this->tables[$reportName])) {
                $this->tables[$reportName] = [
                    'columns' => array_keys($dataToSave),
                    'primary' => [
                        'campaignId',
                        'id',
                    ],
                ];
            }
            $this->save($reportName, $dataToSave);
        }
    }

    private function prepareDataToSave(array $stat): array
    {
        $result = [];
        foreach ($stat as $k => $item) {
            if (is_array($item)) {
                foreach ($item as $key => $value) {
                    $result[sprintf('%s_%s', $k, $key)] = $value;
                }
            } else {
                $result[$k] = $item;
            }
        }
        ksort($result);
        return $result;
    }
}
