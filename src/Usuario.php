<?php

namespace App;

class Usuario
{
    public static function crear(string $username, string $password): int
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('INSERT INTO usuarios (username, password_hash) VALUES (?, ?)');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        return (int) $pdo->lastInsertId();
    }

    public static function obtenerPorUsername(string $username): ?array
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('SELECT id, username, creado_en FROM usuarios WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function obtenerPorId(int $id): ?array
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('SELECT id, username, creado_en FROM usuarios WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function verificarLogin(string $username, string $password): ?array
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM usuarios WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        if ($row === false || !password_verify($password, $row['password_hash'])) {
            return null;
        }

        unset($row['password_hash']);
        return $row;
    }

    public static function usernameDisponible(string $username): bool
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE username = ?');
        $stmt->execute([$username]);
        return ((int) $stmt->fetchColumn()) === 0;
    }
}
