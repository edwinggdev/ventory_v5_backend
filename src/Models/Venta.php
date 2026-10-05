<?php

require_once __DIR__ . '/BaseModel.php';

class Venta extends BaseModel {
    protected $table = 'venta';

    public function getAll() {
        $table = $this->prefixedTable('venta');
        $personaTable = $this->prefixedTable('persona');
        $salexprodTable = $this->prefixedTable('salexprod');
        $usuarioTable = $this->prefixedTable('usuario');
        $stmt = $this->db->query("
            SELECT
                d.id,
                d.nroventa AS numero,
                DATE(d.fecha) AS fecha,
                d.hora,
                CASE
                    WHEN d.est = '1' THEN 'pendiente'
                    WHEN d.est = 'A' THEN 'anulado'
                    WHEN d.est = 'P' THEN 'pago'
                    ELSE d.est
                END AS estado,
                d.cufe,
                d.usu,
                COALESCE(u.nombre, '') AS usu_nombre,
                d.idpersona AS cliente_id,
                d.idsalida,
                d.idpagometodo,
                CONCAT(TRIM(COALESCE(p.nom, '')), ' ', TRIM(COALESCE(p.ape, ''))) AS cliente_nombre,
                COALESCE((
                    SELECT SUM(sx.cantidad * sx.precio)
                    FROM {$salexprodTable} sx
                    WHERE sx.idsalida = d.idsalida
                ), 0) AS total
            FROM {$table} d
            LEFT JOIN {$personaTable} p ON d.idpersona = p.id
            LEFT JOIN {$usuarioTable} u ON d.usu = u.id
            ORDER BY d.id DESC
        ");
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $table = $this->prefixedTable('venta');
        $personaTable = $this->prefixedTable('persona');
        $salidaTable = $this->prefixedTable('salida');
        $stmt = $this->db->prepare("
            SELECT
                d.*,
                d.nroventa AS numero,
                d.idpersona AS cliente_id,
                CONCAT(TRIM(COALESCE(p.nom, '')), ' ', TRIM(COALESCE(p.ape, ''))) AS cliente_nombre,
                s.fecha AS salida_fecha,
                s.hora AS salida_hora,
                s.formapago AS salida_formapago
            FROM {$table} d
            LEFT JOIN {$personaTable} p ON d.idpersona = p.id
            LEFT JOIN {$salidaTable} s ON d.idsalida = s.id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $venta = $stmt->fetch();

        if (!$venta) {
            return null;
        }

        $venta['lineas'] = $this->getLineas((int) $venta['idsalida']);

        return $venta;
    }

    public function getLineas($idsalida) {
        $salexprodTable = $this->prefixedTable('salexprod');
        $productoTable = $this->prefixedTable('producto');
        $stmt = $this->db->prepare("
            SELECT
                s.id,
                s.idprod,
                s.cantidad,
                s.precio,
                s.preciodcto,
                COALESCE(pr.des, '') AS des
            FROM {$salexprodTable} s
            LEFT JOIN {$productoTable} pr ON pr.id = s.idprod
            WHERE s.idsalida = ?
            ORDER BY s.id ASC
        ");
        $stmt->execute([$idsalida]);
        return $stmt->fetchAll();
    }

    public function generateCufe($id) {
        $venta = $this->getById($id);
        if (!$venta) {
            return ['error' => 'Venta no encontrada.', 'success' => false];
        }

        if (!empty($venta['cufe'])) {
            return ['error' => 'La venta ya tiene CUFE.', 'success' => false, 'cufe' => $venta['cufe']];
        }

        $total = 0;
        foreach ($venta['lineas'] as $linea) {
            $total += ((float) $linea['cantidad']) * ((float) $linea['precio']);
        }

        $data = [
            $venta['nroventa'],
            $venta['fecha'],
            $venta['idpersona'],
            $venta['idsalida'],
            $venta['idpagoforma'],
            number_format($total, 2, '.', '')
        ];

        $cufe = strtoupper(hash('sha384', implode('|', $data)));

        $stmt = $this->db->prepare("UPDATE {$this->prefixedTable('venta')} SET cufe = ? WHERE id = ?");
        $stmt->execute([$cufe, $id]);

        return ['success' => true, 'cufe' => $cufe];
    }

    public function create($data) {
        $clienteId = (int) ($data['cliente_id'] ?? $data['idpersona'] ?? 0);
        if ($clienteId <= 0) {
            return ['error' => 'Debe seleccionar un cliente.', 'success' => false];
        }

        $lineas = isset($data['lineas']) && is_array($data['lineas']) ? $data['lineas'] : [];
        if (count($lineas) === 0) {
            return ['error' => 'Debe enviar al menos una linea de producto.', 'success' => false];
        }

        $numero = isset($data['numero']) ? trim((string) $data['numero']) : '';
        if ($numero === '') {
            $numero = (new DateTime('now'))->format('YmdHis');
        }

        $now = new DateTime('now');
        $fechaDate = $now->format('Y-m-d');
        $fechaDateTime = $now->format('Y-m-d H:i:s');
        $hora = $now->format('H:i:s');

        $usuInt = (int) ($data['usu'] ?? 1);
        $usuStr = (string) $usuInt;
        $idPagoForma = (int) ($data['idpagoforma'] ?? $data['formapago'] ?? 1);
        $estadoDoc = isset($data['est']) && $data['est'] !== '' ? (string) $data['est'] : 'A';
        $estadoSalida = isset($data['est']) && $data['est'] !== '' ? (string) $data['est'] : 'A';
        $direccion = isset($data['direccion']) ? trim((string) $data['direccion']) : '';
        $contacto = isset($data['contacto']) ? trim((string) $data['contacto']) : '';
        $observacion = isset($data['observacion']) ? trim((string) $data['observacion']) : '';
        $idResponsable = (int) ($data['idresponsable'] ?? $usuInt);
        $metodoPago = isset($data['idpagometodo']) && $data['idpagometodo'] !== null
            ? substr((string) $data['idpagometodo'], 0, 5)
            : '';
        $tipo = isset($data['tipo']) && $data['tipo'] !== '' ? (string) $data['tipo'] : 'venta';
        $nroFactura = isset($data['nroFactura']) && trim((string) $data['nroFactura']) !== ''
            ? trim((string) $data['nroFactura'])
            : $numero;

        if ($direccion === '' || $contacto === '') {
            $personaTable = $this->prefixedTable('persona');
            $stmtPersona = $this->db->prepare("SELECT direc, con1, nom, ape FROM {$personaTable} WHERE id = ? LIMIT 1");
            $stmtPersona->execute([$clienteId]);
            $persona = $stmtPersona->fetch();

            if ($persona) {
                if ($direccion === '') {
                    $direccion = isset($persona['direc']) ? (string) $persona['direc'] : '';
                }
                if ($contacto === '') {
                    $contacto = isset($persona['con1']) ? (string) $persona['con1'] : '';
                }
            }
        }

        $recibio = isset($data['recibio']) ? trim((string) $data['recibio']) : '';
        if ($recibio === '') {
            $personaTable = $this->prefixedTable('persona');
            $stmtPersona = $this->db->prepare("SELECT nom, ape FROM {$personaTable} WHERE id = ? LIMIT 1");
            $stmtPersona->execute([$clienteId]);
            $persona = $stmtPersona->fetch();
            if ($persona) {
                $recibio = trim(((string) ($persona['nom'] ?? '')) . ' ' . ((string) ($persona['ape'] ?? '')));
            }
        }

        $tableVenta = $this->prefixedTable('venta');
        $tableSalida = $this->prefixedTable('salida');
        $tableSalexprod = $this->prefixedTable('salexprod');

        try {
            $this->db->beginTransaction();

            $stmtSalida = $this->db->prepare("
                INSERT INTO {$tableSalida}
                (nroFactura, idprov, fecha, hora, recibio, usu, est, formapago)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtSalida->execute([
                $nroFactura,
                $clienteId,
                $fechaDate,
                $hora,
                $recibio,
                $usuStr,
                $estadoSalida,
                $idPagoForma
            ]);
            $salidaId = (int) $this->db->lastInsertId();

            $stmtVenta = $this->db->prepare("
                INSERT INTO {$tableVenta}
                (nroventa, fecha, hora, idpersona, idsalida, idpagoforma, usu, est, direccion, contacto, observacion, idresponsable, idpagometodo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtVenta->execute([
                $numero,
                $fechaDateTime,
                $hora,
                $clienteId,
                $salidaId,
                $idPagoForma,
                $usuInt,
                $estadoDoc,
                $direccion,
                $contacto,
                $observacion,
                $idResponsable,
                $metodoPago
            ]);
            $ventaId = (int) $this->db->lastInsertId();

            $stmtDetalle = $this->db->prepare("
                INSERT INTO {$tableSalexprod}
                (idprod, idsalida, precio, cantidad, usu, recibio, tipo, unidad, preciodcto)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($lineas as $linea) {
                $productoId = (int) ($linea['producto_id'] ?? $linea['idprod'] ?? 0);
                $cantidad = (float) ($linea['cantidad'] ?? 0);
                $precio = (float) ($linea['valor'] ?? $linea['precio'] ?? 0);

                if ($productoId <= 0 || $cantidad <= 0) {
                    throw new InvalidArgumentException('Linea de producto invalida.');
                }

                $unidad = isset($linea['unidad']) ? $linea['unidad'] : 0;
                $tipoLinea = isset($linea['tipo']) && $linea['tipo'] !== '' ? (string) $linea['tipo'] : $tipo;
                $precioDcto = (float) ($linea['preciodcto'] ?? $precio);

                $stmtDetalle->execute([
                    $productoId,
                    $salidaId,
                    $precio,
                    $cantidad,
                    $usuInt,
                    $recibio,
                    $tipoLinea,
                    $unidad,
                    $precioDcto
                ]);
            }

            $this->db->commit();

            return [
                'success' => true,
                'id' => $ventaId,
                'idsalida' => $salidaId
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['error' => $e->getMessage(), 'success' => false];
        }
    }

    public function update($id, $data) {
        $mapped = [];

        if (isset($data['numero'])) {
            $mapped['nroventa'] = $data['numero'];
        }
        if (isset($data['cliente_id'])) {
            $mapped['idpersona'] = $data['cliente_id'];
        }
        if (isset($data['idpagoforma']) && $data['idpagoforma'] !== '' && $data['idpagoforma'] !== null) {
            $mapped['idpagoforma'] = (int) $data['idpagoforma'];
        }
        if (isset($data['idpagometodo']) && $data['idpagometodo'] !== null) {
            $mapped['idpagometodo'] = substr((string) $data['idpagometodo'], 0, 5);
        }
        if (isset($data['estado'])) {
            $estado = (string) $data['estado'];
            $map = [
                '1' => '1', 'pendiente' => '1', 'activo' => '1',
                'A' => 'A', 'anulado' => 'A', 'inactivo' => 'A',
                'P' => 'P', 'pago' => 'P', 'pagado' => 'P',
            ];
            $mapped['est'] = $map[$estado] ?? $estado;
        }

        if (count($mapped) === 0) {
            return ['success' => true];
        }

        return parent::update($id, $mapped);
    }

    public function updateLinea($id, $data) {
        $mapped = [];

        if (isset($data['cantidad'])) {
            $mapped['cantidad'] = $data['cantidad'];
        }
        if (isset($data['precio'])) {
            $mapped['precio'] = $data['precio'];
        }

        if (count($mapped) === 0) {
            return ['success' => true];
        }

        $table = $this->prefixedTable('salexprod');
        $sets = implode(' = ?, ', array_keys($mapped)) . ' = ?';
        $values = array_values($mapped);
        $values[] = $id;

        $stmt = $this->db->prepare("UPDATE {$table} SET {$sets} WHERE id = ?");
        try {
            $stmt->execute($values);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['error' => $e->getMessage(), 'success' => false];
        }
    }

    public function addLinea($ventaId, $data) {
        $venta = $this->getById($ventaId);
        if (!$venta) {
            return ['error' => 'Venta no encontrada.', 'success' => false];
        }

        $productoId = (int) ($data['producto_id'] ?? $data['idprod'] ?? 0);
        $cantidad = (float) ($data['cantidad'] ?? 0);
        $precio = (float) ($data['valor'] ?? $data['precio'] ?? 0);

        if ($productoId <= 0 || $cantidad <= 0) {
            return ['error' => 'Producto o cantidad inválidos.', 'success' => false];
        }
        if ($precio < 0) {
            return ['error' => 'Precio inválido.', 'success' => false];
        }

        $salidaTable = $this->prefixedTable('salida');
        $stmtSalida = $this->db->prepare("SELECT recibio, usu FROM {$salidaTable} WHERE id = ?");
        $stmtSalida->execute([(int) $venta['idsalida']]);
        $salida = $stmtSalida->fetch();

        $usuInt = (int) ($venta['usu'] ?? 1);
        $recibio = ($salida && isset($salida['recibio'])) ? (string) $salida['recibio'] : '';
        $tipoLinea = isset($data['tipo']) && $data['tipo'] !== '' ? (string) $data['tipo'] : 'venta';
        $unidad = isset($data['unidad']) ? $data['unidad'] : 0;
        $precioDcto = (float) ($data['preciodcto'] ?? $precio);

        $table = $this->prefixedTable('salexprod');
        $stmt = $this->db->prepare("
            INSERT INTO {$table}
            (idprod, idsalida, precio, cantidad, usu, recibio, tipo, unidad, preciodcto)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        try {
            $stmt->execute([
                $productoId,
                (int) $venta['idsalida'],
                $precio,
                $cantidad,
                $usuInt,
                $recibio,
                $tipoLinea,
                $unidad,
                $precioDcto
            ]);
            return ['success' => true, 'id' => $this->db->lastInsertId()];
        } catch (PDOException $e) {
            return ['error' => $e->getMessage(), 'success' => false];
        }
    }
}
