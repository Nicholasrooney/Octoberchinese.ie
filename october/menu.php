<?php
/*
 * October, second design: menu page in the style of the China Hunan site.
 * Dishes, prices and allergens come from includes/menu-data.php, the same
 * file the main menu page uses.
 */
require_once __DIR__ . '/../includes/october-theme.php';
require_once __DIR__ . '/../includes/menu-data.php';

$tabs = [
    'alacarte'  => ['À la carte', 'October À la carte menu', $menu,
                    'Sichuan, Hunan and Cantonese dishes, made for the middle of the table. Order a few, pass them round, and share.'],
    'lunch'     => ['Lunch', 'October Lunch menu · ' . $lunchHours, $lunchMenu,
                    'Signature rice bowls, noodle soups and handmade dumplings, served from 12:30 to 16:00.'],
];
$current = isset($_GET['tab'], $tabs[$_GET['tab']]) ? $_GET['tab'] : 'alacarte';

function oct_dish(array $d, array $allergenKey): void
{
    ?>
    <li class="oc-dish">
        <div class="oc-dish-top">
            <h4><?= e($d['name']) ?>
                <?php if ($d['chef']): ?><span class="oc-tag" title="Chef’s recommendation"><?= icon('chef') ?></span><?php endif; ?>
                <?php if ($d['veg']): ?><span class="oc-tag oc-tag-veg" title="Vegetarian">V</span><?php endif; ?>
            </h4>
            <?php if (show_prices() && $d['price'] !== ''): ?>
                <span class="oc-dish-price">
                    <?php if ($d['price_label']): ?><small><?= e($d['price_label']) ?></small><?php endif; ?>€<?= e(fmt_price($d['price'])) ?>
                    <?php foreach ($d['more_prices'] as [$label, $price]): ?>
                        <span class="oc-dish-more"><small><?= e($label) ?></small>€<?= e(fmt_price($price)) ?></span>
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>
        </div>
        <?php if ($d['cn']): ?><p class="oc-dish-cn" lang="zh-Hans"><?= e($d['cn']) ?></p><?php endif; ?>
        <?php if ($d['desc']): ?><p class="oc-dish-desc"><?= e($d['desc']) ?></p><?php endif; ?>
        <div class="oc-dish-meta">
            <?= spice_meter((int) $d['spice']) ?>
            <?php if ($d['allergens']): ?>
                <span class="oc-allergens" title="<?= e(implode(', ', array_map(fn($a) => $allergenKey[$a] ?? $a, $d['allergens']))) ?>">Allergens: <?= e(implode(', ', $d['allergens'])) ?></span>
            <?php endif; ?>
        </div>
    </li>
    <?php
}

oct_header(
    'Menu | October Chinese Dining, Dún Laoghaire',
    'The October menu: Sichuan, Hunan and Cantonese dishes for sharing, handmade dumplings, and lunch from 12:30. Allergens listed beside every dish.',
    'menu'
);
?>

<section class="oc-pagehero oc-pagehero-menu">
    <img class="oc-hero-bg" src="<?= OCT_ROOT ?>images/oct/beef-fillet-low.jpg" alt="" aria-hidden="true">
    <div class="oc-wrap oc-center">
        <p class="oc-pagehero-cn" lang="zh-Hans">菜单</p>
        <h1>Our menus</h1>
        <div class="oc-tabs" role="tablist" aria-label="Menus">
            <?php foreach ($tabs as $key => [$label]): ?>
                <a role="tab" href="?tab=<?= e($key) ?>" id="tab-<?= e($key) ?>" aria-controls="panel-<?= e($key) ?>"
                   aria-selected="<?= $key === $current ? 'true' : 'false' ?>" data-oc-tab="<?= e($key) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <a role="tab" href="?tab=allergens" id="tab-allergens" aria-controls="panel-allergens" aria-selected="false" data-oc-tab="allergens">Allergens</a>
        </div>
    </div>
</section>

<div class="oc-menu oc-peach">
    <?php foreach ($tabs as $key => [$label, $title, $sections, $intro]): ?>
        <section class="oc-panel" id="panel-<?= e($key) ?>" role="tabpanel" aria-labelledby="tab-<?= e($key) ?>" <?= $key === $current ? '' : 'hidden' ?>>
            <div class="oc-wrap oc-narrow oc-center">
                <h2 class="oc-menu-title"><?= e($title) ?></h2>
                <p><?= e($intro) ?></p>
                <p class="oc-small">Parties of 5 or more are subject to a 10% service charge, all of which goes directly to the staff.</p>
                <p class="oc-small">Please tell a member of staff about any allergies or dietary needs before ordering. Allergen numbers are listed beside every dish.</p>
            </div>
            <div class="oc-wrap">
                <?php foreach ($sections as $sec): ?>
                    <div class="oc-menu-section" id="<?= e($sec['id']) ?>">
                        <header class="oc-menu-head">
                            <p class="oc-menu-cn" lang="zh-Hans"><?= e($sec['cn']) ?></p>
                            <h3><?= e($sec['title']) ?><?php if ($sec['note']): ?> <small>(<?= e($sec['note']) ?>)</small><?php endif; ?></h3>
                            <?php if ($sec['intro']): ?><p><?= e($sec['intro']) ?></p><?php endif; ?>
                        </header>
                        <ul class="oc-dishes">
                            <?php foreach ($sec['items'] as $d) oct_dish($d, $allergenKey); ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <section class="oc-panel" id="panel-allergens" role="tabpanel" aria-labelledby="tab-allergens" hidden>
        <div class="oc-wrap oc-narrow oc-center">
            <h2 class="oc-menu-title">Allergen key</h2>
            <p>The numbers beside each dish refer to the list below. Please tell a member of staff about any allergies before ordering.</p>
            <dl class="oc-allergen-key">
                <?php foreach ($allergenKey as $code => $name): ?>
                    <div><dt><?= e($code) ?></dt><dd><?= e($name) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </div>
    </section>
</div>

<?php oct_footer(); ?>
