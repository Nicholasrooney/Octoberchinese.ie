<?php
/*
 * Receives one stats event from js/main.js (navigator.sendBeacon) and stores
 * it. See includes/stats.php for what is kept and why it needs no consent.
 *
 * Always answers 204 with no body, whether the event was stored or not: the
 * browser does not wait for it, and a stranger probing the endpoint learns
 * nothing from the reply.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/stats.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$done = function () {
    http_response_code(204);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !stats_enabled()) {
    $done();
}

// Only accept events sent from pages on this site.
$host   = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
if ($origin !== '' && strtolower((string) parse_url($origin, PHP_URL_HOST)) !== preg_replace('/:\d+$/', '', $host)) {
    $done();
}

// Staff using the site from the diary would skew the numbers.
if (!empty($_COOKIE['october_staff'])) {
    $done();
}

$userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400);
$clean     = stats_clean_event($_POST, $userAgent, $host);
if ($clean === null) {
    $done();
}

$visitor = $clean['event'] === 'page_view'
    ? stats_visitor_key((string) ($_SERVER['REMOTE_ADDR'] ?? ''), $userAgent)
    : '';

stats_record($clean, $visitor);
$done();
