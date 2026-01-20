<?php

namespace App\Config;

class FontConfig
{
    public static function profiles(): array
    {
        return [
            'system' => [
                'label' => 'System Default',
                'css' => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                'load' => null,
            ],
            'inter' => [
                'label' => 'Inter',
                'css' => '"Inter", system-ui, sans-serif',
                'load' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            ],
            'roboto' => [
                'label' => 'Roboto',
                'css' => '"Roboto", system-ui, sans-serif',
                'load' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
            ],
            'poppins' => [
                'label' => 'Poppins',
                'css' => '"Poppins", system-ui, sans-serif',
                'load' => 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
            ],
            'montserrat' => [
                'label' => 'Montserrat',
                'css' => '"Montserrat", system-ui, sans-serif',
                'load' => 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap',
            ],
            'playfair' => [
                'label' => 'Playfair Display',
                'css' => '"Playfair Display", system-ui, serif',
                'load' => 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&display=swap',
            ],
        ];
    }

    public static function getProfile(string $key): array
    {
        $profiles = self::profiles();
        return $profiles[$key] ?? $profiles['system'];
    }

    public static function keys(): array
    {
        return array_keys(self::profiles());
    }
}
