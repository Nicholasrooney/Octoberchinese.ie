<?php
/*
 * Edit one dish (or add a new one): name, Chinese name, price, description,
 * allergens, vegetarian, spice, photo, and whether it shows on the website.
 */

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/menu-data.php';
staff_require_login();
staff_require_database();

$built = menu_build($menuTyped, $lunchMenuTyped, true);
$isNew = isset($_GET['new']) || isset($_POST['new']);
$key   = (string) ($_GET['key'] ?? $_POST['key'] ?? '');
$sectionKey = (string) ($_GET['section'] ?? $_POST['section'] ?? '');

if ($isNew) {
    $sec = menu_find_section($built, $sectionKey);
    if (!$sec) {
        staff_flash('That section was not found.');
        header('Location: menu.php', true, 303);
        exit;
    }
    $item = dish('', '', '', []);
    $item += ['key' => '', 'photo' => '', 'hidden' => false, 'edited' => false, 'is_new' => true];
} else {
    $found = menu_find_item($built, $key);
    if (!$found) {
        staff_flash('That dish was not found. It may have been deleted.');
        header('Location: menu.php', true, 303);
        exit;
    }
    ['item' => $item, 'section' => $sec] = $found;
}
$back  = 'menu.php?m=' . $sec['menu'] . '#' . rawurlencode($sec['key']);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!staff_check_token($_POST['token'] ?? null)) {
        staff_flash('That action expired. Try again.');
        header('Location: ' . $back, true, 303);
        exit;
    }
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'reset' && !$isNew) {
        menu_reset_item($item['key']);
        staff_flash($item['is_new'] ? '“' . $item['name'] . '” deleted from the menu.' : '“' . $item['name'] . '” is back to how it was on the printed menu.');
        header('Location: ' . $back, true, 303);
        exit;
    }

    $f = [
        'name'      => $_POST['name'] ?? '',
        'cn'        => $_POST['cn'] ?? '',
        'price'     => $_POST['price'] ?? '',
        'desc'      => $_POST['desc'] ?? '',
        'allergens' => (array) ($_POST['allergens'] ?? []),
        'veg'       => !empty($_POST['veg']),
        'spice'     => (int) ($_POST['spice'] ?? 0),
        'hidden'    => !empty($_POST['hidden']),
    ];

    // Photo: a new upload replaces the old one; "remove" takes it off.
    if (!empty($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        [$photoId, $error] = photo_store_upload($_FILES['photo'], 'Dish: ' . trim((string) $f['name']), staff_actor(), 1600);
        if ($photoId) {
            $f['photo_id'] = $photoId;
        }
    } elseif (!empty($_POST['remove_photo'])) {
        $f['photo_id'] = 0;   // 0 = no photo, even if the printed menu had one
    }

    if (!$error) {
        $saveKey = $isNew ? menu_new_key($sec['key']) : $item['key'];
        $error   = menu_save_item($saveKey, $sec['menu'], $sec['key'], $f, $isNew || $item['is_new'], staff_actor());
        if (!$error) {
            staff_flash('“' . trim((string) $f['name']) . '” ' . ($isNew ? 'added' : 'saved') . '. The website shows it now' . (!empty($f['hidden']) ? ' (hidden from customers).' : '.'));
            header('Location: ' . $back, true, 303);
            exit;
        }
    }
    // Keep what was typed so nothing is lost when there is an error.
    $item = array_merge($item, ['name' => $f['name'], 'cn' => $f['cn'], 'price' => $f['price'], 'desc' => $f['desc'],
        'allergens' => $f['allergens'], 'veg' => $f['veg'], 'spice' => $f['spice'], 'hidden' => $f['hidden']]);
}

staff_head($isNew ? 'Add dish' : 'Edit ' . $item['name'], 'menu');
?>

<p><a href="<?= e($back) ?>">← Back to the menu</a></p>

<form class="card form" method="post" enctype="multipart/form-data" action="menu-item.php">
    <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
    <input type="hidden" name="section" value="<?= e($sec['key']) ?>">
    <?php if ($isNew): ?><input type="hidden" name="new" value="1"><?php else: ?><input type="hidden" name="key" value="<?= e($item['key']) ?>"><?php endif; ?>

    <h1><?= $isNew ? 'Add a dish' : 'Edit dish' ?></h1>
    <p class="sub"><?= e(MENU_NAMES[$sec['menu']]) ?> · <?= e($sec['title']) ?></p>
    <?php if ($error): ?><p class="err" role="alert"><?= e($error) ?></p><?php endif; ?>

    <div class="row">
        <div class="field">
            <label for="name">Name</label>
            <input id="name" name="name" type="text" required maxlength="160" value="<?= e($item['name']) ?>">
        </div>
        <div class="field">
            <label for="cn">Chinese name <span class="opt">(optional)</span></label>
            <input id="cn" name="cn" type="text" maxlength="80" value="<?= e($item['cn']) ?>" lang="zh-Hans">
        </div>
    </div>

    <div class="row">
        <div class="field field-small">
            <label for="price">Price (€)</label>
            <input id="price" name="price" type="text" inputmode="decimal" maxlength="10" value="<?= e($item['price']) ?>" placeholder="12.50">
            <?php if (!empty($item['more_prices'])): ?>
                <p class="sub">This is the <?= e($item['price_label'] ?: 'main') ?> price. Other sizes stay as printed:
                    <?php foreach ($item['more_prices'] as [$l, $p]) echo e($l . ' €' . $p) . ' '; ?></p>
            <?php endif; ?>
        </div>
        <div class="field field-small">
            <label for="spice">Spice (chillies)</label>
            <select id="spice" name="spice" style="min-width: 9em">
                <?php for ($i = 0; $i <= 5; $i++): ?><option value="<?= $i ?>" <?= (int) $item['spice'] === $i ? 'selected' : '' ?>><?= $i === 0 ? 'Not spicy' : $i . ' of 5' ?></option><?php endfor; ?>
            </select>
        </div>
    </div>

    <div class="field">
        <label for="desc">Description <span class="opt">(one line under the name)</span></label>
        <textarea id="desc" name="desc" rows="2" maxlength="500"><?= e($item['desc']) ?></textarea>
    </div>

    <fieldset class="field">
        <legend>Allergens</legend>
        <p class="sub">Tick every allergen in the dish. Check with the kitchen if unsure: this is what customers rely on.</p>
        <div class="check-grid">
            <?php foreach ($allergenKey as $code => $label): ?>
                <label class="check"><input type="checkbox" name="allergens[]" value="<?= e($code) ?>" <?= in_array((string) $code, array_map('strval', $item['allergens']), true) ? 'checked' : '' ?>> <strong><?= e($code) ?></strong> <?= e($label) ?></label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <label class="check"><input type="checkbox" name="veg" value="1" <?= $item['veg'] ? 'checked' : '' ?>> Vegetarian (shows the leaf icon)</label>

    <fieldset class="field">
        <legend>Photo</legend>
        <?php if ($item['photo']): ?>
            <img class="menu-edit-photo" src="<?= e($item['photo']) ?>" alt="Current photo of <?= e($item['name']) ?>">
            <label class="check"><input type="checkbox" name="remove_photo" value="1"> Remove this photo</label>
        <?php endif; ?>
        <label for="photo"><?= $item['photo'] ? 'Replace with a new photo' : 'Add a photo' ?> <span class="opt">(JPG, PNG or WebP, from your phone is fine)</span></label>
        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
    </fieldset>

    <label class="check"><input type="checkbox" name="hidden" value="1" <?= $item['hidden'] ? 'checked' : '' ?>> Hide from the website <span class="opt">(sold out, off the menu for now)</span></label>

    <div class="form-do">
        <button class="btn-p" type="submit" name="action" value="save"><?= $isNew ? 'Add dish' : 'Save' ?></button>
        <a class="btn-s" href="<?= e($back) ?>">Cancel</a>
    </div>
</form>

<?php if (!$isNew && ($item['edited'] || $item['is_new'])): ?>
    <form class="card" method="post" action="menu-item.php"
          data-confirm="<?= $item['is_new'] ? 'Delete this dish from the menu?' : 'Undo every change to this dish and go back to the printed menu?' ?>">
        <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
        <input type="hidden" name="key" value="<?= e($item['key']) ?>">
        <input type="hidden" name="section" value="<?= e($sec['key']) ?>">
        <p class="sub"><?= $item['is_new'] ? 'This dish was added by staff.' : 'This dish has been changed from the printed menu.' ?></p>
        <button class="btn-s btn-cancel" name="action" value="reset"><?= $item['is_new'] ? 'Delete dish' : 'Undo all changes' ?></button>
    </form>
<?php endif; ?>

<?php staff_foot(); ?>
