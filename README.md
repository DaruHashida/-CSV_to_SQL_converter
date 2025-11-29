code
Markdown
# CSV to SQL Converter

A memory-efficient PHP library for converting large CSV files to SQL dumps or importing them directly into a database using PDO.

[![PHP Version](https://img.shields.io/badge/php-%5E8.0-blue)](https://www.php.net/releases/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

## Features

-   **Memory Efficient:** Uses generators and stream reading to handle large files (GBs) without RAM overflow.
-   **Smart Type Detection:** Automatically infers `INT`, `FLOAT`, `VARCHAR`, or `TEXT` types.
-   **Direct Import:** Supports direct insertion into MySQL/MariaDB via PDO with transactions.
-   **Safe:** Handles encoding (BOM removal) and SQL escaping.

## Installation

```bash
composer require daruhashida/csv-to-sql-converter
```

## Usage

### 1. Save as SQL File

Useful for generating migration dumps.

```
use DaruHashida\CsvToSql\Converter;

require 'vendor/autoload.php';

$converter = new Converter('/path/to/large_dataset.csv', 'users_table');

// Generates 'dump.sql' with CREATE TABLE and INSERT statements
$converter->saveToFile('dump.sql');
```

### 2. Direct Database Import

Imports data directly into the database using transactions. If an error occurs, changes are rolled back.

```
use DaruHashida\CsvToSql\Converter;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=test_db", "root", "password");
$converter = new Converter('/path/to/products.csv', 'products');

try {
// Second argument 'true' drops the table if it exists
$converter->saveToDatabase($pdo, true);
echo "Import successful!";
} catch (Exception $e) {
echo "Error: " . $e->getMessage();
}
```

## Requirements

- PHP 8.0 or higher
- PDO Extension
- SPL Extension

## License

MIT



