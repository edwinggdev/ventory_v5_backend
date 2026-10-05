<?php

class BaseModel {
    protected $table;
    protected $db;
    protected $tablePrefix = '5';

    public function __construct() {
        $this->db = getDb();
        if (!empty($this->table) && strncmp($this->table, $this->tablePrefix, 1) !== 0) {
            $this->table = $this->tablePrefix . $this->table;
        }
    }

    protected function prefixedTable(string $table): string {
        if ($table === '') {
            return $table;
        }
        return strncmp($table, $this->tablePrefix, 1) === 0 ? $table : $this->tablePrefix . $table;
    }

    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function create($data) {
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
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        
        try {
            $stmt->execute([$id]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['error' => $e->getMessage(), 'success' => false];
        }
    }
}
