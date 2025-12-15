<?php
// ReservaDAO.php
declare(strict_types=1);

use PDO;
use PDOException;

require_once __DIR__ . '/../config/conexion.php';

class ReservaDAO
{
    /**
     * $cliente: array asociativo con claves:
     *  nombre, cedula, whatsapp, correo, ciudad, cumple, edad, estrato, profesion
     */
    private function asegurarCliente(PDO $con, array $cliente): int
    {
        // 1) Buscar por cédula
        $sqlSel = "SELECT id_cliente FROM clientes WHERE cedula = ?";
        $stmt   = $con->prepare($sqlSel);
        $stmt->execute([$cliente['cedula']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return (int)$row['id_cliente'];
        }

        // 2) Insertar nuevo cliente
        $sqlIns = "INSERT INTO clientes 
            (nombre, cedula, whatsapp, correo, ciudad, cumple, edad, estrato, profesion)
            VALUES (?,?,?,?,?,?,?,?,?)";

        $stmt = $con->prepare($sqlIns);

        $stmt->bindValue(1, $cliente['nombre']      ?? null);
        $stmt->bindValue(2, $cliente['cedula']      ?? null);
        $stmt->bindValue(3, $cliente['whatsapp']    ?? null);
        $stmt->bindValue(4, $cliente['correo']      ?? null);
        $stmt->bindValue(5, $cliente['ciudad']      ?? null);
        $stmt->bindValue(6, $cliente['cumple']      ?? null);

        if (isset($cliente['edad']) && $cliente['edad'] !== null && $cliente['edad'] !== '') {
            $stmt->bindValue(7, (int)$cliente['edad'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue(7, null, PDO::PARAM_NULL);
        }

        $stmt->bindValue(8, $cliente['estrato']    ?? null);
        $stmt->bindValue(9, $cliente['profesion']  ?? null);

        $stmt->execute();

        $id = (int)$con->lastInsertId();
        if ($id <= 0) {
            throw new Exception("No se pudo obtener id_cliente generado");
        }
        return $id;
    }

    /**
     * $reserva: array asociativo con claves:
     *  idCliente, idPlan, codigo, planNombre, fechaIngreso, hora,
     *  noches, adultos, menores, precioUnit, valorBase, valorExtras,
     *  descuentoPorc, descuentoValor, valorTotal, saldo,
     *  parqueadero, medio, agente, observaciones,
     *  pagoTotal, pagoEfectivo, pagoTransferencia, pagoDatafono,
     *  idUsuario, estado
     *
     * Retorna id_reserva (int)
     */
    private function insertarReserva(PDO $con, array &$reserva): int
    {
        $sql = "INSERT INTO reservas (
                    id_cliente, id_plan, codigo, plan_nombre, fecha_ingreso, hora,
                    noches, adultos, menores, precio_unit, valor_base, valor_extras,
                    descuento_porcentaje, descuento_valor, valor_total, saldo,
                    parqueadero, medio, agente, observaciones,
                    pago_total, pago_efectivo, pago_transferencia, pago_datafono,
                    id_usuario, estado
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $con->prepare($sql);

        $i = 1;

        // id_cliente (obligatorio, viene de asegurarCliente)
        $stmt->bindValue($i++, (int)$reserva['idCliente'], PDO::PARAM_INT);

        // id_plan
        if (isset($reserva['idPlan']) && $reserva['idPlan'] !== null && $reserva['idPlan'] !== '') {
            $stmt->bindValue($i++, (int)$reserva['idPlan'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue($i++, null, PDO::PARAM_NULL);
        }

        $stmt->bindValue($i++, $reserva['codigo']       ?? null);
        $stmt->bindValue($i++, $reserva['planNombre']   ?? null);
        $stmt->bindValue($i++, $reserva['fechaIngreso'] ?? null);
        $stmt->bindValue($i++, $reserva['hora']         ?? null);

        $stmt->bindValue($i++, (int)($reserva['noches']   ?? 1), PDO::PARAM_INT);
        $stmt->bindValue($i++, (int)($reserva['adultos']  ?? 2), PDO::PARAM_INT);
        $stmt->bindValue($i++, (int)($reserva['menores']  ?? 0), PDO::PARAM_INT);

        $stmt->bindValue($i++, (int)($reserva['precioUnit']      ?? 0), PDO::PARAM_INT);
        $stmt->bindValue($i++, (int)($reserva['valorBase']       ?? 0), PDO::PARAM_INT);
        $stmt->bindValue($i++, (int)($reserva['valorExtras']     ?? 0), PDO::PARAM_INT);

        $stmt->bindValue($i++, (int)($reserva['descuentoPorc']   ?? 0), PDO::PARAM_INT);
        $stmt->bindValue($i++, (int)($reserva['descuentoValor']  ?? 0), PDO::PARAM_INT);

        $stmt->bindValue($i++, (int)($reserva['valorTotal']      ?? 0), PDO::PARAM_INT);
        $stmt->bindValue($i++, (int)($reserva['saldo']           ?? 0), PDO::PARAM_INT);

        $stmt->bindValue($i++, $reserva['parqueadero']  ?? null);
        $stmt->bindValue($i++, $reserva['medio']        ?? null);
        $stmt->bindValue($i++, $reserva['agente']       ?? null);
        $stmt->bindValue($i++, $reserva['observaciones']?? null);

        $stmt->bindValue($i++, (int)($reserva['pagoTotal']        ?? 0), PDO::PARAM_INT);
        $stmt->bindValue($i++, (int)($reserva['pagoEfectivo']     ?? 0), PDO::PARAM_INT);
        $stmt->bindValue($i++, (int)($reserva['pagoTransferencia']?? 0), PDO::PARAM_INT);
        $stmt->bindValue($i++, (int)($reserva['pagoDatafono']     ?? 0), PDO::PARAM_INT);

        // id_usuario
        if (isset($reserva['idUsuario']) && $reserva['idUsuario'] !== null && $reserva['idUsuario'] !== '') {
            $stmt->bindValue($i++, (int)$reserva['idUsuario'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue($i++, null, PDO::PARAM_NULL);
        }

        $estado = $reserva['estado'] ?? 'RESERVADA';
        $stmt->bindValue($i++, $estado);

        $stmt->execute();

        $id = (int)$con->lastInsertId();
        if ($id <= 0) {
            throw new Exception("No se pudo obtener id_reserva generado");
        }

        // opcional: lo guardamos en el array
        $reserva['idReserva'] = $id;

        return $id;
    }

    /**
     * $pagos: array de arrays con claves:
     *  num_pago, fecha, metodo, valor, nota
     */
    private function insertarPagos(PDO $con, int $idReserva, ?array $pagos): void
    {
        if (empty($pagos)) {
            return;
        }

        $sql = "INSERT INTO pagos (id_reserva, num_pago, fecha, metodo, valor, nota)
                VALUES (?,?,?,?,?,?)";
        $stmt = $con->prepare($sql);

        foreach ($pagos as $pago) {
            if (!$pago) continue;
            $valor = (int)($pago['valor'] ?? 0);
            if ($valor <= 0) continue; // ignorar pagos en 0

            $i = 1;
            $stmt->bindValue($i++, $idReserva, PDO::PARAM_INT);
            $stmt->bindValue($i++, (int)($pago['num_pago'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue($i++, $pago['fecha']  ?? null);
            $stmt->bindValue($i++, $pago['metodo'] ?? null);
            $stmt->bindValue($i++, $valor, PDO::PARAM_INT);
            $stmt->bindValue($i++, $pago['nota']   ?? null);

            $stmt->execute();
        }
    }

    /**
     * Método público: hace TODO dentro de una transacción
     *
     * $cliente: array (ver asegurarCliente)
     * $reserva: array (ver insertarReserva)
     * $pagos:   array de pagos (ver insertarPagos)
     */
    public function registrarReservaCompleta(array $cliente, array &$reserva, ?array $pagos = null): bool
    {
        $con = null;
        try {
            $con = conexion::getConexion();
            $con->beginTransaction();

            // 1) Cliente
            $idCliente         = $this->asegurarCliente($con, $cliente);
            $reserva['idCliente'] = $idCliente;

            // 2) Reserva
            $idReserva         = $this->insertarReserva($con, $reserva);

            // 3) Pagos
            $this->insertarPagos($con, $idReserva, $pagos);

            $con->commit();
            return true;

        } catch (Exception $e) {
            if ($con) {
                try { $con->rollBack(); } catch (Exception $ex) {}
            }
            // Mientras desarrollas:
            error_log("Error registrarReservaCompleta: " . $e->getMessage());
            // Si quieres ver en pantalla:
            // echo "<pre>Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
            return false;
        }
    }
}
