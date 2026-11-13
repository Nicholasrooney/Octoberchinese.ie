<?php
/*
 * Site photos: replace any picture on the website (the home page, menu
 * headers, gallery, /october/ pages) with a new upload, or put the original
 * back. Dish photos are changed on the dish itself, in Menu.
 */

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/menu-edits.php';
staff_require_login();
staff_require_database();

$images = photo_site_images();
$valid  = array_column($images, 'path');
$swaps  = photo_swaps();
$error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!staff_check_token($_POST['token'] ?? null)) {
        staff_flash('That action expired. Try again.');
        header('Location: photos.php', true, 303);
        exit;
    }
    $path = (string) ($_POST['path'] ?? '');
    $anchor = '#p-' . md5($path);
    if (!in_array($path, $valid, true)) {
        staff_flash('That photo was not found.');
    } elseif (($_POST['action'] ?? '') === 'restore') {
        photo_set_swap($path, null, staff_actor());
        staff_flash('The original photo is back on the website.');
    } else {
        [$id, $error] = photo_store_upload($_FILES['photo'] ?? [], 'Replaces ' . $path, staff_actor());
        if ($id) {
            photo_set_swap($path, $id, staff_actor());
            staff_flash('Photo replaced. The website shows the new one now.');
        } else {
            staff_flash($error);
        }
    }
    header('Location: photos.php' . $anchor, true, 303);
    exit;
}

$showAll = isset($_GET['all']);
$flash   = staff_flash();
staff_head('Photos', 'photos', true);
?>

<?php if ($flash): ?><p class="flash"><?= e($flash) ?></p><?php endif; ?>

<div class="orders-top">
    <div>
        <h1>Site photos</h1>
        <p class="sub">Replace any picture on the website. To put a photo on a dish, open the dish in <a href="menu.php">Menu</a>.</p>
    </div>
    <a class="btn-s" href="photos.php<?= $showAll ? '' : '?all' ?>"><?= $showAll ? 'Only photos in use' : 'Show every photo' ?></a>
</div>

<div class="photo-grid">
    <?php foreach ($images as $img): if (!$showAll && !$img['pages'] && !isset($swaps[$img['path']])) continue;
        $swapped = isset($swaps[$img['path']]);
        $src     = $swapped ? photo_url($swaps[$img['path']]) : '../' . $img['path']; ?>
        <article class="card photo-card<?= $swapped ? ' is-swapped' : '' ?>" id="p-<?= md5($img['path']) ?>">
            <a href="<?= e($src) ?>" target="_blank" rel="noopener"><img src="<?= e($src) ?>" alt="" loading="lazy"></a>
            <p class="photo-name"><?= e(substr($img['path'], 7)) ?></p>
            <p class="sub">
                <?php if ($swapped): ?><span class="tag tag-o-ready">Replaced</span><?php endif; ?>
                <?= $img['pages'] ? 'Used on: ' . e(implode(', ', array_map(fn($p) => str_replace(['.php', 'index', 'october/'], ['', 'home', 'October '], $p), $img['pages']))) : 'Not used on a page' ?>
            </p>
            <form method="post" enctype="multipart/form-data" action="photos.php">
                <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
                <input type="hidden" name="path" value="<?= e($img['path']) ?>">
                <label class="sr-only" for="f-<?= md5($img['path']) ?>">New photo for <?= e($img['path']) ?></label>
                <input id="f-<?= md5($img['path']) ?>" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
                <div class="form-do"><button class="btn-p" type="submit" name="action" value="replace">Replace</button></div>
            </form>
            <?php if ($swapped): ?>
                <form method="post" action="photos.php" data-confirm="Put the original photo back?">
                    <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
                    <input type="hidden" name="path" value="<?= e($img['path']) ?>">
                    <button class="btn-s" name="action" value="restore">Put the original back</button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>

<?php staff_foot(); ?>
