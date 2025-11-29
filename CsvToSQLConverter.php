<?php
class CsvToSQLConverter
{
    const TYPE_INT = 0;
    const TYPE_FLOAT = 1;
    const TYPE_VARCHAR = 2;
    const TYPE_TEXT = 3;

    private string $filePath;
    private string $tableName;
    private int $batchSize;

    /**
     * @param string $filePath Путь к CSV файлу
     * @param string|null $tableName Имя таблицы (если null, берется из имени файла)
     * @param int $batchSize Количество строк в одном INSERT запросе
     */
    public function __construct(string $filePath, ?string $tableName = null, int $batchSize = 1000)
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Файл не найден: $filePath");
        }
        $this->filePath = $filePath;

        // Если имя таблицы не передано, берем имя файла без расширения
        $this->tableName = $tableName ?? pathinfo($filePath, PATHINFO_FILENAME);

        // Очистка имени таблицы от недопустимых символов
        $this->tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $this->tableName);

        $this->batchSize = $batchSize;
    }

    /**
     * Основной метод: создает SQL файл
     * @param string $outputSqlPath Путь для сохранения результата (например, 'dump.sql')
     */
    public function convert(string $outputSqlPath): void
    {
        // Настройка чтения CSV
        $file = new SplFileObject($this->filePath, "r");
        $file->setFlags(
            SplFileObject::READ_CSV |
            SplFileObject::READ_AHEAD |
            SplFileObject::SKIP_EMPTY |
            SplFileObject::DROP_NEW_LINE
        );

        // Открываем файл для записи SQL
        $output = fopen($outputSqlPath, 'w');
        if (!$output) {
            throw new RuntimeException("Не удалось создать файл: $outputSqlPath");
        }

        // 1. Анализируем типы данных
        [$headers, $columnTypes] = $this->analyzeStructure($file);

        // 2. Создаем таблицу
        $createTableSql = $this->generateCreateTableSql($headers, $columnTypes);
        fwrite($output, $createTableSql . "\n\n");

        // 3. Пишем данные
        $this->generateInserts($file, $output, $headers);

        fclose($output);
    }

    private function analyzeStructure(SplFileObject $file): array
    {
        $file->rewind();
        $headers = $file->current();

        if (empty($headers)) {
            throw new RuntimeException("CSV файл пуст");
        }

        // Очистка заголовков от BOM и пробелов
        $headers[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $headers[0]);
        $headers = array_map('trim', $headers);

        $file->next();

        $colCount = count($headers);
        $types = array_fill(0, $colCount, self::TYPE_INT);
        $maxLengths = array_fill(0, $colCount, 0);

        while (!$file->eof()) {
            $row = $file->current();
            $file->next();

            if (empty($row)) continue;

            $limit = min(count($row), $colCount);

            for ($i = 0; $i < $limit; $i++) {
                $val = trim($row[$i]);
                $len = strlen($val);

                if ($len > $maxLengths[$i]) {
                    $maxLengths[$i] = $len;
                }

                if ($val === '') continue;

                $currentType = $types[$i];

                if ($currentType === self::TYPE_INT) {
                    // Проверка: это НЕ целое число?
                    if (!ctype_digit($val) && !(str_starts_with($val, '-') && ctype_digit(substr($val, 1)))) {
                        if (is_numeric($val)) {
                            $types[$i] = self::TYPE_FLOAT;
                        } else {
                            $types[$i] = self::TYPE_VARCHAR;
                        }
                    }
                } elseif ($currentType === self::TYPE_FLOAT) {
                    if (!is_numeric($val)) {
                        $types[$i] = self::TYPE_VARCHAR;
                    }
                }

                // Если строка длинная — это TEXT
                if ($types[$i] === self::TYPE_VARCHAR && $maxLengths[$i] > 255) {
                    $types[$i] = self::TYPE_TEXT;
                }
            }
        }

        return [$headers, $types];
    }

    private function generateCreateTableSql(array $headers, array $types): string
    {
        $columns = [];
        foreach ($headers as $i => $colName) {
            $safeColName = "`" . str_replace("`", "``", $colName) . "`";
            $typeCode = $types[$i] ?? self::TYPE_VARCHAR;

            $sqlType = match ($typeCode) {
                self::TYPE_INT => 'BIGINT',
                self::TYPE_FLOAT => 'DOUBLE',
                self::TYPE_TEXT => 'TEXT',
                default => 'VARCHAR(255)',
            };

            $columns[] = "    $safeColName $sqlType";
        }

        return "CREATE TABLE IF NOT EXISTS `{$this->tableName}` (\n" . implode(",\n", $columns) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    }

    private function generateInserts(SplFileObject $file, $outputHandle, array $headers): void
    {
        $file->rewind();
        $file->next(); // Пропускаем заголовки

        $escapedHeaders = array_map(fn($h) => "`" . str_replace("`", "``", $h) . "`", $headers);
        $insertPrefix = "INSERT INTO `{$this->tableName}` (" . implode(", ", $escapedHeaders) . ") VALUES ";

        $buffer = [];

        while (!$file->eof()) {
            $row = $file->current();
            $file->next();

            if (empty($row) || count($row) !== count($headers)) continue;

            $escapedValues = array_map(function ($val) {
                $val = trim($val);
                if ($val === '') return 'NULL';
                if (is_numeric($val) && !str_starts_with($val, '0')) {
                    return $val;
                }
                return "'" . addslashes($val) . "'";
            }, $row);

            $buffer[] = "(" . implode(", ", $escapedValues) . ")";

            if (count($buffer) >= $this->batchSize) {
                fwrite($outputHandle, $insertPrefix . implode(", ", $buffer) . ";\n");
                $buffer = [];
            }
        }

        if (!empty($buffer)) {
            fwrite($outputHandle, $insertPrefix . implode(", ", $buffer) . ";\n");
        }
    }
}
