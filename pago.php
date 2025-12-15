<?php
require_once __DIR__ . '/../config/conexion.php';

class Pago
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = conexion::getConexion();
    }

    public function create($data)
    {
        $sql = "INSERT INTO pagos (id_reserva, num_pago, fecha, metodo, valor)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['id_reserva'],
            $data['num_pago'],
            $data['fecha'],
            $data['metodo'],
            $data['valor']
        ]);
    }
}
?>
