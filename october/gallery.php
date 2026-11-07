<?php
/*
 * October, second design: gallery in the style of the China Hunan site.
 * To add a photo: put it in /images/oct and add a line below.
 */
require_once __DIR__ . '/../includes/october-theme.php';

$photos = [
    ['oct/kataifi-prawns-pour.jpg',  'Mango lime sauce poured over kataifi wrapped king prawns'],
    ['oct/sea-bass-angle.jpg',       'Steamed sea bass with Hunan chopped chillies'],
    ['oct/beef-fillet-steam.jpg',    'Stir-fried beef fillet with wild chilli pepper'],
    ['oct/wonton-soup-top.jpg',      'Wonton noodle soup with pak choi and a soy-braised egg'],
    ['interior.jpg',                 'The dining room under the gold tree and copper arches'],
    ['oct/bao-buns-side.jpg',        'Bao buns with crispy duck in a bamboo steamer'],
    ['oct/sichuan-chicken-wide.jpg', 'Sichuan spicy chicken with dried chillies'],
    ['oct/dumplings.jpg',            'Pan-fried dumplings with dipping sauce'],
    ['oct/beef-noodle-top.jpg',      'Taiwanese braised beef noodle soup'],
    ['oct/kataifi-prawns.jpg',       'Kataifi wrapped king prawns with mango lime sauce'],
    ['oct/sea-bass-top.jpg',         'Sea bass fillets laid in a chevron'],
    ['oct/beef-fillet-top.jpg',      'Beef fillet with wild chilli pepper, from above'],
    ['storefront.jpg',               'The green shopfront on George’s Street Upper'],
    ['oct/wonton-soup.jpg',          'Wonton noodle soup'],
    ['oct/bao-buns-open.jpg',        'Bao buns stamped with the October tree'],
    ['oct/sichuan-chicken.jpg',      'Sichuan spicy chicken'],
    ['oct/dumplings-top.jpg',        'Pan-fried dumplings, from above'],
    ['oct/beef-noodle-side.jpg',     'Braised beef noodle soup'],
    ['oct/kataifi-prawns-top.jpg',   'Kataifi prawns, from above'],
    ['oct/sea-bass-close.jpg',       'Hunan chopped chillies on steamed sea bass'],
    ['oct/beef-fillet-low.jpg',      'Stir-fried beef fillet'],
    ['oct/sichuan-chicken-anise.jpg','Sichuan spicy chicken with star anise'],
    ['street.jpg',                   'October on George’s Street Upper, Dún Laoghaire'],
];

oct_header(
    'Gallery | October Chinese Dining, Dún Laoghaire',
    'Photos of the food and the dining room at October Chinese Dining in Dún Laoghaire.',
    'gallery'
);
?>

<section class="oc-pagehero">
    <div class="oc-wrap oc-center">
        <p class="oc-pagehero-cn" lang="zh-Hans">美味图鉴</p>
        <h1>Gallery</h1>
    </div>
</section>

<section class="oc-gallery-wrap">
    <div class="oc-wrap">
        <ul class="oc-gallery">
            <?php foreach ($photos as $i => [$file, $caption]): ?>
                <li>
                    <button type="button" class="gallery-item" data-index="<?= $i ?>"
                            data-src="<?= OCT_ROOT ?>images/<?= e($file) ?>" data-caption="<?= e($caption) ?>"
                            aria-label="Open photo: <?= e($caption) ?>">
                        <img src="<?= OCT_ROOT ?>images/<?= e($file) ?>" alt="<?= e($caption) ?>" loading="lazy">
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<dialog class="lightbox" aria-label="Photo viewer">
    <figure>
        <img src="" alt="">
        <figcaption></figcaption>
    </figure>
    <button type="button" class="lb-btn lb-prev" aria-label="Previous photo">&#8249;</button>
    <button type="button" class="lb-btn lb-next" aria-label="Next photo">&#8250;</button>
    <button type="button" class="lb-btn lb-close" aria-label="Close">&times;</button>
</dialog>

<?php oct_footer(); ?>
