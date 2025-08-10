<?php

declare(strict_types=1);

namespace PrepMeal\Core\Database;

class UserRepository
{
    private DatabaseConnection $db;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM users WHERE id = ?"
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM users WHERE email = ?"
        );
        $stmt->execute([$email]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }

    public function create(array $userData): int
    {
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO users (email, password, name, created_at) VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute([
            $userData['email'],
            $userData['password'],
            $userData['name'] ?? null
        ]);
        
        return (int) $this->db->getConnection()->lastInsertId();
    }

    public function update(int $id, array $userData): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE users SET email = ?, name = ?, updated_at = NOW() WHERE id = ?"
        );
        
        return $stmt->execute([
            $userData['email'],
            $userData['name'] ?? null,
            $id
        ]);
    }

    public function updatePassword(int $id, string $hashedPassword): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?"
        );
        
        return $stmt->execute([$hashedPassword, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->getConnection()->prepare(
            "DELETE FROM users WHERE id = ?"
        );
        
        return $stmt->execute([$id]);
    }

    public function getAll(): array
    {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT * FROM users ORDER BY created_at DESC"
        );
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}

