<?php
require_once __DIR__ . '/../config/conexion.php';

class Cliente
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = conexion::getConexion();
    }

    // Buscar cliente por cédula
    public function findByCedula($cedula)
    {
        $sql = "SELECT * FROM clientes WHERE cedula = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cedula]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Crear cliente
    public function create($data)
    {
        $sql = "INSERT INTO clientes 
        (nombre, cedula, whatsapp, edad, cumple, ciudad, correo, medio, estrato, profesion)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['nombre'],
            $data['cedula'],
            $data['whatsapp'],
            $data['edad'],
            $data['cumple'],
            $data['ciudad'],
            $data['correo'],
            $data['medio'],
            $data['estrato'],
            $data['profesion']
        ]);

        return $this->pdo->lastInsertId();
    }
}
?>
