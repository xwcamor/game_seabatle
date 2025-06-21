<?php
$db_host = 'localhost'; // o tu host de BD
$db_name = 'seabattle_db'; // nombre de tu BD
$db_user = 'root'; // tu usuario de BD
$db_pass = '123456'; // tu contraseña de BD

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("No se pudo conectar a la base de datos $db_name :" . $e->getMessage());
}
?>