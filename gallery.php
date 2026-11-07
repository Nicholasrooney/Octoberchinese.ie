<?php
$pageTitle       = 'Gallery | October Chinese Dining, Dún Laoghaire';
$pageDescription = 'Photos of the food and the dining room at October Chinese Dining in Dún Laoghaire: Hunan sea bass, Sichuan chicken, bao buns, dumplings and noodle soups.';
$pageKeywords    = 'October Chinese Dining photos, Chinese food Dún Laoghaire photos, Dún Laoghaire restaurant';
$activePage      = 'gallery';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/seo.php';
$pageSchema = [seo_breadcrumbs(['Gallery' => 'gallery.php'])];

require __DIR__ . '/includes/header.php';

// To add a photo: upload it to /images and add a line here.
// 'wide' spans two columns on larger screens, 'tall' spans two rows.
$photos = [
    ['kataifi-prawns-pour.jpg',        'Mango lime sauce poured over kataifi wrapped king prawns', 'wide'],
    ['interior.jpg',                   'The dining room under the gold tree and copper arches',    'tall'],
    ['sichuan-chicken.jpg',            'Sichuan spicy chicken with dried chillies and star anise',  ''],
    ['bao-buns-overhead.jpg',          'Bao buns with crispy duck, branded with the October tree',  ''],
    ['sea-bass-pour.jpg',              'Hot oil ladled over steamed sea bass with Hunan chillies',  'tall'],
    ['beef-noodle-soup-overhead.jpg',  'Taiwanese braised beef noodle soup',                        ''],
    ['dumplings-overhead.jpg',         'Pan-fried dumplings with dipping sauce',                   ''],
    ['sea-bass-overhead.jpg',          'Steamed sea bass with Hunan chopped chillies, from above',  'wide'],
    ['beef-fillet-steam.jpg',          'Stir-fried beef fillet with wild chilli pepper',           ''],
    ['wonton-noodle-soup.jpg',         'Wonton noodle soup',                                        ''],
    ['storefront.jpg',                 'The green shopfront on George’s Street Upper',              'wide'],
    ['kataifi-prawns.jpg',             'Kataifi wrapped king prawns',                               ''],
    ['bao-buns.jpg',                   'Bao buns in a bamboo steamer',                              ''],
    ['beef-fillet-overhead.jpg',       'Stir-fried beef fillet, from above',                       'tall'],
    ['sichuan-chicken-side.jpg',       'Sichuan spicy chicken',                                     ''],
    ['wonton-noodle-soup-overhead.jpg','Wonton noodle soup, from above',                           ''],
    ['sea-bass-chillies.jpg',          'Sea bass under chopped chillies',                           ''],
    ['beef-noodle-soup.jpg',           'Braised beef noodle soup',                                  ''],
    ['dumplings.jpg',                  'Pan-fried dumplings',                                       ''],
    ['kataifi-prawns-overhead.jpg',    'Kataifi prawns from above',                                 'wide'],
    ['street.jpg',                     'October on George’s Street Upper, Dún Laoghaire',           ''],
];
?>

<section class="page-hero">
    <img class="page-hero-img" src="images/kataifi-prawns-overhead.jpg" alt="" aria-hidden="true">
    <div class="container page-hero-inner">
        <p class="eyebrow">A look inside</p>
        <h1>Gallery <span lang="zh-Hans">相册</span></h1>
        <p>The food, the room and the green door on George’s Street.</p>
    </div>
</section>

<div class="container">
    <ul class="gallery">
        <?php foreach ($photos as $i => [$file, $caption, $shape]): ?>
            <li class="<?= $shape ? 'g-' . e($shape) : '' ?>">
                <button type="button" class="gallery-item" data-index="<?= $i ?>"
                        data-src="images/<?= e($file) ?>" data-caption="<?= e($caption) ?>"
                        aria-label="Open photo: <?= e($caption) ?>">
                    <img src="images/<?= e($file) ?>" alt="<?= e($caption) ?>" loading="lazy">
                    <span class="gallery-caption" aria-hidden="true"><?= e($caption) ?></span>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<dialog class="lightbox" aria-label="Photo viewer">
    <figure>
        <img src="" alt="">
        <figcaption></figcaption>
    </figure>
    <button type="button" class="lb-btn lb-prev" aria-label="Previous photo">&#8249;</button>
    <button type="button" class="lb-btn lb-next" aria-label="Next photo">&#8250;</button>
    <button type="button" class="lb-btn lb-close" aria-label="Close">&times;</button>
</dialog>

<section class="section cta-band">
    <?php render_arches('arches cta-arches'); ?>
    <div class="container cta-inner">
        <p class="eyebrow">Looks good?</p>
        <h2>It tastes even better</h2>
        <div class="cta-actions"><a class="btn btn-gold" href="booking.php">Book a table</a></div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
