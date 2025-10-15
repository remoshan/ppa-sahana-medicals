<?php
// Prevent caching for admin pages to ensure real-time data updates
// This fixes the issue where admin needs to logout/login to see customer updates

// HTTP 1.1
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// HTTP 1.0
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Set content type to prevent content sniffing
header("X-Content-Type-Options: nosniff");

// Prevent clickjacking
header("X-Frame-Options: SAMEORIGIN");

// Enable XSS protection
header("X-XSS-Protection: 1; mode=block");
?>

