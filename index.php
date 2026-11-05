<?php

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/conexion.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

function responder($datos, $estado = 200)
{
    http_response_code($estado);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($id !== null && !preg_match('/^[1-9][0-9]*$/', $id)) {
    responder(['mensaje' => 'ID inválido'], 400);
}

try {
    // OBTENER todos los bebés o uno por su ID.
    if ($metodo === 'GET') {
        if ($id !== null) {
            $consulta = $conexion->prepare('SELECT * FROM bebes WHERE id = ?');
            $consulta->execute([$id]);
            $bebe = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$bebe) {
                responder(['mensaje' => 'Bebé no encontrado'], 404);
            }

            responder($bebe);
        }

        $consulta = $conexion->query('SELECT * FROM bebes ORDER BY id');
        responder($consulta->fetchAll(PDO::FETCH_ASSOC));
    }

    if (!in_array($metodo, ['POST', 'PUT', 'DELETE'], true)) {
        header('Allow: GET, POST, PUT, DELETE');
        responder(['mensaje' => 'Método no permitido'], 405);
    }

    // Actualizar y borrar requieren un ID existente.
    if ($metodo === 'PUT' || $metodo === 'DELETE') {
        if ($id === null) {
            responder(['mensaje' => 'Agrega ?id=NUMERO a la dirección'], 400);
        }

        $consulta = $conexion->prepare('SELECT id FROM bebes WHERE id = ?');
        $consulta->execute([$id]);

        if (!$consulta->fetch()) {
            responder(['mensaje' => 'Bebé no encontrado'], 404);
        }
    }

    // BORRAR.
    if ($metodo === 'DELETE') {
        $consulta = $conexion->prepare('DELETE FROM bebes WHERE id = ?');
        $consulta->execute([$id]);

        responder(['mensaje' => 'Bebé eliminado correctamente']);
    }

    // Leer el JSON para crear o actualizar.
    $datos = json_decode(file_get_contents('php://input'));

    if (!is_object($datos)) {
        responder(['mensaje' => 'Envía un objeto JSON válido'], 400);
    }

    $campos = [
        'nombre',
        'genero',
        'fecha_nacimiento',
        'nombre_papa',
        'nombre_mama'
    ];

    foreach ($campos as $campo) {
        if (
            !isset($datos->$campo) ||
            !is_string($datos->$campo) ||
            trim($datos->$campo) === ''
        ) {
            responder(['mensaje' => "Completa el campo $campo"], 422);
        }

        $datos->$campo = trim($datos->$campo);

        if (strlen($datos->$campo) > 150) {
            responder(['mensaje' => "El campo $campo es demasiado largo"], 422);
        }
    }

    if (!in_array($datos->genero, ['masculino', 'femenino', 'otro'], true)) {
        responder(['mensaje' => 'Usa masculino, femenino u otro'], 422);
    }

    $fecha = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $datos->fecha_nacimiento
    );

    if (
        !$fecha ||
        $fecha->format('Y-m-d') !== $datos->fecha_nacimiento ||
        $fecha > new DateTimeImmutable('today')
    ) {
        responder(['mensaje' => 'Fecha de nacimiento inválida'], 422);
    }

    $valores = [
        $datos->nombre,
        $datos->genero,
        $datos->fecha_nacimiento,
        $datos->nombre_papa,
        $datos->nombre_mama
    ];

    // CREAR.
    if ($metodo === 'POST') {
        $consulta = $conexion->prepare(
            'INSERT INTO bebes
            (nombre, genero, fecha_nacimiento, nombre_papa, nombre_mama)
            VALUES (?, ?, ?, ?, ?)'
        );

        $consulta->execute($valores);

        responder([
            'mensaje' => 'Bebé creado correctamente',
            'id' => $conexion->lastInsertId()
        ], 201);
    }

    // ACTUALIZAR.
    $consulta = $conexion->prepare(
        'UPDATE bebes
         SET nombre = ?, genero = ?, fecha_nacimiento = ?,
             nombre_papa = ?, nombre_mama = ?
         WHERE id = ?'
    );

    $valores[] = $id;
    $consulta->execute($valores);

    responder(['mensaje' => 'Bebé actualizado correctamente']);

} catch (PDOException $error) {
    error_log($error->getMessage());
    responder(['mensaje' => 'Error al ejecutar la operación en MySQL'], 500);
}