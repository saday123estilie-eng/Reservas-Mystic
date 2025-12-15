<?php
require_once __DIR__ . '/../config/conexion.php';

class Reserva
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = conexion::getConexion();
    }

    public function create($data)
    {
        $sql = "INSERT INTO reservas
        (
            id_cliente,
            id_plan,
            codigo,
            plan_nombre,
            fecha_ingreso,
            hora,
            noches,
            adultos,
            menores,
            precio_unit,
            valor_base,
            valor_extras,
            descuento_porcentaje,
            descuento_valor,
            valor_total,
            saldo,
            parqueadero,
            medio,
            agente,
            observaciones,
            pago_total,
            pago_efectivo,
            pago_transferencia,
            pago_datafono,
            id_usuario
        )
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            $data['id_cliente'],          // 1
            $data['id_plan'],             // 2
            $data['codigo'],              // 3
            $data['plan_nombre'],         // 4
            $data['fecha_ingreso'],       // 5
            $data['hora'],                // 6
            $data['noches'],              // 7
            $data['adultos'],             // 8
            $data['menores'],             // 9
            $data['precio_unit'],         // 10
            $data['valor_base'],          // 11
            $data['valor_extras'],        // 12
            $data['descuento_porcentaje'],// 13
            $data['descuento_valor'],     // 14
            $data['valor_total'],         // 15
            $data['saldo'],               // 16
            $data['parqueadero'],         // 17
            $data['medio'],               // 18
            $data['agente'],              // 19
            $data['observaciones'],       // 20
            $data['pago_total'],          // 21
            $data['pago_efectivo'],       // 22
            $data['pago_transferencia'],  // 23
            $data['pago_datafono'],       // 24
            $data['id_usuario']           // 25 👈 AHORA SÍ
        ]);

        return $this->pdo->lastInsertId();
    }
}
?>
