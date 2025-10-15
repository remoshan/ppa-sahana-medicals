<?php
/**
 * Utility functions for Sahana Medicals
 */

/**
 * Format currency in Sri Lankan Rupees
 * @param float $amount The amount to format
 * @param bool $showSymbol Whether to show the currency symbol
 * @return string Formatted currency string
 */
function formatCurrency($amount, $showSymbol = true) {
    $formatted = number_format($amount, 2);
    return $showSymbol ? 'LKR ' . $formatted : $formatted;
}

/**
 * Format currency for display in tables and cards
 * @param float $amount The amount to format
 * @return string Formatted currency string
 */
function formatPrice($amount) {
    return 'LKR ' . number_format($amount, 2);
}

/**
 * Get currency symbol
 * @return string Currency symbol
 */
function getCurrencySymbol() {
    return 'LKR';
}

/**
 * Format currency with compact notation for large amounts
 * @param float $amount The amount to format
 * @return string Formatted currency string
 */
function formatCurrencyCompact($amount) {
    if ($amount >= 1000000) {
        return 'LKR ' . number_format($amount / 1000000, 1) . 'M';
    } elseif ($amount >= 1000) {
        return 'LKR ' . number_format($amount / 1000, 1) . 'K';
    } else {
        return 'LKR ' . number_format($amount, 2);
    }
}
?>
