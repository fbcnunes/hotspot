<?php

declare(strict_types=1);

namespace App\Utils;

final class Normalizer
{
    public static function normalizeUsername(string $username): string
    {
        $username = trim(mb_strtolower($username, 'UTF-8'));
        return preg_replace('/\s+/', '', $username) ?? '';
    }

    public static function isValidUsername(string $username): bool
    {
        return (bool)preg_match('/^[a-z0-9._-]+$/', $username);
    }

    public static function onlyDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
