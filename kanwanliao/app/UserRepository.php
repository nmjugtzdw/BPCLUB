<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use RuntimeException;

/**
 * PDO implementation of AccountStore.
 *
 * Password material only ever enters as an already-computed hash; this class
 * does not know about passwords and never returns one to a caller.
 */
final class UserRepository implements AccountStore
{
    public function __construct(private readonly PDO $pdo) {}

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, email, password_hash, role, status, created_at '
            . 'FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findByIdentity(string $identity): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, email, password_hash, role, status, created_at '
            . 'FROM users WHERE username = :identity OR email = :identity2 LIMIT 1'
        );
        $stmt->bindValue(':identity', $identity, PDO::PARAM_STR);
        $stmt->bindValue(':identity2', $identity, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function existsUsername(string $username): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        return $stmt->fetchColumn() !== false;
    }

    public function existsEmail(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        return $stmt->fetchColumn() !== false;
    }

    public function create(string $username, string $email, string $passwordHash): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, email, password_hash, role, status, created_at, updated_at) '
            . "VALUES (:username, :email, :hash, 'user', 'active', UTC_TIMESTAMP(), UTC_TIMESTAMP())"
        );
        try {
            $stmt->execute([':username' => $username, ':email' => $email, ':hash' => $passwordHash]);
        } catch (PDOException $error) {
            // 1062 duplicate key: a concurrent registration won the race even
            // though the pre-check passed. Report it as a normal conflict.
            if ((string) $error->getCode() === '23000') {
                throw new RuntimeException('duplicate_account');
            }
            throw $error;
        }
        return (int) $this->pdo->lastInsertId();
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['active', 'restricted', 'disabled'], true)) {
            throw new RuntimeException('invalid_user_status');
        }
        $stmt = $this->pdo->prepare(
            'UPDATE users SET status = :status, updated_at = UTC_TIMESTAMP() WHERE id = :id'
        );
        $stmt->execute([':status' => $status, ':id' => $id]);
    }
}
