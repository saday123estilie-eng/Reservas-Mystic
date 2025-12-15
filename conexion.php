<?php

class conexion
{
    private const HOST = '127.0.0.1';
    private const DB   = 'mystic_reservas';
    private const USER = 'root';  // tu usuario
    private const PASS = '';      // tu contraseña
    private const CHARSET = 'utf8mb4';

    public static function getConexion(): PDO
    {
        $dsn = 'mysql:host=' . self::HOST . ';dbname=' . self::DB . ';charset=' . self::CHARSET;

        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // lanza excepciones
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // fetch por defecto
            PDO::ATTR_EMULATE_PREPARES   => false,                    // usa prepares nativos
        ];

        return new PDO($dsn, self::USER, self::PASS, $opciones);
    }
}

// ======= PRUEBA RÁPIDA, similar a tu main =======
if (php_sapi_name() === 'cli') {
    // Si lo ejecutas desde consola: php Conexion.php
    try {
        $cn = Conexion::getConexion();
        echo "✅ Conexión EXITOSA a la base de datos mystic_reservas\n";
    } catch (PDOException $e) {
        echo "❌ Error al conectar a la base de datos:\n";
        echo $e->getMessage() . "\n";
    }
}
