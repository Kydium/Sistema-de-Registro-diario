<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'servicios_db');   // nombre de tu base de datos en Hostinger
define('DB_USER', 'tu_usuario');     // usuario MySQL de Hostinger
define('DB_PASS', 'tu_contraseña'); // contraseña MySQL de Hostinger
define('DB_CHARSET', 'utf8mb4');

// Tiempo de sesión en segundos (8 horas)
define('SESSION_LIFETIME', 28800);

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
