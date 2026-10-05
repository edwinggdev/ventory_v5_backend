<?php

require_once __DIR__ . '/BaseModel.php';

class Producto extends BaseModel {
    protected $table = 'producto';

    public function getAll() {
        $marcaTable = $this->prefixedTable('marca');
        $usuarioTable = $this->prefixedTable('usuario');
        $stmt = $this->db->query("
            SELECT p.*, 
                p.marca as idmarca,
                m.des as marca_nombre,
                p.idunidad as unidad_nombre,
                u.nombre as usuario_nombre
            FROM {$this->table} p
            LEFT JOIN {$marcaTable} m ON p.marca = m.id
            LEFT JOIN {$usuarioTable} u ON p.usu = u.id
            ORDER BY p.id DESC
        ");
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $marcaTable = $this->prefixedTable('marca');
        $usuarioTable = $this->prefixedTable('usuario');
        $stmt = $this->db->prepare("
            SELECT p.*, 
                   p.marca as idmarca,
                   m.des as marca_nombre,
                   p.idunidad as unidad_nombre,
                   u.nombre as usuario_nombre
            FROM {$this->table} p
            LEFT JOIN {$marcaTable} m ON p.marca = m.id
            LEFT JOIN {$usuarioTable} u ON p.usu = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function create($data) {
        $data = $this->sanitizeData($data);
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute(array_values($data));
            return ['id' => $this->db->lastInsertId(), 'success' => true];
        } catch (PDOException $e) {
            return ['error' => $e->getMessage(), 'success' => false];
        }
    }

    public function update($id, $data) {
        $data = $this->sanitizeData($data);
        $sets = implode(' = ?, ', array_keys($data)) . ' = ?';
        $values = array_values($data);
        $values[] = $id;
        
        $sql = "UPDATE {$this->table} SET {$sets} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute($values);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['error' => $e->getMessage(), 'success' => false];
        }
    }

    public function delete($id) {
        $retTable = $this->prefixedTable('productoxret');

        try {
            $this->db->beginTransaction();

            $this->db->prepare("DELETE FROM {$retTable} WHERE idprod = ?")->execute([$id]);

            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);

            $this->db->commit();
            return ['success' => true];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['error' => $e->getMessage(), 'success' => false];
        }
    }

    private function sanitizeData($data) {
        if (isset($data['idmarca'])) {
            $data['marca'] = $data['idmarca'];
            unset($data['idmarca']);
        }

        if (isset($data['descuento'])) {
            $data['valora'] = $data['descuento'];
            unset($data['descuento']);
        }

        unset($data['id'], $data['marca_nombre'], $data['unidad_nombre'], $data['usuario_nombre'], $data['stock']);
        return $data;
    }

    public function getWithStock() {
        $marcaTable = $this->prefixedTable('marca');
        $entradasTable = $this->prefixedTable('entradas');
        $salidasTable = $this->prefixedTable('salidas');
        $stmt = $this->db->query("
            SELECT p.*, 
                    COALESCE(SUM(e.cantidad), 0) - COALESCE(SUM(s.cantidad), 0) as stock,
                    m.des as marca_nombre
            FROM {$this->table} p
            LEFT JOIN {$marcaTable} m ON p.marca = m.id
            LEFT JOIN {$entradasTable} e ON p.id = e.producto_id
            LEFT JOIN {$salidasTable} s ON p.id = s.producto_id
            GROUP BY p.id
        ");
        return $stmt->fetchAll();
    }

    public function search($query) {
        $marcaTable = $this->prefixedTable('marca');
        $stmt = $this->db->prepare("
            SELECT p.*, m.des as marca_nombre
            FROM {$this->table} p
            LEFT JOIN {$marcaTable} m ON p.marca = m.id
            WHERE p.des LIKE ? OR p.codigo LIKE ?
        ");
        $stmt->execute(["%{$query}%", "%{$query}%"]);
        return $stmt->fetchAll();
    }

    public function getRetenciones($productoId) {
        $table = $this->prefixedTable('productoxret');
        $configTable = $this->prefixedTable('config');
        $stmt = $this->db->prepare("
            SELECT pr.id, pr.idprod, pr.codigo, pr.valor, COALESCE(c.e3, '') AS descripcion
            FROM {$table} pr
            LEFT JOIN {$configTable} c ON c.e1 = 'idretencion' AND c.e2 = pr.codigo
            WHERE pr.idprod = ?
            ORDER BY pr.id ASC
        ");
        $stmt->execute([$productoId]);
        return $stmt->fetchAll();
    }

    public function saveRetenciones($productoId, $rows) {
        if (!is_array($rows)) {
            $rows = [];
        }

        try {
            $this->db->beginTransaction();
            $this->writeRetenciones($productoId, $rows);
            $this->db->commit();
            return ['success' => true];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['error' => $e->getMessage(), 'success' => false];
        }
    }

    private function writeRetenciones($productoId, $rows) {
        $table = $this->prefixedTable('productoxret');

        $delete = $this->db->prepare("DELETE FROM {$table} WHERE idprod = ?");
        $delete->execute([$productoId]);

        $insert = $this->db->prepare("INSERT INTO {$table} (idprod, codigo, valor) VALUES (?, ?, ?)");
        foreach ($rows as $row) {
            $codigo = isset($row['codigo']) ? trim((string) $row['codigo']) : '';
            if ($codigo === '' || $codigo === '0') {
                continue;
            }
            $valor = (float) ($row['valor'] ?? 0);
            if ($valor <= 0) {
                continue;
            }
            $insert->execute([$productoId, $codigo, $valor]);
        }
    }

    public function saveWithRetenciones($data) {
        $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
        unset($data['items']);

        try {
            $this->db->beginTransaction();

            if (isset($data['id']) && $data['id'] !== '' && $data['id'] !== null) {
                $id = $data['id'];
                $result = $this->update($id, $data);
            } else {
                $result = $this->create($data);
                $id = isset($result['id']) ? $result['id'] : null;
            }

            if (!isset($result['success']) || $result['success'] !== true) {
                throw new RuntimeException(
                    isset($result['error']) ? $result['error'] : 'Error al guardar el producto'
                );
            }

            if ($id !== null) {
                $this->writeRetenciones($id, $items);
            }

            $this->db->commit();
            return ['success' => true, 'id' => $id];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['error' => $e->getMessage(), 'success' => false];
        }
    }
}