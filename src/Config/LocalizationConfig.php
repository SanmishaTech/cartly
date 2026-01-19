<?php

namespace App\Config;

/**
 * Localization Configuration
 * India-specific settings for currency, timezone, date formatting, and taxes
 */
class LocalizationConfig
{
    /**
     * Get currency code
     * @return string ISO 4217 currency code (e.g., INR, USD)
     */
    public static function currencyCode(): string
    {
        return $_ENV['CURRENCY'] ?? 'INR';
    }

    /**
     * Get currency symbol
     * @return string Currency symbol (e.g., ₹, $, €)
     */
    public static function currencySymbol(): string
    {
        return $_ENV['CURRENCY_SYMBOL'] ?? '₹';
    }

    /**
     * Get timezone
     * @return string PHP timezone string (e.g., Asia/Kolkata)
     */
    public static function timezone(): string
    {
        return $_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata';
    }

    /**
     * Get locale
     * @return string Locale code (e.g., en_IN)
     */
    public static function locale(): string
    {
        return $_ENV['APP_LOCALE'] ?? 'en_IN';
    }

    /**
     * Get country
     * @return string Country name (e.g., India)
     */
    public static function country(): string
    {
        return $_ENV['APP_COUNTRY'] ?? 'India';
    }

    /**
     * Get date format
     * @return string PHP date format (e.g., d-m-Y)
     */
    public static function dateFormat(): string
    {
        return $_ENV['DATE_FORMAT'] ?? 'd-m-Y';
    }

    /**
     * Get datetime format
     * @return string PHP datetime format (e.g., d-m-Y H:i:s)
     */
    public static function datetimeFormat(): string
    {
        return $_ENV['DATETIME_FORMAT'] ?? 'd-m-Y H:i:s';
    }

    /**
     * Get time format
     * @return string PHP time format (e.g., H:i:s)
     */
    public static function timeFormat(): string
    {
        return $_ENV['TIME_FORMAT'] ?? 'H:i:s';
    }

    /**
     * Get GST rate (%)
     * @return float GST rate as percentage (e.g., 18)
     */
    public static function gstRate(): float
    {
        return (float)($_ENV['GST_RATE'] ?? 18);
    }

    /**
     * Check if GST is enabled
     * @return bool True if GST calculation is enabled
     */
    public static function gstEnabled(): bool
    {
        $enabled = $_ENV['GST_ENABLED'] ?? 'true';
        return strtolower($enabled) === 'true';
    }

    /**
     * Get decimal separator
     * @return string Decimal separator (. or ,)
     */
    public static function decimalSeparator(): string
    {
        return $_ENV['DECIMAL_SEPARATOR'] ?? '.';
    }

    /**
     * Get thousands separator
     * @return string Thousands separator (, or .)
     */
    public static function thousandsSeparator(): string
    {
        return $_ENV['THOUSANDS_SEPARATOR'] ?? ',';
    }

    /**
     * Format currency value
     * @param float $amount Amount to format
     * @param bool $includeSymbol Include currency symbol
     * @return string Formatted currency (e.g., "₹1,23,456.00")
     */
    public static function formatCurrency(float $amount, bool $includeSymbol = true): string
    {
        $formatted = number_format(
            $amount,
            2,
            self::decimalSeparator(),
            self::thousandsSeparator()
        );

        if ($includeSymbol) {
            return self::currencySymbol() . $formatted;
        }

        return $formatted;
    }

    /**
     * Format price with GST
     * @param float $basePrice Base price before GST
     * @return array ['base' => X, 'gst' => Y, 'total' => Z]
     */
    public static function formatPriceWithGST(float $basePrice): array
    {
        $gstRate = self::gstRate();
        $gstAmount = $basePrice * ($gstRate / 100);
        $totalPrice = $basePrice + $gstAmount;

        return [
            'base' => $basePrice,
            'gst_rate' => $gstRate,
            'gst_amount' => $gstAmount,
            'total' => $totalPrice,
            'base_formatted' => self::formatCurrency($basePrice),
            'gst_formatted' => self::formatCurrency($gstAmount),
            'total_formatted' => self::formatCurrency($totalPrice),
        ];
    }

    /**
     * Format date
     * @param string|int $date Date string or timestamp
     * @return string Formatted date (e.g., "15-01-2026")
     */
    public static function formatDate($date): string
    {
        if (is_numeric($date)) {
            $timestamp = $date;
        } else {
            $timestamp = strtotime($date);
        }

        return date(self::dateFormat(), $timestamp);
    }

    /**
     * Format datetime
     * @param string|int $datetime Datetime string or timestamp
     * @return string Formatted datetime (e.g., "15-01-2026 14:30:00")
     */
    public static function formatDatetime($datetime): string
    {
        if (is_numeric($datetime)) {
            $timestamp = $datetime;
        } else {
            $timestamp = strtotime($datetime);
        }

        return date(self::datetimeFormat(), $timestamp);
    }

    /**
     * Format time
     * @param string|int $time Time string or timestamp
     * @return string Formatted time (e.g., "14:30:00")
     */
    public static function formatTime($time): string
    {
        if (is_numeric($time)) {
            $timestamp = $time;
        } else {
            $timestamp = strtotime($time);
        }

        return date(self::timeFormat(), $timestamp);
    }

    /**
     * Initialize timezone
     * Call this early in application bootstrap
     */
    public static function initializeTimezone(): void
    {
        date_default_timezone_set(self::timezone());
    }

    /**
     * Get all localization settings as array
     * Useful for passing to views
     */
    public static function toArray(): array
    {
        return [
            'currency_code' => self::currencyCode(),
            'currency_symbol' => self::currencySymbol(),
            'timezone' => self::timezone(),
            'locale' => self::locale(),
            'country' => self::country(),
            'date_format' => self::dateFormat(),
            'datetime_format' => self::datetimeFormat(),
            'time_format' => self::timeFormat(),
            'gst_rate' => self::gstRate(),
            'gst_enabled' => self::gstEnabled(),
            'decimal_separator' => self::decimalSeparator(),
            'thousands_separator' => self::thousandsSeparator(),
        ];
    }
}
