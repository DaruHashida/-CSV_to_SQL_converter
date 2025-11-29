<?php
declare(strict_types=1);

namespace DaruHashida\CsvToSql;

use SplFileObject;
use RuntimeException;
use IteratorAggregate;
use Generator;

class CsvReader implements IteratorAggregate
{
    private SplFileObject $file;

    public function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found: $filePath");
        }

        $this->file = new SplFileObject($filePath, "r");
        $this->file->setFlags(
            SplFileObject::READ_CSV |
            SplFileObject::READ_AHEAD |
            SplFileObject::SKIP_EMPTY |
            SplFileObject::DROP_NEW_LINE
        );
    }

    public function getHeaders(): array
    {
        $this->file->rewind();
        $headers = $this->file->current();

        if (empty($headers) || !is_array($headers)) {
            throw new RuntimeException("CSV file is empty or invalid.");
        }

        $bom = "\xEF\xBB\xBF";
        if (str_starts_with($headers[0], $bom)) {
            $headers[0] = substr($headers[0], 3);
        }

        return array_map('trim', $headers);
    }

    public function getIterator(): Generator
    {
        $this->file->rewind();
        $this->file->next();

        while (!$this->file->eof()) {
            $row = $this->file->current();
            $this->file->next();

            if (empty($row) || $row === [null] || trim(implode('', $row)) === '') continue;

            yield $row;
        }
    }
}
