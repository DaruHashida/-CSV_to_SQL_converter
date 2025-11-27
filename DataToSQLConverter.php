<?php

class DataToSQLConverter
{
    public string $sql_data;
    private string $directory_path;
    public string $database_name;
    private string $database_pass;
    public string $nameoffile;

    public function __construct(
        string $directory_path,
        string $database_name,
        string $nameoffile
    ) {
        $this->directory_path = $directory_path;
        $this->database_name = $database_name;
        $this->nameoffile = substr($nameoffile, 0, -4);
    }

    /*protected function loadCsvFiles(string $directory)
    {foreach(newDirectoryIterator($directory)) as $file)
        { if ($file->getExtention() == 'csv') {
        $this->filesToConvert[] = $file->getFileInfo();
        }
        }*/

    public function getData()
    {
        $path = $this->directory_path;
        $file = new SplFileObject($path, "r");
        $tablename = $this->nameoffile;
        $k = 0;
        $keys = [];
        $types = [];
        $data = '';
        $linesTotal = 0;
        do {
            if ($file->eof()) {
                break;
            }
            $line = $file->fgets();
            if (empty($line) or empty(trim($line))) {
                break;
            }
            $linesTotal += 1;
        } while (!empty($line) || !$file->eof());
        $file->seek(0);
        for ($k = 1; $k < $linesTotal; $k++) {
            $line = rtrim($file->fgets());
            if ($k == 1) {
                $line = str_replace(['"', ' '], '', $line);
                $keys = explode(',', $line);
                foreach ($keys as $key) {
                    $types = array_merge(
                        $types, [$key =>
                        ['type' => '',
                            'biggest_number' => 0,
                            'lowest_number' => 0,
                            'negate_nums' => false,
                            'longest_value' => 0]],
                    );
                    $$key = [];
                }
                $types = array_shift($types);
            } else {
                $values = explode(',', $line);
                for ($i = 0; $i < count($keys); $i++) {
                    $key = $keys[$i];
                    $value = &$values[$i];
                    $value = trim($value);
                    array_push($$key, $value);
                    $types = $this->typeSelector($key, $value, $types);
                    if (!is_numeric($value)) {
                        $value = "'" . $value . "'";
                    }
                }
                $line = implode(', ', $values);
                $data = $data . '(' . $line . '), ';
            }
        }
        $data = substr($data, 0, -2);
        $columns = '';
        $columnsWithTypes = '';
        for ($i = 0; $i < count($keys); $i++) {
            if ($i != 0) {
                $columns = $columns . ', ';
                $columnsWithTypes = $columnsWithTypes . ', ';
            }
            $columns = $columns . "`" . $keys[$i] . "`";
            $columnsWithTypes = $columnsWithTypes . "`" . $keys[$i] . "`" . ' ' . $types[$keys[$i]]['type'];
        }
        $sql_create_data = "CREATE TABLE $tablename ($columnsWithTypes);";
        $sql_insert_data = "INSERT INTO $tablename($columns) VALUES $data;";

        return ([$sql_create_data, $sql_insert_data]);
    }

    private function typeSelector($key, $value, $types)
    {
        $type = &$types[$key]['type'];
        $biggest_number = &$types[$key]['biggest_number'];
        $lowest_number = &$types[$key]['lowest_number'];
        $negate_nums = &$types[$key]['negate_nums'];
        $longest_value = &$types[$key]['longest_value'];
        $length = strlen($value);
        if (!(strripos($type ?? '', 'VARCHAR')) && is_numeric($value)) {
            if (ctype_digit($value)) {
                if ($value > $biggest_number) {
                    if (!$negate_nums) {
                        $biggest_number = (int)$value;
                        if ($biggest_number < 256) {
                            $type = 'TINYINT';
                        } elseif ($biggest_number < 65536) {
                            $type = 'SMALLINT';
                        } elseif ($biggest_number < 16777216) {
                            $type = 'MEDIUMINT';
                        } elseif ($biggest_number < 4294967296) {
                            $type = 'INT';
                        } else {
                            $type = 'BIGINT';
                        }
                    } else {
                        $biggest_number = (int)$value;
                        if ($biggest_number < 128) {
                            $type = 'TINYINT';
                        } elseif ($biggest_number < 32768) {
                            $type = 'SMALLINT';
                        } elseif ($biggest_number < 8388608) {
                            $type = 'MEDIUMINT';
                        } elseif ($biggest_number < 2147483648) {
                            $type = 'INT';
                        } else {
                            $type = 'BIGINT';
                        }
                    }
                }
            } elseif (0 < strripos($value, '.') and strripos($value, '.') < ($length - 1)) {
                $type = 'DOUBLE';
            } elseif (strripos($value, '-') == 0) {
                $negate_nums = true;
                if ($value < $lowest_number) {
                    $lowest_number = (int)$value;
                    if ($lowest_number > -128) {
                        $type = 'TINYINT';
                    } elseif ($lowest_number > -32768) {
                        $type = 'SMALLINT';
                    } elseif ($lowest_number > -8388608) {
                        $type = 'MEDIUMINT';
                    } elseif ($lowest_number > -2147483648) {
                        $type = 'INT';
                    } else {
                        $type = 'BIGINT';
                    }
                }
            }
        } /**
         * ЕСЛИ ЭТО НЕ ЧИСЛО
         */
        else {
            $value = "'" . $value . "'";
            if ($length > 65535) {
                $type = 'MEDIUMTEXT';
            } elseif ($length > 16777215) {
                $type = 'LONGTEXT';
            } else {
                if (strripos($type ?? '', 'CHAR') != false) {
                    if (iconv_strlen($value) > $longest_value) {
                        $longest_value = iconv_strlen($value);
                        $type = 'VARCHAR(' . $longest_value . ')';
                    }
                } else {
                    $type = 'VARCHAR(' . $length . ')';
                }
            }
        }

        return $types;
    }

}
