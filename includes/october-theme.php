<?php
/*
 * "October" style: the second design of the site, laid out like the
 * China Hunan website (deep teal and peach, centred logo, serif capitals).
 * Lives under /october/ so the restaurant can compare it with the main
 * design and pick what they like from each.
 *
 * Every fact on these pages (menu, prices, hours, address, reviews) comes
 * from the same files as the main site, so the two designs never disagree.
 *
 * Pages call oct_header() at the top and oct_footer() at the bottom.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/brand.php';
require_once __DIR__ . '/hours.php';
require_once __DIR__ . '/analytics.php';

/** Path from an /october/ page back to the site root. */
const OCT_ROOT = '../';

function oct_tel(): string
{
    global $site;
    return $site['phone'] ? 'tel:+353' . ltrim(preg_replace('/[^0-9]/', '', $site['phone']), '0') : '';
}

/** Small "中文 ◆ ENGLISH" label used above every heading. */
function oct_eyebrow(string $cn, string $en): string
{
    return '<p class="oc-eyebrow"><span lang="zh-Hans">' . e($cn) . '</span><span class="oc-diamond" aria-hidden="true">◆</span><span>' . e($en) . '</span></p>';
}

function oct_header(string $title, string $description, string $active = ''): void
{
    global $site;
    $nav = [
        'menu'    => ['Menu', 'menu.php'],
        'gallery' => ['Gallery', 'gallery.php'],
        'order'   => ['Order', OCT_ROOT . 'order.php'],
        'booking' => ['Bookings', OCT_ROOT . 'booking.php'],
        'contact' => ['Contact', 'index.php#contact'],
    ];
    if (!booking_online()) {
        unset($nav['booking']);
    }
    if (!ordering_online()) {
        unset($nav['order']);
    }
    ?>
<!DOCTYPE html>
<html lang="en-IE">
<head>
    <meta charset="utf-8">
    <script>document.documentElement.classList.add('js')</script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <?php /* A design preview: keep it out of Google so it doesn't compete with the main pages. */ ?>
    <meta name="robots" content="noindex, follow">
    <meta name="theme-color" content="#16302f">
    <link rel="icon" type="image/svg+xml" href="<?= OCT_ROOT ?>images/favicon.svg">
    <?php render_analytics_head(); ?>
    <link rel="stylesheet" href="<?= e(asset('fonts/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('october/october.css')) ?>">
</head>
<body class="oc oc-page-<?= e($active) ?>">
<a class="oc-skip" href="#main">Skip to content</a>

<header class="oc-header" data-oc-header>
    <a class="oc-logo" href="index.php" aria-label="<?= e($site['name']) ?> home">
        <?php render_tree_mark('oc-logo-mark'); ?>
        <span class="oc-logo-word">October</span>
        <span class="oc-logo-sub"><span lang="zh-Hans"><?= e($site['name_cn']) ?></span> · Chinese Dining</span>
    </a>
    <button class="oc-burger" type="button" aria-expanded="false" aria-controls="oc-nav" aria-label="Open navigation">
        <span aria-hidden="true"></span>
    </button>
    <nav id="oc-nav" class="oc-nav" aria-label="Main">
        <ul>
            <li><a href="index.php" <?= $active === 'home' ? 'aria-current="page"' : '' ?>>Home</a></li>
            <?php foreach ($nav as $key => [$label, $href]): ?>
                <li><a href="<?= e($href) ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a></li>
            <?php endforeach; ?>
            <li><a class="oc-nav-switch" href="<?= OCT_ROOT ?>index.php">Classic design</a></li>
        </ul>
    </nav>
</header>

<main id="main">
    <?php
}

function oct_footer(): void
{
    global $site;
    $tel   = oct_tel();
    $hours = hours_display();
    ?>
</main>

<section class="oc-contact" id="contact" aria-label="Contact and reservations">
    <div class="oc-wrap oc-contact-grid">
        <div class="oc-reveal">
            <?= oct_eyebrow('联系方式', 'Contact information') ?>
            <h2>Get in touch with us</h2>
            <p><?= e($site['address']) ?>, <?= e($site['eircode']) ?></p>
            <div class="oc-btn-row">
                <a class="oc-btn oc-btn-light" href="<?= e($site['maps_url']) ?>" target="_blank" rel="noopener">View on Google Maps</a>
                <?php if ($tel): ?><a class="oc-btn oc-btn-light" href="<?= e($tel) ?>">Call us on <?= e($site['phone']) ?></a><?php endif; ?>
            </div>
            <h3 class="oc-hours-title">Opening hours</h3>
            <dl class="oc-hours">
                <?php foreach ($hours as $days => $time): ?>
                    <div><dt><?= e($days) ?></dt><dd><?= e($time) ?></dd></div>
                <?php endforeach; ?>
                <div><dt>Lunch menu</dt><dd>12:30 to 16:00</dd></div>
            </dl>
        </div>
        <div class="oc-reveal">
            <?= oct_eyebrow('预订餐桌', 'Make a reservation') ?>
            <h2>Reserve your table and stay a while.</h2>
            <p><?= booking_online() ? 'Book online in a minute for up to 8 guests, or ring us for a bigger group.' : 'Ring us to book, whether it is a table for two or a long table for the whole family.' ?> We will have the table ready under the copper arches.</p>
            <div class="oc-btn-row">
                <a class="oc-btn oc-btn-light" href="<?= e(book_href(OCT_ROOT)) ?>"><?= e(book_label()) ?></a>
            </div>
            <figure class="oc-contact-photo">
                <img src="<?= OCT_ROOT ?>images/storefront.jpg" alt="The green October shopfront on George’s Street Upper" loading="lazy">
            </figure>
        </div>
    </div>
</section>

<footer class="oc-footer">
    <div class="oc-wrap oc-footer-grid">
        <div class="oc-footer-col">
            <p class="oc-footer-head">Contact</p>
            <dl class="oc-footer-list">
                <div><dt>Address</dt><dd>60 George’s Street Upper,<br>Dún Laoghaire, <?= e($site['eircode']) ?></dd></div>
                <?php if ($tel): ?><div><dt>Tel</dt><dd><a href="<?= e($tel) ?>"><?= e($site['phone']) ?></a></dd></div><?php endif; ?>
                <?php if ($site['email']): ?><div><dt>Mail</dt><dd><a href="mailto:<?= e($site['email']) ?>"><?= e($site['email']) ?></a></dd></div><?php endif; ?>
            </dl>
        </div>
        <div class="oc-footer-col oc-footer-centre">
            <?php render_tree_mark('oc-footer-mark'); ?>
            <p class="oc-footer-motto"><span lang="zh-Hans">食悦</span><br>Good food brings<br>people together</p>
        </div>
        <div class="oc-footer-col oc-footer-right">
            <p class="oc-footer-head">Opening hours</p>
            <dl class="oc-footer-list">
                <?php foreach ($hours as $days => $time): ?>
                    <div><dt><?= e($days) ?></dt><dd><?= e($time) ?></dd></div>
                <?php endforeach; ?>
            </dl>
            <p><a class="oc-footer-link" href="<?= e(book_href(OCT_ROOT)) ?>"><?= booking_online() ? 'Book a table with us' : 'Call to book a table' ?></a></p>
        </div>
    </div>
    <div class="oc-wrap oc-footer-legal">
        <a href="<?= OCT_ROOT ?>privacy.php">Privacy policy</a>
        <button type="button" class="oc-linkbtn" data-cookie-settings>Cookie settings</button>
        <span>© <?= date('Y') ?> <?= e($site['name']) ?></span>
    </div>
</footer>

<div class="oc-mobile-cta">
    <?php if (booking_online()): ?>
        <?php if ($tel): ?><a href="<?= e($tel) ?>">Call</a><?php endif; ?>
        <a class="is-book" href="<?= OCT_ROOT ?>booking.php">Book a table</a>
    <?php elseif ($tel): ?>
        <a class="is-book" href="<?= e($tel) ?>">Call to book</a>
    <?php endif; ?>
</div>

<?php
    // Same cookie notice as the main site; only the privacy link needs the ../
    require_once __DIR__ . '/cookie-banner.php';
    ob_start();
    render_cookie_banner();
    echo str_replace('href="privacy.php"', 'href="' . OCT_ROOT . 'privacy.php"', ob_get_clean());
?>
<script src="<?= e(asset('js/main.js')) ?>" defer></script>
<script src="<?= e(asset('october/october.js')) ?>" defer></script>
</body>
</html>
    <?php
}
