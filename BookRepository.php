<?php

use React\MySQL\ConnectionInterface;
use React\MySQL\QueryResult;
use React\Promise\PromiseInterface;

class BookRepository
{
    private $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    // CREATE
    public function create(array $data): PromiseInterface
    {
        $sql = 'INSERT INTO libros (title, author, isbn, quantity, year) VALUES (?, ?, ?, ?, ?)';
        $params = [$data['title'], $data['author'], $data['isbn'], $data['quantity'], $data['year'] ?? 0];

        return $this->db->query($sql, $params)->then(function (QueryResult $result) {
            return $result->insertId;
        });
    }

    // READ (Todos)
    public function getAll(): PromiseInterface
    {
        return $this->db->query('SELECT * FROM libros')->then(function (QueryResult $result) {
            return $result->resultRows;
        });
    }

    // READ (Por ID)
    public function getById(int $id): PromiseInterface
    {
        return $this->db->query('SELECT * FROM libros WHERE id = ?', [$id])->then(function (QueryResult $result) {
            return count($result->resultRows) > 0 ? $result->resultRows[0] : null;
        });
    }

    // UPDATE
    public function update(int $id, array $data): PromiseInterface
    {
        $fields = [];
        $params = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $id;

        $sql = 'UPDATE libros SET ' . implode(', ', $fields) . ' WHERE id = ?';
        
        return $this->db->query($sql, $params);
    }

    public function delete(int $id): PromiseInterface
    {
        return $this->db->query('DELETE FROM libros WHERE id = ?', [$id])->then(function (QueryResult $result) {
            return $result->affectedRows;
        });
    }
}