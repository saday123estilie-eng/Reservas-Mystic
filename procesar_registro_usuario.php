<?php
// Controlador/procesar_registro_usuario.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Solo ADMIN puede registrar usuarios
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: ../vista/login.php");
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../vista/crearusuario.php");
    exit;
}

require_once __DIR__ . '/../config/conexion.php';

$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$email  = isset($_POST['email'])  ? trim($_POST['email'])  : '';
$clave  = isset($_POST['clave'])  ? $_POST['clave']        : '';
$clave2 = isset($_POST['clave2']) ? $_POST['clave2']       : '';
$rol    = isset($_POST['rol'])    ? strtoupper(trim($_POST['rol'])) : '';

$errores = [];

// ===== Validaciones básicas =====
if ($nombre === '') {
    $errores[] = 'El nombre es obligatorio.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'Debes ingresar un correo válido.';
}

if (strlen($clave) < 6) {
    $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
}

if ($clave !== $clave2) {
    $errores[] = 'Las contraseñas no coinciden.';
}

// Roles permitidos según ENUM de la tabla usuarios
$rolesPermitidos = ['ASESOR', 'ADMIN', 'RECEPCION', 'OTRO'];
if (!in_array($rol, $rolesPermitidos, true)) {
    $errores[] = 'El rol seleccionado no es válido.';
}

// Si hay errores de validación, devolver al formulario
if (!empty($errores)) {
    $_SESSION['msg_err'] = implode(' ', $errores);
    header("Location: ../vista/crearusuario.php");
    exit;
}

try {
    /** @var PDO $pdo */
    $pdo = conexion::getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ===== Verificar si el correo ya existe =====
    $sqlCheck = "SELECT id_usuario FROM usuarios WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($sqlCheck);
    $stmt->execute([':email' => $email]);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['msg_err'] = 'Ya existe un usuario registrado con ese correo.';
        header("Location: ../vista/crearusuario.php");
        exit;
    }

    // ===== Insertar nuevo usuario =====
    $hash = password_hash($clave, PASSWORD_DEFAULT);

    // Coincide con: id_usuario, nombre, email, password_hash, rol, creado_en
    $sqlInsert = "
        INSERT INTO usuarios (nombre, email, password_hash, rol)
        VALUES (:nombre, :email, :password_hash, :rol)
    ";

    $stmt = $pdo->prepare($sqlInsert);
    $stmt->execute([
        ':nombre'        => $nombre,
        ':email'         => $email,
        ':password_hash' => $hash,
        ':rol'           => $rol,
    ]);

    $_SESSION['msg_ok'] = 'Usuario registrado correctamente.';
    // Si prefieres enviarlo al listado de usuarios, cambia la ruta:
    // header("Location: ../vista/usuarios.php");
    header("Location: ../vista/crearusuario.php");
    exit;

} catch (Exception $e) {
    // Para producción, no mostrar el detalle del error al usuario
    $_SESSION['msg_err'] = 'Ocurrió un error al registrar el usuario. Intenta de nuevo.';
    // Para depurar:
    // error_log('Error registro usuario: ' . $e->getMessage());
    header("Location: ../vista/crearusuario.php");
    exit;
}
