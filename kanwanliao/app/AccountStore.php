<?php

declare(strict_types=1);

namespace App;

/**
 * Account persistence contract.
 *
 * Controllers depend on this instead of PDO so the registration and login
 * flows can be tested without a database, and so no controller ever assembles
 * its own account SQL.
 */
interface AccountStore
{
    /** @return array<string, mixed>|null id, username, email, password_hash, role, status */
    public function findById(int $id): ?array;

    /** @return array<string, mixed>|null 用用户名或邮箱登录，两者都试 */
    public function findByIdentity(string $identity): ?array;

    public function existsUsername(string $username): bool;

    public function existsEmail(string $email): bool;

    /** @return int 新账号 id */
    public function create(string $username, string $email, string $passwordHash): int;

    /** @param string $status active|restricted|disabled */
    public function setStatus(int $id, string $status): void;
}
