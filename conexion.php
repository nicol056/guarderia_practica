<?php

$host = '127.0.0.1';
$base = 'guarderia';
$usuario = 'root';
$clave = '';

try {
    $conexion = new PDO(
        "mysql:host=$host;dbname=$base;charset=utf8mb4",
        $usuario,
        $clave
    );

    $conexion->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );
} catch (PDOException $error) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'mensaje' => 'Error al conectar con la base de datos'
    ]);

    exit;
}