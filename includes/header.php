<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/analytics.php';

$pageTitle       = $pageTitle       ?? $site['name'];
$pageDescription = $pageDescription ?? 'Chinese restaurant in Dún Laoghaire serving Sichuan, Hunan and Cantonese dishes for sharing, handmade dumplings and a weekday lunch menu.';
$activePage      = $activePage      ?? '';
$pageKeywords    = $pageKeywords    ?? '';
$pageSchema      = $pageSchema      ?? [];
$heroHeader      = $heroHeader      ?? false;   // transparent header over a full-bleed hero

$nav = [
    'home'     => ['Home', 'index.php'],
    'menu'     => ['Menu', 'menu.php'],
    'gallery'  => ['Gallery', 'gallery.php'],
    'order'    => ['Order', 'order.php'],
    'catering' => ['Events', 'event-catering.php'],
    'visit'    => ['Visit', 'index.php#visit'],
    // The second design (Hunan style) for the restaurant to compare. Remove
    // this line once they have picked a design.
    'october'  => ['October', 'october/'],
    'booking'  => ['Book a table', 'booking.php'],
];

// Booking and ordering can be switched off in config; their links go with them.
if (!booking_online()) {
    unset($nav['booking']);
}
if (!ordering_online()) {
    unset($nav['order']);
}

// Catering is switched off in config, so it leaves the menu too.
if (empty($catering['enabled'])) {
    unset($nav['catering']);
}

$telHref = $site['phone'] ? 'tel:+353' . ltrim(preg_replace('/[^0-9]/', '', $site['phone']), '0') : '';

require_once __DIR__ . '/brand.php';
require_once __DIR__ . '/menu-edits.php';
photos_start_buffer();   // swaps in any photos staff replaced (staff/photos.php)
?>
<!DOCTYPE html>
<html lang="en-IE">
<head>
    <meta charset="utf-8">
    <script>document.documentElement.classList.add('js')</script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <?php if (!empty($pageKeywords)): ?>
        <meta name="keywords" content="<?= e($pageKeywords) ?>">
    <?php endif; ?>
    <meta name="robots" content="<?= e($pageRobots ?? 'index, follow') ?>">
    <link rel="canonical" href="<?= e($pageCanonical ?? seo_canonical()) ?>">

    <meta property="og:type" content="<?= $activePage === 'home' ? 'website' : 'article' ?>">
    <meta property="og:site_name" content="<?= e($site['name']) ?>">
    <meta property="og:locale" content="en_IE">
    <meta property="og:url" content="<?= e($pageCanonical ?? seo_canonical()) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:image" content="<?= e(seo_url($pageImage ?? 'images/og-image.jpg')) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#0f1f19">
    <link rel="icon" href="favicon.ico?v=3" sizes="any">
    <link rel="icon" type="image/png" sizes="64x64" href="images/logo/favicon-64.png?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="images/logo/favicon-32.png?v=3">
    <link rel="apple-touch-icon" href="images/logo/apple-touch-icon.png?v=3">
    <?php render_analytics_head(); ?>
    <?php /* Fonts are served from this site, not Google, so nothing is sent to a third party before consent. */ ?>
    <link rel="stylesheet" href="<?= e(asset('fonts/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <?php foreach ($pageStyles ?? [] as $css): ?>
    <link rel="stylesheet" href="<?= e(asset($css)) ?>">
    <?php endforeach; ?>
    <?php seo_render_schema($pageSchema ?? []); ?>
</head>
<body class="page-<?= e($activePage) ?><?= $heroHeader ? ' has-hero' : '' ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header<?= $heroHeader ? ' is-over-hero' : '' ?>" data-header>
    <div class="header-inner">
        <a class="brand" href="index.php" aria-label="<?= e($site['name']) ?> home">
            <?php render_tree_mark('brand-mark'); ?>
            <span class="brand-text">
                <span class="brand-name">October</span>
                <span class="brand-sub"><span lang="zh-Hans"><?= e($site['name_cn'] ?? '') ?></span> · <?= e($site['tagline'] ?? '') ?></span>
            </span>
        </a>

        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Open navigation">
            <span class="nav-toggle-bars" aria-hidden="true"></span>
        </button>

        <nav id="site-nav" class="site-nav" aria-label="Main">
            <ul>
                <?php foreach ($nav as $key => [$label, $href]): ?>
                    <li>
                        <a href="<?= e($href) ?>"
                           class="<?= $key === 'booking' ? 'nav-book' : '' ?>"
                           <?= $activePage === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($telHref): ?>
                <a class="nav-phone" href="<?= e($telHref) ?>"><?= e($site['phone']) ?></a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main id="main">
