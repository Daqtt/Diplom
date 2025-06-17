<?php
/**
 * Возвращает подключение к MySQL через mysqli
 */
function get_db_connection() {
    $host     = 'localhost';
    $user     = 'root';            // ваш MySQL-юзер
    $password = '';   // ваш пароль
    $database = 'saitbd';   // имя БД (создайте её перед импортом database.sql)

    $conn = new mysqli($host, $user, $password, $database);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    // убедимся, что кодировка utf8mb4
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>
