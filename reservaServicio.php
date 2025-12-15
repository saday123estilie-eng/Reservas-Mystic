<?php
require_once __DIR__ . '/../config/conexion.php';

class ReservaServicio
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = conexion::getConexion();
    }

    public function create($data)
    {
        $sql = "INSERT INTO reservas_servicios 
                (id_reserva, id_servicio, cantidad, incluidas, precio_unit_aplicado, total_linea)
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['id_reserva'],
            $data['id_servicio'],
            $data['cantidad'],
            $data['incluidas'],
            $data['precio_unit_aplicado'],
            $data['total_linea']
        ]);
    }
}
?>
