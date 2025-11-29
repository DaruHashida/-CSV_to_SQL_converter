<?php

declare(strict_types=1);

namespace DaruHashida\CsvToSql;

use PDO;
use RuntimeException;
use Exception;

class Converter
{
    private string $csvFilePath;
    private string $tableName;
    private int $batchSize;

    public function __construct(string $csvFilePath, ?string $tableName = null, int $batchSize = 1000)
    {
        $this->csvFilePath = $csvFilePath;
        $this->batchSize = $batchSize;

        $rawName = $tableName ?? pathinfo($csvFilePath, PATHINFO_FILENAME);
        // Принудительно чистим имя таблицы при создании
        $this->tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $rawName);
    }

    public function saveToFile(string $outputSqlPath): void
    {
        $handle = fopen($outputSqlPath, 'w');
        if (!$handle) {
            throw new RuntimeException("Cannot open file for writing: $outputSqlPath");
        }

        try {
            $this->process($handle, null);
        } finally {
            fclose($handle);
        }
    }

    public function saveToDatabase(PDO $pdo, bool $dropIfExists = true): void
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->beginTransaction();
        try {
            if ($dropIfExists) {
                $pdo->exec("DROP TABLE IF EXISTS `{$this->tableName}`");
            }

            $this->process(null, $pdo);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Единая логика обработки для обоих методов
     * @param resource|null $fileHandle Дескриптор файла (если пишем в файл)
     * @param PDO|null $pdo Объект PDO (если пишем в базу)
     */
    private function process($fileHandle, ?PDO $pdo): void
    {
        $reader = new CsvReader($this->csvFilePath);
        $analyzer = new ColumnAnalyzer();
        $generator = new SqlGenerator($this->tableName);

        // 1. Анализ структуры
        $headers = $reader->getHeaders();
        $types = $analyzer->analyze($reader);

        // 2. Создание таблицы
        $createSql = $generator->getCreateTable($headers, $types);

        if ($pdo) {
            $pdo->exec($createSql);
        } else {
            fwrite($fileHandle, $createSql . "\n\n");
        }

        // 3. Вставка данных
        $batch = [];
        foreach ($reader as $row) {
            $batch[] = $row;
            if (count($batch) >= $this->batchSize) {
                $insertSql = $generator->getInsertBatch($headers, $batch, $pdo);

                if ($pdo) {
                    $pdo->exec($insertSql);
                } else {
                    fwrite($fileHandle, $insertSql . "\n");
                }

                $batch = [];
            }
        }

        // 4. Остатки
        if (!empty($batch)) {
            $insertSql = $generator->getInsertBatch($headers, $batch, $pdo);
            if ($pdo) {
                $pdo->exec($insertSql);
            } else {
                fwrite($fileHandle, $insertSql . "\n");
            }
        }
    }
}
