<?php
declare(strict_types=1);

namespace DaruHashida\CsvToSql;

use PDO;

class SqlGenerator
{
    private string $tableName;

    public function __construct(string $tableName)
    {
        // Очищаем имя таблицы: разрешаем только латиницу, цифры и подчеркивание
        $this->tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    }

    public function getCreateTable(array $headers, array $types): string
    {
        $columns = [];
        foreach ($headers as $i => $colName) {
            // Экранируем имена колонок (backticks)
            $safeCol = "`" . str_replace("`", "``", $colName) . "`";

            $type = match ($types[$i] ?? ColumnAnalyzer::TYPE_VARCHAR) {
                ColumnAnalyzer::TYPE_INT => 'BIGINT',
                ColumnAnalyzer::TYPE_FLOAT => 'DOUBLE',
                ColumnAnalyzer::TYPE_TEXT => 'TEXT',
                default => 'VARCHAR(255)',
            };
            $columns[] = "    $safeCol $type";
        }

        return "CREATE TABLE IF NOT EXISTS `{$this->tableName}` (\n" . implode(",\n", $columns) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    }

    public function getInsertBatch(array $headers, array $rows, ?PDO $pdo = null): string
    {
        $safeHeaders = implode(", ", array_map(fn($h) => "`" . str_replace("`", "``", $h) . "`", $headers));
        $valuesList = [];
        $colCount = count($headers);

        foreach ($rows as $row) {
            $escapedRow = [];

            // Гарантируем, что в строке столько же колонок, сколько в заголовке
            // Если меньше - добиваем null-ами, если больше - обрезаем
            $row = array_slice(array_pad($row, $colCount, ''), 0, $colCount);

            foreach ($row as $val) {
                $val = trim((string)$val);

                if ($val === '') {
                    $escapedRow[] = 'NULL';
                } elseif (is_numeric($val) && !str_starts_with($val, '0')) {
                    // Числа оставляем как есть (кроме тех, что начинаются с 0, например телефоны)
                    $escapedRow[] = $val;
                } else {
                    // Экранирование
                    if ($pdo) {
                        $escapedRow[] = $pdo->quote($val);
                    } else {
                        // Фолбэк для записи в файл
                        $escapedRow[] = "'" . addslashes($val) . "'";
                    }
                }
            }
            $valuesList[] = "(" . implode(", ", $escapedRow) . ")";
        }

        return "INSERT INTO `{$this->tableName}` ($safeHeaders) VALUES " . implode(", ", $valuesList) . ";";
    }
}
