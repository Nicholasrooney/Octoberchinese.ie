<?php
$pageTitle       = 'Menu | October Chinese Dining, Dún Laoghaire';
$pageDescription = 'The October menu: Sichuan, Hunan and Cantonese specials, appetisers, soups, mains, dumplings, noodles and rice, plus a lunch menu from 12:30. Allergens listed on every dish.';
$pageKeywords    = 'Chinese menu Dún Laoghaire, Sichuan menu Dublin, dumplings Dún Laoghaire, Chinese lunch menu Dún Laoghaire';
$activePage      = 'menu';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/menu-data.php';
require_once __DIR__ . '/includes/seo.php';
$pageSchema = [
    seo_menu_node(array_merge($menu, $lunchMenu)),
    seo_breadcrumbs(['Menu' => 'menu.php']),
];

require __DIR__ . '/includes/header.php';

// A photo to open each section that has one.
$sectionPhotos = [
    'october-special'    => ['sichuan-chicken-side.jpg', 'Sichuan spicy chicken with dried chillies'],
    'appetisers'         => ['kataifi-prawns-overhead.jpg', 'Kataifi wrapped king prawns with mango lime sauce'],
    'mains'              => ['beef-fillet-steam.jpg', 'Steam rising from a bowl of stir-fried beef fillet'],
    'dumplings'          => ['dumplings.jpg', 'Pan-fried dumplings with dipping sauce'],
    'lunch-bowls'        => ['beef-noodle-soup.jpg', 'Braised beef in a blue and white bowl'],
    'lunch-noodle-soups' => ['wonton-noodle-soup-overhead.jpg', 'Wonton noodle soup from above'],
];

$activeTab = (($_GET['tab'] ?? '') === 'lunch') ? 'lunch' : 'carte';

function render_menu_sections(array $sections, array $allergenKey, array $photos): void
{
    foreach ($sections as $section): ?>
        <section class="menu-section" id="<?= e($section['id']) ?>" aria-labelledby="<?= e($section['id']) ?>-title">
            <header class="menu-section-head">
                <?php if (!empty($section['cn'])): ?>
                    <span class="menu-section-cn" lang="zh-Hans" aria-hidden="true"><?= e($section['cn']) ?></span>
                <?php endif; ?>
                <h2 id="<?= e($section['id']) ?>-title"><?= e($section['title']) ?></h2>
                <?php if ($section['note']): ?><p class="menu-note"><?= e($section['note']) ?></p><?php endif; ?>
                <?php if (!empty($section['intro'])): ?><p class="menu-intro"><?= e($section['intro']) ?></p><?php endif; ?>
                <span class="ornament" aria-hidden="true"></span>
            </header>

            <?php if (isset($photos[$section['id']])): [$img, $alt] = $photos[$section['id']]; ?>
                <figure class="menu-photo">
                    <img src="images/<?= e($img) ?>" alt="<?= e($alt) ?>" loading="lazy">
                </figure>
            <?php endif; ?>

            <ul class="menu-items">
                <?php foreach ($section['items'] as $item): ?>
                    <li class="menu-item" data-veg="<?= $item['veg'] ? '1' : '0' ?>">
                        <div class="menu-item-top">
                            <h3>
                                <span class="mi-name"><?= e($item['name']) ?></span>
                                <span class="mi-icons">
                                    <?php if ($item['chef']): ?><span class="mi-icon is-chef" title="Chef’s recommendation"><?= icon('chef') ?><span class="visually-hidden">Chef’s recommendation</span></span><?php endif; ?>
                                    <?php if ($item['veg']): ?><span class="mi-icon is-veg" title="Vegetarian"><?= icon('leaf') ?><span class="visually-hidden">Vegetarian</span></span><?php endif; ?>
                                    <?php if ($item['seafood']): ?><span class="mi-icon is-sea" title="Seafood"><?= icon('wave') ?><span class="visually-hidden">Seafood</span></span><?php endif; ?>
                                </span>
                            </h3>
                            <span class="mi-leader" aria-hidden="true"></span>
                            <?php if (show_prices()): ?>
                                <span class="price">
                                    <?php if ($item['price_label']): ?><small><?= e($item['price_label']) ?></small><?php endif; ?>€<?= e(fmt_price($item['price'])) ?>
                                    <?php foreach ($item['more_prices'] as [$label, $p]): ?>
                                        <span class="price-alt"><small><?= e($label) ?></small>€<?= e(fmt_price($p)) ?></span>
                                    <?php endforeach; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($item['cn']): ?><p class="mi-cn" lang="zh-Hans"><?= e($item['cn']) ?></p><?php endif; ?>
                        <?php if ($item['desc']): ?><p class="menu-desc"><?= e($item['desc']) ?></p><?php endif; ?>
                        <div class="mi-meta">
                            <?= spice_meter((int) $item['spice']) ?>
                            <?php if (!empty($item['allergens'])): ?>
                                <span class="allergens">
                                    <span class="visually-hidden">Allergens:</span>
                                    <?php foreach ($item['allergens'] as $code): ?>
                                        <abbr title="<?= e($allergenKey[$code] ?? $code) ?>"><?= e($code) ?></abbr>
                                    <?php endforeach; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach;
}
?>

<section class="page-hero page-hero-menu">
    <img class="page-hero-img" src="images/sea-bass-overhead.jpg" alt="" aria-hidden="true">
    <div class="container page-hero-inner">
        <p class="eyebrow">À la carte &amp; lunch</p>
        <h1>The Menu <span lang="zh-Hans">菜单</span></h1>
        <p>Dishes made for sharing. Order a few for the table and pass them round.</p>
    </div>
</section>

<div class="menu-shell" data-menu>
    <div class="menu-bar">
        <div class="container menu-bar-inner">
            <div class="menu-tabs" role="tablist" aria-label="Choose a menu">
                <button type="button" role="tab" id="tab-carte" aria-controls="panel-carte" aria-selected="<?= $activeTab === 'carte' ? 'true' : 'false' ?>" data-tab="carte">À la carte</button>
                <button type="button" role="tab" id="tab-lunch" aria-controls="panel-lunch" aria-selected="<?= $activeTab === 'lunch' ? 'true' : 'false' ?>" data-tab="lunch">Lunch <small><?= e($lunchHours) ?></small></button>
            </div>
            <label class="veg-toggle">
                <input type="checkbox" data-veg-filter>
                <span class="veg-switch" aria-hidden="true"></span>
                <?= icon('leaf') ?> Vegetarian only
            </label>
        </div>
        <div class="container">
            <nav class="menu-jump" aria-label="Menu sections" data-jump="carte"<?= $activeTab === 'carte' ? '' : ' hidden' ?>>
                <?php foreach ($menu as $section): ?>
                    <a href="#<?= e($section['id']) ?>"><?= e($section['title']) ?></a>
                <?php endforeach; ?>
            </nav>
            <nav class="menu-jump" aria-label="Lunch sections" data-jump="lunch"<?= $activeTab === 'lunch' ? '' : ' hidden' ?>>
                <?php foreach ($lunchMenu as $section): ?>
                    <a href="#<?= e($section['id']) ?>"><?= e($section['title']) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>

    <div class="container">
        <ul class="menu-legend" aria-label="Key">
            <li><span class="mi-icon is-veg"><?= icon('leaf') ?></span> Vegetarian</li>
            <li><span class="mi-icon is-sea"><?= icon('wave') ?></span> Seafood</li>
            <li><span class="mi-icon is-chef"><?= icon('chef') ?></span> Chef’s recommendation</li>
            <li><?= spice_meter(3) ?> Spice, out of five</li>
        </ul>

        <div class="menu-panel" id="panel-carte" role="tabpanel" aria-labelledby="tab-carte"<?= $activeTab === 'carte' ? '' : ' hidden' ?>>
            <div class="menu-layout">
                <?php render_menu_sections($menu, $allergenKey, $sectionPhotos); ?>
            </div>
            <p class="service-note"><?= e($serviceNote) ?></p>
        </div>

        <div class="menu-panel" id="panel-lunch" role="tabpanel" aria-labelledby="tab-lunch"<?= $activeTab === 'lunch' ? '' : ' hidden' ?>>
            <span id="lunch" class="anchor" aria-hidden="true"></span>
            <div class="lunch-banner">
                <p class="eyebrow">Lunch menu</p>
                <p class="lunch-banner-time"><?= e($lunchHours) ?></p>
                <p>Fresh from the wok, made with care.</p>
            </div>
            <div class="menu-layout">
                <?php render_menu_sections($lunchMenu, $allergenKey, $sectionPhotos); ?>
            </div>
        </div>

        <p class="veg-empty" data-veg-empty hidden>No vegetarian dishes in this section.</p>

        <section class="allergen-key" aria-labelledby="allergen-title">
            <h2 id="allergen-title">Allergen information</h2>
            <p>The numbers beside each dish show its allergens. Please let our team know about any allergies or dietary requirements before ordering.</p>
            <dl>
                <?php foreach ($allergenKey as $code => $label): ?>
                    <div><dt><?= e($code) ?></dt><dd><?= e($label) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </section>
    </div>
</div>

<section class="section cta-band">
    <?php render_arches('arches cta-arches'); ?>
    <div class="container cta-inner">
        <p class="eyebrow">Hungry already?</p>
        <h2>Save a seat at the table</h2>
        <div class="cta-actions">
            <?php if (booking_online()): ?>
                <a class="btn btn-gold" href="booking.php">Book a table</a>
                <?php if ($telHref): ?><a class="btn btn-ghost" href="<?= e($telHref) ?>">Call <?= e($site['phone']) ?></a><?php endif; ?>
            <?php elseif ($telHref): ?>
                <a class="btn btn-gold" href="<?= e($telHref) ?>">Call <?= e($site['phone']) ?> to book</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
