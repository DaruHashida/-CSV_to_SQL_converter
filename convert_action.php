<?php
require_once "validation_func.php";
require_once "DataToSQLConverter.php";
$errors = [];

if(notFilled('database_name'))
{
    $errors=array_push($errors, notFilled('database_name'));
}
else {
    $database_name = $_POST['database_name'];
}

$server_name=$_POST['server_name']!=''?$_POST['server_name']:'127.0.0.1';
$database_pass=$_POST['database_pass'];
$database_login=$_POST['database_login']!=''?$_POST['database_login']:'root';
$link = mysqli_connect($server_name, $database_login, $database_pass, $database_name);
if($_FILES['file']) {
    for ($i=0;$i<count($_FILES['file']['name']);$i++) {
        $loader = new DataToSQLConverter($_FILES['file']['tmp_name'][$i],
            $database_name,
            $_FILES['file']['name'][$i]);
        $link->query("USE $loader->database_name;");
        $link->query("DROP TABLE IF EXISTS $loader->nameoffile;");
        $link->query(($loader->getData())[0]);
        $link->query(($loader->getData())[1]);
        echo( "Данные таблицы $loader->nameoffile успешно загружены!<br/>");
    }

}
else
{
    $errors = array_push($errors, ['file' => 'There are no files!']);
}

IF($errors)
{var_dump($errors);}
?>
<a href="./index.php" class="button">Назад к конвертеру</a>