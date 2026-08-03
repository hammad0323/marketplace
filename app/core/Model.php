<?php
/**
 * Thin base class over PDO. Not an ORM — subclasses write their own
 * queries, this just removes the PDO connection boilerplate.
 */

abstract class Model
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function find($id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBy(string $column, $value): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$column} = :value LIMIT 1");
        $stmt->execute(['value' => $value]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function all(string $orderBy = 'id DESC'): array
    {
        return $this->db->query("SELECT * FROM {$this->table} ORDER BY {$orderBy}")->fetchAll();
    }

    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn ($c) => ":{$c}", $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    public function update($id, array $data): bool
    {
        $assignments = implode(', ', array_map(fn ($c) => "{$c} = :{$c}", array_keys($data)));
        $sql = "UPDATE {$this->table} SET {$assignments} WHERE {$this->primaryKey} = :__id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data + ['__id' => $id]);
    }
}
