<?php

namespace App\Twig;

use App\Config\LocalizationConfig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Localization Twig Extension
 * Provides filters and functions for displaying localized values in views
 */
class LocalizationExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('currency', [$this, 'formatCurrency']),
            new TwigFilter('price_with_gst', [$this, 'formatPriceWithGST']),
            new TwigFilter('local_date', [$this, 'formatDate']),
            new TwigFilter('local_datetime', [$this, 'formatDatetime']),
            new TwigFilter('local_time', [$this, 'formatTime']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('currency_symbol', [$this, 'getCurrencySymbol']),
            new TwigFunction('currency_code', [$this, 'getCurrencyCode']),
            new TwigFunction('country', [$this, 'getCountry']),
            new TwigFunction('gst_rate', [$this, 'getGSTRate']),
            new TwigFunction('localization', [$this, 'getLocalization']),
        ];
    }

    /**
     * Format number as currency
     * Usage: {{ 1234.56|currency }} outputs: ₹1,234.56
     */
    public function formatCurrency(float $amount, bool $symbol = true): string
    {
        return LocalizationConfig::formatCurrency($amount, $symbol);
    }

    /**
     * Format price with GST
     * Usage: {{ 1000|price_with_gst }} outputs array with base, gst, total
     */
    public function formatPriceWithGST(float $price): array
    {
        return LocalizationConfig::formatPriceWithGST($price);
    }

    /**
     * Format date according to locale
     * Usage: {{ date|local_date }} outputs: 15-01-2026
     */
    public function formatDate($date): string
    {
        return LocalizationConfig::formatDate($date);
    }

    /**
     * Format datetime according to locale
     * Usage: {{ now|local_datetime }} outputs: 15-01-2026 14:30:00
     */
    public function formatDatetime($datetime): string
    {
        return LocalizationConfig::formatDatetime($datetime);
    }

    /**
     * Format time according to locale
     * Usage: {{ now|local_time }} outputs: 14:30:00
     */
    public function formatTime($time): string
    {
        return LocalizationConfig::formatTime($time);
    }

    /**
     * Get currency symbol
     * Usage: {{ currency_symbol() }} outputs: ₹
     */
    public function getCurrencySymbol(): string
    {
        return LocalizationConfig::currencySymbol();
    }

    /**
     * Get currency code
     * Usage: {{ currency_code() }} outputs: INR
     */
    public function getCurrencyCode(): string
    {
        return LocalizationConfig::currencyCode();
    }

    /**
     * Get country name
     * Usage: {{ country() }} outputs: India
     */
    public function getCountry(): string
    {
        return LocalizationConfig::country();
    }

    /**
     * Get GST rate
     * Usage: {{ gst_rate() }} outputs: 18
     */
    public function getGSTRate(): float
    {
        return LocalizationConfig::gstRate();
    }

    /**
     * Get all localization settings
     * Usage: {{ localization() }} returns array of all settings
     */
    public function getLocalization(): array
    {
        return LocalizationConfig::toArray();
    }
}
