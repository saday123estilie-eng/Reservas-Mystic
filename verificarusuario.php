<?php
// procesar_registro_usuario.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/../config/conexion.php';

$nombre = trim($_POST['nombre'] ?? '');
$email  = trim($_POST['email'] ?? '');
$clave  = $_POST['clave']  ?? '';
$clave2 = $_POST['clave2'] ?? '';
$rol    = $_POST['rol']    ?? 'ASESOR';
$estado = $_POST['estado'] ?? 'ACTIVO';

if ($nombre === '' || $email === '' || $clave === '' || $clave2 === '') {
    $_SESSION['msg_err'] = "Todos los campos son obligatorios.";
    header("Location: registro_usuario.php");
    exit;
}

if ($clave !== $clave2) {
    $_SESSION['msg_err'] = "Las contraseñas no coinciden.";
    header("Location: registro_usuario.php");
    exit;
}

if (strlen($clave) < 6) {
    $_SESSION['msg_err'] = "La contraseña debe tener al menos 6 caracteres.";
    header("Location: registro_usuario.php");
    exit;
}

try {
    /** @var PDO $pdo */
    $pdo = conexion::getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Validar que no exista el correo
    $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        $_SESSION['msg_err'] = "Ya existe un usuario con ese correo.";
        header("Location: registro_usuario.php");
        exit;
    }

    $hash = password_hash($clave, PASSWORD_BCRYPT);

    $sql = "
        INSERT INTO usuarios (nombre, email, clave_hash, rol, estado)
        VALUES (:nombre, :email, :clave_hash, :rol, :estado)
    ";

    $stmtIns = $pdo->prepare($sql);
    $stmtIns->execute([
        ':nombre'     => $nombre,
        ':email'      => $email,
        ':clave_hash' => $hash,
        ':rol'        => $rol,
        ':estado'     => $estado,
    ]);

    $_SESSION['msg_ok'] = "Usuario registrado correctamente.";
    header("Location: registro_usuario.php");
    exit;

} catch (Exception $e) {
    $_SESSION['msg_err'] = "Error al registrar usuario: " . $e->getMessage();
    header("Location: registro_usuario.php");
    exit;
}
