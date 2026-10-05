<?php

require_once __DIR__ . '/BaseModel.php';

class Marca extends BaseModel {
    protected $table = 'marca';

    public function getAll() {
        $stmt = $this->db->query("SELECT id, des as nombre, nomen, usu FROM {$this->table} ORDER BY des");
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function create($data) {
        $data = $this->normalizeData($data);
        return parent::create($data);
    }

    public function update($id, $data) {
        $data = $this->normalizeData($data);
        return parent::update($id, $data);
    }

    private function normalizeData($data) {
        if (isset($data['nombre'])) {
            $data['des'] = $data['nombre'];
            unset($data['nombre']);
        }

        return $data;
    }
}

class Usuario extends BaseModel {
    protected $table = 'usuario';

    public function getAll() {
        $stmt = $this->db->query("SELECT id, usuario, nombre, estado, fecha, tipo FROM {$this->table} ORDER BY nombre");
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT id, usuario, nombre, estado, fecha, tipo FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function login($data) {
        $stmt = $this->db->prepare("
            SELECT id, usuario, nombre, estado, tipo 
            FROM {$this->table} 
            WHERE usuario = ? AND contrasena = ?
        ");
        $password = md5($data['password']);
        $stmt->execute([$data['email'], $password]);
        $user = $stmt->fetch();
        
        if ($user) {
            $token = bin2hex(random_bytes(32));
            return ['user' => $user, 'token' => $token, 'success' => true];
        }
        return ['error' => 'Credenciales inválidas', 'success' => false];
    }

    public function create($data) {
        if (isset($data['contrasena'])) {
            $data['contrasena'] = md5($data['contrasena']);
        }
        return parent::create($data);
    }

    public function update($id, $data) {
        if (isset($data['contrasena'])) {
            $data['contrasena'] = md5($data['contrasena']);
        }
        return parent::update($id, $data);
    }
}

class Documento extends BaseModel {
    protected $table = 'documento';

    public function getAll() {
        $table = $this->prefixedTable('documento');
        $stmt = $this->db->query("\n            SELECT\n                d.id,\n                'factura' AS tipo,\n                '' AS serie,\n                d.nrodocumento AS numero,\n                CASE\n                    WHEN d.est = 'A' THEN 'activo'\n                    WHEN d.est = 'I' THEN 'inactivo'\n                    ELSE d.est\n                END AS estado,\n                d.fecha,\n                d.hora,\n                d.idpersona AS cliente_id,\n                d.idsalida\n            FROM {$table} d\n            ORDER BY d.id DESC\n        ");
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $table = $this->prefixedTable('documento');
        $stmt = $this->db->prepare("\n            SELECT\n                d.*,\n                d.nrodocumento AS numero,\n                d.idpersona AS cliente_id\n            FROM {$table} d\n            WHERE d.id = ?\n        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
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

        $tableDocumento = $this->prefixedTable('documento');
        $tableSalida = $this->prefixedTable('salida');
        $tableSalexprod = $this->prefixedTable('salexprod');

        try {
            $this->db->beginTransaction();

            $stmtSalida = $this->db->prepare("\n                INSERT INTO {$tableSalida}\n                (nroFactura, idprov, fecha, hora, recibio, usu, est, formapago)\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?)\n            ");
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

            $stmtDocumento = $this->db->prepare("\n                INSERT INTO {$tableDocumento}\n                (nrodocumento, fecha, hora, idpersona, idsalida, idpagoforma, usu, est, direccion, contacto, observacion, idresponsable)\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n            ");
            $stmtDocumento->execute([
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
                $idResponsable
            ]);
            $documentoId = (int) $this->db->lastInsertId();

            $stmtDetalle = $this->db->prepare("\n                INSERT INTO {$tableSalexprod}\n                (idprod, idsalida, precio, cantidad, usu, recibio, tipo, unidad, preciodcto)\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)\n            ");

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
                'id' => $documentoId,
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
            $mapped['nrodocumento'] = $data['numero'];
        }
        if (isset($data['cliente_id'])) {
            $mapped['idpersona'] = $data['cliente_id'];
        }
        if (isset($data['estado'])) {
            $estado = (string) $data['estado'];
            $mapped['est'] = $estado === 'activo' ? 'A' : ($estado === 'inactivo' ? 'I' : $estado);
        }

        if (count($mapped) === 0) {
            return ['success' => true];
        }

        return parent::update($id, $mapped);
    }
}

class Unidad extends BaseModel {
    protected $table = 'unidad';

    public function getAll() {
        $data = $this->loadUnitMeasuresFile();
        if (!isset($data['unitMeasures']) || !is_array($data['unitMeasures'])) {
            return [];
        }

        $units = [];
        foreach ($data['unitMeasures'] as $unit) {
            $code = isset($unit['code']) ? (string) $unit['code'] : '';
            $name = isset($unit['name']) ? $unit['name'] : '';
            $units[] = [
                'id' => $code,
                'des' => $name,
                'sigla' => $code,
                'est' => 1
            ];
        }

        return $units;
    }

    public function getById($id) {
        $data = $this->loadUnitMeasuresFile();
        if (!isset($data['unitMeasures']) || !is_array($data['unitMeasures'])) {
            return null;
        }

        $id = (string) $id;
        foreach ($data['unitMeasures'] as $unit) {
            $code = isset($unit['code']) ? (string) $unit['code'] : '';
            if ($code === $id) {
                $name = isset($unit['name']) ? $unit['name'] : '';
                return [
                    'id' => $code,
                    'des' => $name,
                    'sigla' => $code,
                    'est' => 1
                ];
            }
        }

        return null;
    }

    public function create($data) {
        $data = $this->normalizeData($data);
        return parent::create($data);
    }

    public function update($id, $data) {
        $data = $this->normalizeData($data);
        return parent::update($id, $data);
    }

    private function normalizeData($data) {
        if (isset($data['nombre'])) {
            $data['des'] = $data['nombre'];
            unset($data['nombre']);
        }

        return $data;
    }

    private function loadUnitMeasuresFile(): array {
        $path = __DIR__ . '/../Files/UnitMeasures.json';
        if (!is_file($path)) {
            return [];
        }

        $mtime = filemtime($path);
        if ($mtime === false) {
            $mtime = 0;
        }

        if (isset($_SESSION) && isset($_SESSION['unitMeasuresCache'])) {
            $cache = $_SESSION['unitMeasuresCache'];
            if (is_array($cache) && ($cache['mtime'] ?? -1) === $mtime && isset($cache['data'])) {
                return is_array($cache['data']) ? $cache['data'] : [];
            }
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        $data = json_decode($contents, true);
        $data = is_array($data) ? $data : [];

        if (isset($_SESSION)) {
            $_SESSION['unitMeasuresCache'] = [
                'mtime' => $mtime,
                'data' => $data
            ];
        }

        return $data;
    }
}

class Cotizacion extends BaseModel {
    protected $table = 'cotizacion';

    public function getAll() {
        $table = $this->prefixedTable('cotizacion');
        $personaTable = $this->prefixedTable('persona');
        $detalleTable = $this->prefixedTable('tcotxprod');
        $stmt = $this->db->query("
            SELECT
                c.id,
                c.nroCotizacion AS numero,
                c.idpersona AS cliente_id,
                c.fecha,
                c.vigencia,
                c.est AS estado,
                CONCAT(TRIM(COALESCE(p.nom, '')), ' ', TRIM(COALESCE(p.ape, ''))) AS cliente_nombre,
                COALESCE((
                    SELECT SUM(t.cantidad * t.precio)
                    FROM {$detalleTable} t
                    WHERE t.idCotizacion = c.id
                ), 0) AS total
            FROM {$table} c
            LEFT JOIN {$personaTable} p ON c.idpersona = p.id
            ORDER BY c.id DESC
        ");
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $table = $this->prefixedTable('cotizacion');
        $personaTable = $this->prefixedTable('persona');
        $detalleTable = $this->prefixedTable('tcotxprod');
        $productoTable = $this->prefixedTable('producto');
        $usuarioTable = $this->prefixedTable('usuario');
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                c.nroCotizacion AS numero,
                c.idpersona AS cliente_id,
                CONCAT(TRIM(COALESCE(p.nom, '')), ' ', TRIM(COALESCE(p.ape, ''))) AS cliente_nombre,
                COALESCE(u.nombre, '') AS usu_nombre
            FROM {$table} c
            LEFT JOIN {$personaTable} p ON c.idpersona = p.id
            LEFT JOIN {$usuarioTable} u ON c.usu = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $cotizacion = $stmt->fetch();

        if (!$cotizacion) {
            return null;
        }

        $stmtLineas = $this->db->prepare("
            SELECT
                t.id,
                t.idprod AS producto_id,
                t.cantidad,
                t.precio,
                COALESCE(pr.des, '') AS des,
                COALESCE(pr.codigo, '') AS codigo
            FROM {$detalleTable} t
            LEFT JOIN {$productoTable} pr ON pr.id = t.idprod
            WHERE t.idCotizacion = ?
            ORDER BY t.id ASC
        ");
        $stmtLineas->execute([$id]);
        $cotizacion['lineas'] = $stmtLineas->fetchAll();

        return $cotizacion;
    }

    public function create($data) {
        $clienteId = (int) ($data['cliente_id'] ?? $data['idpersona'] ?? 0);
        if ($clienteId <= 0) {
            return ['error' => 'Debe seleccionar un cliente.', 'success' => false];
        }

        $lineas = isset($data['lineas']) && is_array($data['lineas']) ? $data['lineas'] : [];
        if (count($lineas) === 0) {
            return ['error' => 'Debe agregar al menos un producto.', 'success' => false];
        }

        $vigencia = (int) ($data['vigencia'] ?? 0);
        if ($vigencia < 1 || $vigencia > 30) {
            return ['error' => 'La vigencia debe estar entre 1 y 30 días.', 'success' => false];
        }

        $fecha = isset($data['fecha']) && trim((string) $data['fecha']) !== ''
            ? substr(trim((string) $data['fecha']), 0, 10)
            : (new DateTime('now'))->format('Y-m-d');
        $hora = (new DateTime('now'))->format('H:i:s');
        $usuInt = (int) ($data['usu'] ?? 1);

        $table = $this->prefixedTable('cotizacion');
        $detalleTable = $this->prefixedTable('tcotxprod');

        try {
            $this->db->beginTransaction();

            $stmtMax = $this->db->query("SELECT COALESCE(MAX(CAST(nroCotizacion AS UNSIGNED)), 0) AS maximo FROM {$table}");
            $maximo = $stmtMax->fetch();
            $numero = (string) (((int) ($maximo['maximo'] ?? 0)) + 1);

            $stmt = $this->db->prepare("
                INSERT INTO {$table}
                (nroCotizacion, idDocumento, idpersona, fecha, hora, usu, recibio, nombre, contacto, idpagoforma, est, cabecera, vigencia, idresponsable, comision)
                VALUES (?, 0, ?, ?, ?, ?, 0, '', '', 0, 0, 0, ?, ?, 0)
            ");
            $stmt->execute([$numero, $clienteId, $fecha, $hora, $usuInt, $vigencia, $usuInt]);
            $cotizacionId = (int) $this->db->lastInsertId();

            $stmtDetalle = $this->db->prepare("
                INSERT INTO {$detalleTable}
                (idCotizacion, idprod, cantidad, cantidaddcto, precio, preciodcto, usu, recibio, tipo, unidad)
                VALUES (?, ?, ?, 0, ?, ?, ?, '', 'prod', '')
            ");

            foreach ($lineas as $linea) {
                $productoId = (int) ($linea['producto_id'] ?? $linea['idprod'] ?? 0);
                $cantidad = (int) ($linea['cantidad'] ?? 0);
                $precio = (int) ($linea['valor'] ?? $linea['precio'] ?? 0);

                if ($productoId <= 0 || $cantidad <= 0) {
                    throw new InvalidArgumentException('Linea de producto invalida.');
                }
                if ($precio < 0) {
                    throw new InvalidArgumentException('Precio inválido.');
                }

                $stmtDetalle->execute([$cotizacionId, $productoId, $cantidad, $precio, $precio, $usuInt]);
            }

            $this->db->commit();
            return ['success' => true, 'id' => $cotizacionId, 'numero' => $numero];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['error' => $e->getMessage(), 'success' => false];
        }
    }

    public function update($id, $data) {
        $mapped = [];

        if (isset($data['cliente_id'])) {
            $mapped['idpersona'] = (int) $data['cliente_id'];
        }
        if (isset($data['fecha']) && trim((string) $data['fecha']) !== '') {
            $mapped['fecha'] = substr(trim((string) $data['fecha']), 0, 10);
        }
        if (isset($data['vigencia'])) {
            $vigencia = (int) $data['vigencia'];
            if ($vigencia < 1 || $vigencia > 30) {
                return ['error' => 'La vigencia debe estar entre 1 y 30 días.', 'success' => false];
            }
            $mapped['vigencia'] = $vigencia;
        }

        $lineas = isset($data['lineas']) && is_array($data['lineas']) ? $data['lineas'] : null;

        try {
            $this->db->beginTransaction();

            if (count($mapped) > 0) {
                $sets = implode(' = ?, ', array_keys($mapped)) . ' = ?';
                $values = array_values($mapped);
                $values[] = $id;
                $table = $this->prefixedTable('cotizacion');
                $stmt = $this->db->prepare("UPDATE {$table} SET {$sets} WHERE id = ?");
                $stmt->execute($values);
            }

            if (is_array($lineas)) {
                $detalleTable = $this->prefixedTable('tcotxprod');
                $stmtDel = $this->db->prepare("DELETE FROM {$detalleTable} WHERE idCotizacion = ?");
                $stmtDel->execute([$id]);

                $stmtDetalle = $this->db->prepare("
                    INSERT INTO {$detalleTable}
                    (idCotizacion, idprod, cantidad, cantidaddcto, precio, preciodcto, usu, recibio, tipo, unidad)
                    VALUES (?, ?, ?, 0, ?, ?, 1, '', 'prod', '')
                ");
                foreach ($lineas as $linea) {
                    $productoId = (int) ($linea['producto_id'] ?? $linea['idprod'] ?? 0);
                    $cantidad = (int) ($linea['cantidad'] ?? 0);
                    $precio = (int) ($linea['valor'] ?? $linea['precio'] ?? 0);
                    if ($productoId <= 0 || $cantidad <= 0) {
                        throw new InvalidArgumentException('Linea de producto invalida.');
                    }
                    $stmtDetalle->execute([$id, $productoId, $cantidad, $precio, $precio]);
                }
            }

            $this->db->commit();
            return ['success' => true];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['error' => $e->getMessage(), 'success' => false];
        }
    }
}

class Salida extends BaseModel {
    protected $table = 'salida';

    public function getAll() {
        $table = $this->prefixedTable('salida');
        $personaTable = $this->prefixedTable('persona');
        $detalleTable = $this->prefixedTable('salexprod');
        $stmt = $this->db->query("
            SELECT
                s.id,
                s.nroFactura,
                s.fecha,
                s.hora,
                s.est AS estado,
                s.formapago,
                s.recibio,
                s.idprov AS cliente_id,
                CONCAT(TRIM(COALESCE(p.nom, '')), ' ', TRIM(COALESCE(p.ape, ''))) AS cliente_nombre,
                COALESCE((
                    SELECT SUM(sx.cantidad * sx.precio)
                    FROM {$detalleTable} sx
                    WHERE sx.idsalida = s.id
                ), 0) AS total
            FROM {$table} s
            LEFT JOIN {$personaTable} p ON s.idprov = p.id
            ORDER BY s.id DESC
        ");
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $table = $this->prefixedTable('salida');
        $personaTable = $this->prefixedTable('persona');
        $detalleTable = $this->prefixedTable('salexprod');
        $productoTable = $this->prefixedTable('producto');
        $stmt = $this->db->prepare("
            SELECT
                s.*,
                s.idprov AS cliente_id,
                CONCAT(TRIM(COALESCE(p.nom, '')), ' ', TRIM(COALESCE(p.ape, ''))) AS cliente_nombre
            FROM {$table} s
            LEFT JOIN {$personaTable} p ON s.idprov = p.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $salida = $stmt->fetch();

        if (!$salida) {
            return null;
        }

        $stmtLineas = $this->db->prepare("
            SELECT
                sx.id,
                sx.idprod AS producto_id,
                sx.cantidad,
                sx.precio,
                COALESCE(pr.des, '') AS des,
                COALESCE(pr.codigo, '') AS codigo
            FROM {$detalleTable} sx
            LEFT JOIN {$productoTable} pr ON pr.id = sx.idprod
            WHERE sx.idsalida = ?
            ORDER BY sx.id ASC
        ");
        $stmtLineas->execute([$id]);
        $salida['lineas'] = $stmtLineas->fetchAll();

        return $salida;
    }

    public function createCompleto($data) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} (cliente_id, fecha, total, tipo_documento, nro_documento, estado, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['cliente_id'],
                $data['fecha'],
                $data['total'],
                $data['tipo_documento'] ?? 'factura',
                $data['nro_documento'] ?? null,
                $data['estado'] ?? 'activo',
                $data['usuario_id']
            ]);
            $salidaId = $this->db->lastInsertId();

            if (isset($data['detalles'])) {
                $stmtDetalle = $this->db->prepare("
                    INSERT INTO salida_detalles (salida_id, producto_id, cantidad, precio)
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($data['detalles'] as $detalle) {
                    $stmtDetalle->execute([
                        $salidaId,
                        $detalle['producto_id'],
                        $detalle['cantidad'],
                        $detalle['precio']
                    ]);
                }
            }

            $this->db->commit();
            return ['id' => $salidaId, 'success' => true];
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['error' => $e->getMessage(), 'success' => false];
        }
    }

    public function getByFecha($fechaInicio, $fechaFin) {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE fecha BETWEEN ? AND ?
            ORDER BY fecha DESC
        ");
        $stmt->execute([$fechaInicio, $fechaFin]);
        return $stmt->fetchAll();
    }

    public function archivar($id) {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET estado = 'archivado' WHERE id = ?");
        $stmt->execute([$id]);
        return ['success' => true];
    }

    public function anular($id) {
        $stmt = $this->db->prepare("UPDATE {$this->prefixedTable('salida')} SET est = 'I' WHERE id = ?");
        $stmt->execute([$id]);
        return ['success' => true];
    }
}

class Pedido extends BaseModel {
    protected $table = 'pedidos';

    public function getWithDetails($id) {
        $stmt = $this->db->prepare("
            SELECT p.*, pd.producto_id, pd.cantidad,
                   pr.nombre as producto_nombre
            FROM {$this->table} p
            LEFT JOIN pedido_detalles pd ON p.id = pd.pedido_id
            LEFT JOIN productos pr ON pd.producto_id = pr.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public function createCompleto($data) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} (cliente_id, fecha, fecha_entrega, total, estado, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['cliente_id'],
                $data['fecha'],
                $data['fecha_entrega'] ?? null,
                $data['total'],
                $data['estado'] ?? 'pendiente',
                $data['usuario_id']
            ]);
            $pedidoId = $this->db->lastInsertId();

            if (isset($data['detalles'])) {
                $stmtDetalle = $this->db->prepare("
                    INSERT INTO pedido_detalles (pedido_id, producto_id, cantidad)
                    VALUES (?, ?, ?)
                ");
                foreach ($data['detalles'] as $detalle) {
                    $stmtDetalle->execute([
                        $pedidoId,
                        $detalle['producto_id'],
                        $detalle['cantidad']
                    ]);
                }
            }

            $this->db->commit();
            return ['id' => $pedidoId, 'success' => true];
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['error' => $e->getMessage(), 'success' => false];
        }
    }
}

class Entrada extends BaseModel {
    protected $table = 'entrada';

    public function getAll() {
        $table = $this->prefixedTable('entrada');
        $proveedorTable = $this->prefixedTable('proveedor');
        $detalleTable = $this->prefixedTable('entraxprod');
        $stmt = $this->db->query("
            SELECT
                e.id,
                e.nroEntrada,
                e.fecha,
                e.hora,
                e.est AS estado,
                e.recibio,
                e.idprov AS proveedor_id,
                TRIM(CONCAT(COALESCE(pr.nom1, ''), ' ', COALESCE(pr.nom2, ''), ' ', COALESCE(pr.ape1, ''), ' ', COALESCE(pr.ape2, ''))) AS proveedor_nombre,
                COALESCE((
                    SELECT SUM(ex.cantidad * ex.valorIngreso)
                    FROM {$detalleTable} ex
                    WHERE ex.idEntrada = e.id
                ), 0) AS total
            FROM {$table} e
            LEFT JOIN {$proveedorTable} pr ON e.idprov = pr.id
            ORDER BY e.id DESC
        ");
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $table = $this->prefixedTable('entrada');
        $proveedorTable = $this->prefixedTable('proveedor');
        $detalleTable = $this->prefixedTable('entraxprod');
        $productoTable = $this->prefixedTable('producto');
        $stmt = $this->db->prepare("
            SELECT
                e.*,
                e.idprov AS proveedor_id,
                TRIM(CONCAT(COALESCE(pr.nom1, ''), ' ', COALESCE(pr.nom2, ''), ' ', COALESCE(pr.ape1, ''), ' ', COALESCE(pr.ape2, ''))) AS proveedor_nombre
            FROM {$table} e
            LEFT JOIN {$proveedorTable} pr ON e.idprov = pr.id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $entrada = $stmt->fetch();

        if (!$entrada) {
            return null;
        }

        $stmtLineas = $this->db->prepare("
            SELECT
                ex.id,
                ex.idprod AS producto_id,
                ex.cantidad,
                ex.precio,
                ex.valorIngreso,
                ex.valorVenta,
                COALESCE(p.des, '') AS des,
                COALESCE(p.codigo, '') AS codigo
            FROM {$detalleTable} ex
            LEFT JOIN {$productoTable} p ON p.id = ex.idprod
            WHERE ex.idEntrada = ?
            ORDER BY ex.id ASC
        ");
        $stmtLineas->execute([$id]);
        $entrada['lineas'] = $stmtLineas->fetchAll();

        return $entrada;
    }

    public function createCompleto($data) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} (proveedor_id, fecha, total, tipo, usuario_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['proveedor_id'],
                $data['fecha'],
                $data['total'],
                $data['tipo'] ?? 'compra',
                $data['usuario_id']
            ]);
            $entradaId = $this->db->lastInsertId();

            if (isset($data['detalles'])) {
                $stmtDetalle = $this->db->prepare("
                    INSERT INTO entrada_detalles (entrada_id, producto_id, cantidad, precio)
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($data['detalles'] as $detalle) {
                    $stmtDetalle->execute([
                        $entradaId,
                        $detalle['producto_id'],
                        $detalle['cantidad'],
                        $detalle['precio']
                    ]);
                }
            }

            $this->db->commit();
            return ['id' => $entradaId, 'success' => true];
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['error' => $e->getMessage(), 'success' => false];
        }
    }
}

class Empresa extends BaseModel {
    protected $table = 'empresa';

    public function get() {
        $stmt = $this->db->query("SELECT * FROM {$this->table} LIMIT 1");
        $row = $stmt->fetch();
        if ($row) {
            $row['logo_url'] = '/ventory/sys/images/' . $this->tablePrefix . '/logo.png';
            $sysDir = realpath(__DIR__ . '/../../../../sys');
            $row['logo_exists'] = ($sysDir !== false && file_exists($sysDir . '/images/' . $this->tablePrefix . '/logo.png')) ? 1 : 0;
        }
        return $row;
    }

    public function updateData($data) {
        $sets = implode(' = ?, ', array_keys($data)) . ' = ?';
        $sql = "UPDATE {$this->table} SET {$sets} WHERE id = 1";
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute(array_values($data));
            return ['success' => true];
        } catch (PDOException $e) {
            return ['error' => $e->getMessage(), 'success' => false];
        }
    }

    public function saveLogo($file) {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['error' => 'No se recibió el archivo.', 'success' => false];
        }
        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            return ['error' => 'La imagen debe pesar máximo 2MB.', 'success' => false];
        }
        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            return ['error' => 'Archivo inválido.', 'success' => false];
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            return ['error' => 'El archivo no es una imagen válida.', 'success' => false];
        }
        $mime = $info['mime'] ?? '';
        $allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            return ['error' => 'Formato no soportado (PNG, JPG, GIF, WEBP).', 'success' => false];
        }

        $sysDir = realpath(__DIR__ . '/../../../../sys');
        if ($sysDir === false) {
            return ['error' => 'No se encontró la carpeta sys.', 'success' => false];
        }
        $dir = $sysDir . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $this->tablePrefix;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return ['error' => 'No se pudo crear la carpeta destino.', 'success' => false];
        }
        $target = $dir . DIRECTORY_SEPARATOR . 'logo.png';

        if ($mime === 'image/png') {
            if (!@move_uploaded_file($file['tmp_name'], $target)) {
                return ['error' => 'No se pudo guardar el archivo.', 'success' => false];
            }
        } elseif (function_exists('imagecreatefromstring') && function_exists('imagepng')) {
            $src = @imagecreatefromstring(@file_get_contents($file['tmp_name']));
            if ($src === false) {
                return ['error' => 'No se pudo procesar la imagen.', 'success' => false];
            }
            imagealphablending($src, false);
            imagesavealpha($src, true);
            $ok = @imagepng($src, $target);
            imagedestroy($src);
            if (!$ok) {
                return ['error' => 'No se pudo guardar el archivo.', 'success' => false];
            }
        } else {
            return ['error' => 'Suba un PNG (para JPG/GIF active GD en PHP).', 'success' => false];
        }

        return [
            'success' => true,
            'logo_url' => '/ventory/sys/images/' . $this->tablePrefix . '/logo.png'
        ];
    }
}

class Configuracion extends BaseModel {
    protected $table = 'config';

    public function getAllByReference() {
        $stmt = $this->db->query("SELECT id, e1, e2 FROM {$this->table} WHERE e1 <> '' ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    public function saveByReference($items) {
        if (!is_array($items)) {
            return ['error' => 'Invalid payload', 'success' => false];
        }

        try {
            $this->db->beginTransaction();

            $selectStmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE e1 = ? LIMIT 1");
            $updateStmt = $this->db->prepare("UPDATE {$this->table} SET e2 = ? WHERE id = ?");
            $insertStmt = $this->db->prepare(
                "INSERT INTO {$this->table} (usu, c1, c2, c3, c4, c5, c6, c7, e1, e2, e3, e4, e5, e6, e7, ganancia, precioVariante, selector)
                 VALUES (0, 0, 0, 0, 0, 0, 0, 0, ?, ?, '', '', '', '', '', 0, '0', '')"
            );

            foreach ($items as $item) {
                if (!is_array($item) || !isset($item['e1'])) {
                    continue;
                }

                $e1 = trim((string) $item['e1']);
                if ($e1 === '') {
                    continue;
                }

                $e2 = isset($item['e2']) ? trim((string) $item['e2']) : '';

                $selectStmt->execute([$e1]);
                $existing = $selectStmt->fetch();

                if ($existing && isset($existing['id'])) {
                    $updateStmt->execute([$e2, $existing['id']]);
                } else {
                    $insertStmt->execute([$e1, $e2]);
                }
            }

            $this->db->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['error' => $e->getMessage(), 'success' => false];
        }
    }

    private function getOptionsByE1($e1) {
        $stmt = $this->db->prepare(
            "SELECT e2, e3 FROM {$this->table} WHERE e1 = ? AND TRIM(COALESCE(e2, '')) <> '' ORDER BY CAST(e2 AS UNSIGNED), e2"
        );
        $stmt->execute([$e1]);
        $rows = $stmt->fetchAll();

        $options = [];
        foreach ($rows as $row) {
            $value = isset($row['e2']) ? trim((string) $row['e2']) : '';
            if ($value === '') {
                continue;
            }

            $label = isset($row['e3']) ? trim((string) $row['e3']) : '';
            if ($label === '') {
                $label = $value;
            }

            $options[] = [
                'id' => $value,
                'nombre' => $label
            ];
        }

        return $options;
    }

    public function getIdDocOptions() {
        return $this->getOptionsByE1('iddoc');
    }

    public function getIdTributoOptions() {
        return $this->getOptionsByE1('idtributo');
    }

    public function getIdOrganizacionOptions() {
        return $this->getOptionsByE1('idorganizacion');
    }

    public function getIdRetencionOptions() {
        return $this->getOptionsByE1('idretencion');
    }

    public function getPagoFormaOptions() {
        return $this->getOptionsByE1('pagoforma');
    }

    public function getPagoMetodoOptions() {
        return $this->getOptionsByE1('pagometodo');
    }

    public function getCufe() {
        $stmt = $this->db->query("SELECT id, e1, e2 FROM {$this->table} WHERE c1 = 3 ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    public function saveCufe($items) {
        if (!is_array($items)) {
            return ['error' => 'Invalid payload', 'success' => false];
        }

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("UPDATE {$this->table} SET e2 = ? WHERE id = ? AND c1 = 3");

            foreach ($items as $item) {
                if (!is_array($item) || !isset($item['id'])) {
                    continue;
                }
                $e2 = isset($item['e2']) ? (string) $item['e2'] : '';
                $stmt->execute([$e2, (int) $item['id']]);
            }

            $this->db->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['error' => $e->getMessage(), 'success' => false];
        }
    }

    public function fetchCufeToken() {
        $rows = $this->getCufe();

        $url = '';
        $params = [];
        foreach ($rows as $row) {
            $key = isset($row['e1']) ? trim((string) $row['e1']) : '';
            $value = isset($row['e2']) ? (string) $row['e2'] : '';
            if ($key === '') {
                continue;
            }
            if (strtolower($key) === 'url') {
                $url = trim($value);
            } else {
                $params[$key] = $value;
            }
        }

        if ($url === '') {
            return ['error' => 'URL CUFE no configurada (e1 = url, c1 = 3).', 'success' => false];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['error' => 'Error contactando CUFE: ' . $curlError, 'success' => false];
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Respuesta CUFE no válida.', 'success' => false, 'http_code' => $httpCode];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return ['error' => 'CUFE respondió HTTP ' . $httpCode, 'success' => false, 'response' => $decoded];
        }

        $decoded['success'] = true;
        return $decoded;
    }
}

class Cliente extends BaseModel {
    protected $table = 'persona';
    
    public function getAll() {
        $stmt = $this->db->query(" 
            SELECT 
                id, nit, dv, nom, ape, direc, con1, con2, usu, correo, estado,
                compania, nom_comercial, idorganization, idtributo, ididentificacion, idmunicipio,
                CONCAT(TRIM(COALESCE(nom, '')), ' ', TRIM(COALESCE(ape, ''))) as nombre
            FROM {$this->table}
            ORDER BY id DESC
        ");
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->db->prepare(" 
            SELECT 
                id, nit, dv, nom, ape, direc, con1, con2, usu, correo, estado,
                compania, nom_comercial, idorganization, idtributo, ididentificacion, idmunicipio,
                CONCAT(TRIM(COALESCE(nom, '')), ' ', TRIM(COALESCE(ape, ''))) as nombre
            FROM {$this->table}
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function create($data) {
        $data = $this->normalizeData($data);
        return parent::create($data);
    }

    public function update($id, $data) {
        $data = $this->normalizeData($data, true);
        return parent::update($id, $data);
    }

    public function search($query) {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE nom LIKE ? OR ape LIKE ? OR nit LIKE ?
        ");
        $stmt->execute(["%{$query}%", "%{$query}%", "%{$query}%"]);
        return $stmt->fetchAll();
    }

    private function normalizeData($data, $isUpdate = false) {
        if (isset($data['identificacion'])) {
            $data['nit'] = $data['identificacion'];
            unset($data['identificacion']);
        }
        if (isset($data['telefono'])) {
            $data['con1'] = $data['telefono'];
            unset($data['telefono']);
        }
        if (isset($data['email'])) {
            $data['correo'] = $data['email'];
            unset($data['email']);
        }
        if (isset($data['direccion'])) {
            $data['direc'] = $data['direccion'];
            unset($data['direccion']);
        }

        if (isset($data['nombre']) && (!isset($data['nom']) || $data['nom'] === '')) {
            $parts = preg_split('/\s+/', trim((string) $data['nombre']));
            $data['nom'] = $parts[0] ?? '';
            if (!isset($data['ape']) || $data['ape'] === '') {
                $data['ape'] = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
            }
        }

        // 'nombre' is a computed/read-only field in queries, not a column in persona table.
        unset($data['nombre']);

        unset($data['id']);

        if (!$isUpdate) {
            if (!isset($data['usu']) || $data['usu'] === '' || $data['usu'] === null) {
                $data['usu'] = 1;
            }
            if (!isset($data['estado']) || $data['estado'] === '') {
                $data['estado'] = 'A';
            }
            if (!isset($data['idorganization']) || $data['idorganization'] === '') {
                $data['idorganization'] = 0;
            }
            if (!isset($data['idtributo']) || $data['idtributo'] === '') {
                $data['idtributo'] = 0;
            }
            if (!isset($data['ididentificacion']) || $data['ididentificacion'] === '') {
                $data['ididentificacion'] = 0;
            }
            if (!isset($data['idmunicipio']) || $data['idmunicipio'] === '') {
                $data['idmunicipio'] = 0;
            }
        }

        return $data;
    }
}

class Proveedor extends BaseModel {
    protected $table = 'proveedor';

    private function selectList() {
        return "SELECT id, nit, nom1, nom2, ape1, ape2, direc, con1, con2, usu, estado,
            nit AS identificacion,
            TRIM(CONCAT(COALESCE(nom1, ''), ' ', COALESCE(nom2, ''), ' ', COALESCE(ape1, ''), ' ', COALESCE(ape2, ''))) AS nombre,
            con1 AS telefono,
            con2 AS email,
            direc AS direccion
            FROM {$this->table}";
    }

    public function getAll() {
        $stmt = $this->db->query($this->selectList() . " ORDER BY nom1, ape1");
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->db->prepare($this->selectList() . " WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function create($data) {
        $data = $this->normalizeData($data);
        return parent::create($data);
    }

    public function update($id, $data) {
        $data = $this->normalizeData($data, true);
        return parent::update($id, $data);
    }

    public function search($query) {
        $like = "%{$query}%";
        $stmt = $this->db->prepare($this->selectList() . " WHERE nit LIKE ? OR nom1 LIKE ? OR nom2 LIKE ? OR ape1 LIKE ? OR ape2 LIKE ? OR direc LIKE ? OR con1 LIKE ? OR con2 LIKE ? ORDER BY nom1, ape1");
        $stmt->execute([$like, $like, $like, $like, $like, $like, $like, $like]);
        return $stmt->fetchAll();
    }

    private function normalizeData($data, $isUpdate = false) {
        // Frontend aliases -> real 5proveedor columns
        if (isset($data['identificacion'])) {
            $data['nit'] = $data['identificacion'];
            unset($data['identificacion']);
        }
        if (isset($data['telefono'])) {
            $data['con1'] = $data['telefono'];
            unset($data['telefono']);
        }
        if (isset($data['email'])) {
            $data['con2'] = $data['email'];
            unset($data['email']);
        }
        if (isset($data['direccion'])) {
            $data['direc'] = $data['direccion'];
            unset($data['direccion']);
        }

        // Split "nombre" into nom1/nom2/ape1/ape2 when parts not given explicitly
        if (isset($data['nombre']) && trim((string) $data['nombre']) !== '') {
            $parts = array_values(array_filter(preg_split('/\s+/', trim((string) $data['nombre']))));
            if (!isset($data['nom1']) || $data['nom1'] === '') {
                $data['nom1'] = $parts[0] ?? '';
            }
            if (!isset($data['nom2']) || $data['nom2'] === '') {
                $data['nom2'] = $parts[1] ?? '';
            }
            if (!isset($data['ape1']) || $data['ape1'] === '') {
                $data['ape1'] = $parts[2] ?? '';
            }
            if ((!isset($data['ape2']) || $data['ape2'] === '') && count($parts) > 3) {
                $data['ape2'] = implode(' ', array_slice($parts, 3));
            }
        }
        unset($data['nombre']);

        unset($data['id']);
        unset($data['tipo']); // legacy persona discriminator, no such column in proveedor table

        if (!$isUpdate) {
            if (!isset($data['usu']) || $data['usu'] === '' || $data['usu'] === null) {
                $data['usu'] = 1;
            }
            if (!isset($data['estado']) || $data['estado'] === '') {
                $data['estado'] = 'A';
            }
        }

        return $data;
    }
}

class Abono extends BaseModel {
    protected $table = 'abonos';

    public function getByCliente($clienteId) {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE cliente_id = ? 
            ORDER BY fecha DESC
        ");
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll();
    }
}

class Cobro extends BaseModel {
    protected $table = 'cobros';

    public function getByCliente($clienteId) {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE cliente_id = ? 
            ORDER BY fecha DESC
        ");
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll();
    }
}

class Arqueo extends BaseModel {
    protected $table = 'arqueos';

    public function getByFecha($fecha) {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE DATE(fecha) = ?
        ");
        $stmt->execute([$fecha]);
        return $stmt->fetchAll();
    }

    public function getResumen($fechaInicio, $fechaFin) {
        $stmt = $this->db->prepare("
            SELECT 
                SUM(CASE WHEN tipo = 'entrada' THEN monto ELSE 0 END) as total_entradas,
                SUM(CASE WHEN tipo = 'salida' THEN monto ELSE 0 END) as total_salidas
            FROM {$this->table}
            WHERE DATE(fecha) BETWEEN ? AND ?
        ");
        $stmt->execute([$fechaInicio, $fechaFin]);
        return $stmt->fetch();
    }
}
