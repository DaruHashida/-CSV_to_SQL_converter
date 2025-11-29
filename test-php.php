<?php
// ОБЯЗАТЕЛЬНО подключаем автозагрузчик
require_once __DIR__ . '/vendor/autoload.php';

use DaruHashida\CsvToSql\Converter;

try {
    // Если здесь не упадет ошибка "Class not found", значит всё работает!
    $converter = (new Converter('test.csv'))->saveToFile('test.sql');
    echo "Класс успешно загружен!";
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
}
