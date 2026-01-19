<?php

namespace App\Services;

class FlashService
{
    private const FLASH_KEY = '_flash';

    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION[self::FLASH_KEY]) || !is_array($_SESSION[self::FLASH_KEY])) {
            $_SESSION[self::FLASH_KEY] = [];
        }
    }

    public static function set(string $key, mixed $value): void
    {
        self::ensureSession();
        $_SESSION[self::FLASH_KEY][$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::ensureSession();
        if (!array_key_exists($key, $_SESSION[self::FLASH_KEY])) {
            return $default;
        }
        $value = $_SESSION[self::FLASH_KEY][$key];
        unset($_SESSION[self::FLASH_KEY][$key]);
        return $value;
    }
}
