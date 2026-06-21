<?php

namespace App\Repositories;

use PDO;

final class BookRepository
{
    public function __construct(
        private PDO $pdo
    ) {}

    public function all(string $q = '', int $limit = 0): array
    {
        $sql = 'SELECT * FROM books';
        $args = [];

        if ($q !== '') {
            $sql .= ' WHERE title LIKE :q_title OR author LIKE :q_author';
            $args[':q_title'] = '%' . $q . '%';
            $args[':q_author'] = '%' . $q . '%';
        }

        $sql .= ' ORDER BY id ASC';

        if ($limit > 0) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM books WHERE id = :id'
        );

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    // ✅ create 方法加入 $createdBy
    public function create(array $data, int $createdBy): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO books (title, author, year, genre, created_by)
             VALUES (:title, :author, :year, :genre, :owner)'
        );

        $stmt->execute([
            ':title'  => trim($data['title']),
            ':author' => trim($data['author']),
            ':year'   => (int)$data['year'],
            ':genre'  => trim($data['genre'] ?? 'Uncategorised'),
            ':owner'  => $createdBy
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE books
             SET title = :title,
                 author = :author,
                 year = :year,
                 genre = :genre
             WHERE id = :id'
        );

        return $stmt->execute([
            ':id'     => $id,
            ':title'  => $data['title'],
            ':author' => $data['author'],
            ':year'   => $data['year'],
            ':genre'  => $data['genre']
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM books WHERE id = :id'
        );

        return $stmt->execute([':id' => $id]);
    }
}