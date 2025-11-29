<?php
declare(strict_types=1);

namespace DaruHashida\CsvToSql;

class ColumnAnalyzer
{
    public const TYPE_INT = 0;
    public const TYPE_FLOAT = 1;
    public const TYPE_VARCHAR = 2;
    public const TYPE_TEXT = 3;

    public function analyze(CsvReader $reader): array
    {
        $headers = $reader->getHeaders();
        $colCount = count($headers);

        $types = array_fill(0, $colCount, self::TYPE_INT);
        $maxLengths = array_fill(0, $colCount, 0);

        foreach ($reader as $row) {
            // Ограничиваемся количеством заголовков
            $limit = min(count($row), $colCount);

            for ($i = 0; $i < $limit; $i++) {
                $val = trim((string)$row[$i]);
                if ($val === '') continue;

                $len = strlen($val);
                if ($len > $maxLengths[$i]) {
                    $maxLengths[$i] = $len;
                }

                // Логика "повышения" типов
                if ($types[$i] === self::TYPE_INT) {
                    // Проверяем, похоже ли на целое число (включая отрицательные)
                    if (!ctype_digit($val) && !(str_starts_with($val, '-') && ctype_digit(substr($val, 1)))) {
                        $types[$i] = is_numeric($val) ? self::TYPE_FLOAT : self::TYPE_VARCHAR;
                    }
                } elseif ($types[$i] === self::TYPE_FLOAT) {
                    if (!is_numeric($val)) {
                        $types[$i] = self::TYPE_VARCHAR;
                    }
                }

                // Если VARCHAR длиннее 255 символов -> TEXT
                if ($types[$i] === self::TYPE_VARCHAR && $maxLengths[$i] > 255) {
                    $types[$i] = self::TYPE_TEXT;
                }
            }
        }

        return $types;
    }
}
