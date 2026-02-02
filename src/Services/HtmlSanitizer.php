<?php

namespace App\Services;

/**
 * Sanitizes HTML from rich text editors (e.g. TEX) for safe storage and display.
 * Strips dangerous attributes, inline styles, and optionally H1 headings.
 */
class HtmlSanitizer
{
    /**
     * Sanitize HTML for safe storage.
     *
     * @param mixed $value Raw input (string or null)
     * @param int $maxLength Maximum allowed length
     * @param string $field Error field key for validation messages
     * @param array<string, string> $errors Errors array (by reference)
     * @param bool $required If true, empty input produces empty string; otherwise null
     * @return string|null Sanitized HTML or null on validation failure
     */
    public function sanitize(
        mixed $value,
        int $maxLength,
        string $field,
        array &$errors,
        bool $required = false
    ): ?string {
        if ($value === null) {
            return $required ? '' : null;
        }

        $html = str_replace("\0", '', trim((string) $value));
        if ($html === '') {
            return $required ? '' : null;
        }

        if (strlen($html) > $maxLength) {
            $errors[$field] = 'Value is too long.';
            return null;
        }

        if (preg_match('/<\s*h1\b/i', $html)) {
            $errors[$field] = 'H1 headings are not allowed.';
            return null;
        }

        $html = preg_replace('/\sstyle=("|\')(.*?)\1/i', '', $html);
        $html = preg_replace('/\sstyle=([^"\'][^\s>]*)/i', '', $html);

        return $html;
    }
}
