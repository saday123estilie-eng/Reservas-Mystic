<?php
// cancelar_reserva.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/conexion.php';

$idReserva = isset($_POST['id_reserva']) ? (int)$_POST['id_reserva'] : 0;

if ($idReserva <= 0) {
    die('ID de reserva inválido.');
}

try {
    /** @var PDO $pdo */
    $pdo = conexion::getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->beginTransaction();

    // Bloquear la reserva para evitar condiciones de carrera
    $stmt = $pdo->prepare("SELECT * FROM reservas WHERE id_reserva = :id FOR UPDATE");
    $stmt->execute([':id' => $idReserva]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reserva) {
        throw new Exception('Reserva no encontrada.');
    }

    if ($reserva['estado'] === 'CANCELADA') {
        // Ya está cancelada, no hacemos nada más
        $pdo->rollBack();
        header('Location: ../Vista/editar_reserva.php?id_reserva=' . $idReserva . '&msg=ya_cancelada');
        exit;
    }

    // Datos "antes" para auditoría
    $antes = [
        'estado'            => $reserva['estado'],
        'tipo_alojamiento'  => $reserva['tipo_alojamiento'],
        'numero_alojamiento'=> $reserva['numero_alojamiento'],
        'saldo'             => (int)$reserva['saldo'],
    ];

    // Actualizar reserva: cancelar y liberar alojamiento + saldo 0
    $stmtUp = $pdo->prepare("
        UPDATE reservas
        SET estado = 'CANCELADA',
            tipo_alojamiento   = NULL,
            numero_alojamiento = NULL,
            saldo              = 0,
            actualizado_en     = NOW()
        WHERE id_reserva = :id
    ");
    $stmtUp->execute([':id' => $idReserva]);

    $despues = [
        'estado'            => 'CANCELADA',
        'tipo_alojamiento'  => null,
        'numero_alojamiento'=> null,
        'saldo'             => 0,
    ];

    $jsonCambios = json_encode(
        [
            'antes'  => $antes,
            'despues'=> $despues,
            'extras' => [
                'motivo' => 'cancelacion_desde_editar_reserva'
            ],
        ],
        JSON_UNESCAPED_UNICODE
    );

    // Insertar en auditoría
    $stmtAud = $pdo->prepare("
        INSERT INTO reserva_auditoria (id_reserva, usuario_id, usuario_rol, accion, cambios, fecha)
        VALUES (:id_reserva, :usuario_id, :usuario_rol, 'CANCELACION', :cambios, NOW())
    ");
    $stmtAud->execute([
        ':id_reserva' => $idReserva,
        ':usuario_id' => $_SESSION['id_usuario'] ?? null,
        ':usuario_rol'=> $_SESSION['rol']        ?? 'DESCONOCIDO',
        ':cambios'    => $jsonCambios,
    ]);

    $pdo->commit();

    header('Location: ../Vista/editar_reserva.php?id_reserva=' . $idReserva . '&ok=cancelada');
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die('Error al cancelar la reserva: ' . htmlspecialchars($e->getMessage()));
}
