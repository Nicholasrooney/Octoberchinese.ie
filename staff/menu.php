<?php
/*
 * Menu editor: every dish and price on the website, changed here without code.
 *
 * Prices can be changed straight from this list ("Save prices" under each
 * section). "Edit" opens a dish for its name, description, allergens, photo,
 * or to hide it. Sections and dishes can be added. The lunch menu here is the
 * one click and collect sells.
 */

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/menu-data.php';
staff_require_login();
staff_require_database();

$menuKey = ($_GET['m'] ?? $_POST['m'] ?? 'alacarte') === 'lunch' ? 'lunch' : 'alacarte';
$built   = menu_build($menuTyped, $lunchMenuTyped, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!staff_check_token($_POST['token'] ?? null)) {
        staff_flash('That action expired. Try again.');
        header('Location: menu.php?m=' . $menuKey, true, 303);
        exit;
    }
    $sectionKey = (string) ($_POST['section'] ?? '');
    $sec        = menu_find_section($built, $sectionKey);
    $errors     = [];
    $changed    = 0;
    if ($sec && ($_POST['action'] ?? '') === 'prices') {
        foreach ($sec['items'] as $item) {
            if (!isset($_POST['price'][$item['key']])) {
                continue;
            }
            $new = (string) $_POST['price'][$item['key']];
            $was = (string) $item['price'];
            $err = menu_save_price($item, $sec['menu'], $sec['key'], $new, staff_actor());
            if ($err) {
                $errors[] = $err;
            } elseif (menu_clean_price($new) !== $was) {
                $changed++;
            }
        }
        staff_flash($errors
            ? implode(' ', $errors)
            : ($changed ? $changed . ($changed === 1 ? ' price' : ' prices') . ' updated in “' . $sec['title'] . '”. The website shows them now.' : 'No prices changed.'));
    }
    header('Location: menu.php?m=' . $menuKey . '#' . rawurlencode($sectionKey), true, 303);
    exit;
}

$sections = $built[$menuKey];
$flash    = staff_flash();

staff_head('Menu', 'menu', true);
?>

<?php if ($flash): ?><p class="flash"><?= e($flash) ?></p><?php endif; ?>

<div class="orders-top">
    <div>
        <h1>Menu</h1>
        <p class="sub">Change any dish, price or photo. Changes show on the website straight away.</p>
    </div>
    <a class="btn-s" href="photos.php">Change site photos</a>
</div>

<nav class="tabs menu-tabs" aria-label="Which menu">
    <?php foreach (MENU_NAMES as $k => $label): ?>
        <a href="menu.php?m=<?= e($k) ?>" class="<?= $k === $menuKey ? 'on' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($menuKey === 'lunch'): ?>
    <p class="flash flash-warn">
        Click and collect sells the lunch menu, for collection from opening time until <?= e($ordering['collect_until'] ?? '16:00') ?>.
        It is <strong><?= ordering_online() ? 'ON' : 'OFF' ?></strong> on the website<?= ordering_online() ? '' : ' until the lunch menu is ready' ?>.
    </p>
<?php endif; ?>

<p class="sub menu-jump">
    <?php foreach ($sections as $sec): ?>
        <a href="#<?= e(rawurlencode($sec['key'])) ?>"><?= e($sec['title'] ?: 'Untitled') ?></a>
    <?php endforeach; ?>
</p>

<?php foreach ($sections as $sec): ?>
    <section class="card menu-sec<?= $sec['hidden'] ? ' is-hidden' : '' ?>" id="<?= e($sec['key']) ?>">
        <div class="menu-sec-head">
            <div>
                <h2><?= e($sec['title'] ?: 'Untitled section') ?> <?php if ($sec['cn']): ?><span class="menu-cn" lang="zh-Hans"><?= e($sec['cn']) ?></span><?php endif; ?></h2>
                <p class="sub">
                    <?= count($sec['items']) ?> <?= count($sec['items']) === 1 ? 'dish' : 'dishes' ?>
                    <?php if ($sec['hidden']): ?> · <span class="tag tag-o-cancelled">Hidden from website</span><?php endif; ?>
                    <?php if ($sec['is_new']): ?> · <span class="tag tag-o-ready">Added by staff</span><?php endif; ?>
                </p>
            </div>
            <div class="ticket-do">
                <a class="btn-s" href="menu-section.php?key=<?= e(rawurlencode($sec['key'])) ?>">Edit section</a>
                <a class="btn-s btn-go" href="menu-item.php?new=1&amp;section=<?= e(rawurlencode($sec['key'])) ?>">+ Add dish</a>
            </div>
        </div>

        <?php if ($sec['items']): ?>
            <form method="post" action="menu.php">
                <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
                <input type="hidden" name="m" value="<?= e($menuKey) ?>">
                <input type="hidden" name="section" value="<?= e($sec['key']) ?>">
                <input type="hidden" name="action" value="prices">
                <ul class="menu-rows">
                    <?php foreach ($sec['items'] as $d): ?>
                        <li class="menu-row<?= $d['hidden'] ? ' is-hidden' : '' ?>">
                            <a class="menu-thumb" href="menu-item.php?key=<?= e(rawurlencode($d['key'])) ?>" aria-label="Edit <?= e($d['name']) ?>">
                                <?php if ($d['photo']): ?><img src="<?= e($d['photo']) ?>" alt="" loading="lazy"><?php else: ?><span>+ photo</span><?php endif; ?>
                            </a>
                            <div class="menu-row-main">
                                <strong><?= e($d['name']) ?></strong>
                                <?php if ($d['cn']): ?><span class="menu-cn" lang="zh-Hans"><?= e($d['cn']) ?></span><?php endif; ?>
                                <span class="menu-tags">
                                    <?php if ($d['hidden']): ?><span class="tag tag-o-cancelled">Hidden</span><?php endif; ?>
                                    <?php if ($d['is_new']): ?><span class="tag tag-o-ready">New</span><?php elseif ($d['edited']): ?><span class="tag tag-o-accepted">Changed</span><?php endif; ?>
                                    <?php if ($d['more_prices']): ?><span class="tag">also <?php foreach ($d['more_prices'] as [$l, $p]) echo e($l . ' €' . $p) . ' '; ?></span><?php endif; ?>
                                </span>
                            </div>
                            <label class="menu-price">
                                <span class="sr-only">Price of <?= e($d['name']) ?></span>
                                <span aria-hidden="true">€<?= $d['price_label'] ? ' ' . e($d['price_label']) : '' ?></span>
                                <input type="text" inputmode="decimal" name="price[<?= e($d['key']) ?>]" value="<?= e($d['price']) ?>" size="6" autocomplete="off">
                            </label>
                            <a class="btn-s menu-edit" href="menu-item.php?key=<?= e(rawurlencode($d['key'])) ?>">Edit</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="form-do"><button class="btn-p" type="submit">Save prices</button></div>
            </form>
        <?php else: ?>
            <p class="sub">No dishes yet. Press “+ Add dish”.</p>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<p><a class="btn-p" href="menu-section.php?new=1&amp;m=<?= e($menuKey) ?>">+ Add a section to the <?= e(strtolower(MENU_NAMES[$menuKey])) ?></a></p>

<?php staff_foot(); ?>
