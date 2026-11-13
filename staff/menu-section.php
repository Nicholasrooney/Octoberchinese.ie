<?php
/*
 * Edit a menu section's heading and intro, hide it, or add a new section
 * (for example a new click and collect lunch section).
 */

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/menu-data.php';
staff_require_login();
staff_require_database();

$built   = menu_build($menuTyped, $lunchMenuTyped, true);
$isNew   = isset($_GET['new']) || isset($_POST['new']);
$key     = (string) ($_GET['key'] ?? $_POST['key'] ?? '');
$menuKey = ($_GET['m'] ?? $_POST['m'] ?? 'alacarte') === 'lunch' ? 'lunch' : 'alacarte';

if ($isNew) {
    $sec = ['key' => '', 'menu' => $menuKey, 'title' => '', 'cn' => '', 'note' => '', 'intro' => '', 'hidden' => false, 'edited' => false, 'is_new' => true, 'items' => []];
} else {
    $sec = menu_find_section($built, $key);
    if (!$sec) {
        staff_flash('That section was not found.');
        header('Location: menu.php', true, 303);
        exit;
    }
    $menuKey = $sec['menu'];
}
$back  = 'menu.php?m=' . $menuKey . ($sec['key'] ? '#' . rawurlencode($sec['key']) : '');
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!staff_check_token($_POST['token'] ?? null)) {
        staff_flash('That action expired. Try again.');
        header('Location: ' . $back, true, 303);
        exit;
    }
    if (($_POST['action'] ?? '') === 'reset' && !$isNew) {
        menu_reset_section($sec['key']);
        staff_flash($sec['is_new'] ? 'Section deleted, with the dishes added to it.' : 'Section is back to how it was on the printed menu.');
        header('Location: menu.php?m=' . $menuKey, true, 303);
        exit;
    }
    $f = ['title' => $_POST['title'] ?? '', 'cn' => $_POST['cn'] ?? '', 'note' => $_POST['note'] ?? '',
          'intro' => $_POST['intro'] ?? '', 'hidden' => !empty($_POST['hidden'])];
    $saveKey = $isNew ? menu_new_key($menuKey) : $sec['key'];
    $error   = menu_save_section($saveKey, $menuKey, $f, $isNew || $sec['is_new'], staff_actor());
    if (!$error) {
        staff_flash($isNew ? 'Section added. Now add its dishes with “+ Add dish”.' : 'Section saved.');
        header('Location: menu.php?m=' . $menuKey . '#' . rawurlencode($saveKey), true, 303);
        exit;
    }
    $sec = array_merge($sec, $f);
}

staff_head($isNew ? 'Add section' : 'Edit section', 'menu');
?>

<p><a href="<?= e($back) ?>">← Back to the menu</a></p>

<form class="card form" method="post" action="menu-section.php">
    <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
    <input type="hidden" name="m" value="<?= e($menuKey) ?>">
    <?php if ($isNew): ?><input type="hidden" name="new" value="1"><?php else: ?><input type="hidden" name="key" value="<?= e($sec['key']) ?>"><?php endif; ?>

    <h1><?= $isNew ? 'Add a section' : 'Edit section' ?></h1>
    <p class="sub"><?= e(MENU_NAMES[$menuKey]) ?></p>
    <?php if ($error): ?><p class="err" role="alert"><?= e($error) ?></p><?php endif; ?>

    <div class="row">
        <div class="field">
            <label for="title">Title</label>
            <input id="title" name="title" type="text" required maxlength="120" value="<?= e($sec['title']) ?>" placeholder="e.g. Rice Bowls">
        </div>
        <div class="field">
            <label for="cn">Chinese title <span class="opt">(optional)</span></label>
            <input id="cn" name="cn" type="text" maxlength="60" value="<?= e($sec['cn']) ?>" lang="zh-Hans">
        </div>
    </div>
    <div class="field">
        <label for="note">Small note <span class="opt">(optional, e.g. “6 pieces”)</span></label>
        <input id="note" name="note" type="text" maxlength="120" value="<?= e($sec['note']) ?>">
    </div>
    <div class="field">
        <label for="intro">Intro <span class="opt">(optional, a line under the title)</span></label>
        <textarea id="intro" name="intro" rows="2" maxlength="500"><?= e($sec['intro']) ?></textarea>
    </div>
    <label class="check"><input type="checkbox" name="hidden" value="1" <?= $sec['hidden'] ? 'checked' : '' ?>> Hide this whole section from the website</label>

    <div class="form-do">
        <button class="btn-p" type="submit"><?= $isNew ? 'Add section' : 'Save' ?></button>
        <a class="btn-s" href="<?= e($back) ?>">Cancel</a>
    </div>
</form>

<?php if (!$isNew && ($sec['edited'] || $sec['is_new'])): ?>
    <form class="card" method="post" action="menu-section.php"
          data-confirm="<?= $sec['is_new'] ? 'Delete this section and every dish added to it?' : 'Undo changes to this section heading?' ?>">
        <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
        <input type="hidden" name="key" value="<?= e($sec['key']) ?>">
        <button class="btn-s btn-cancel" name="action" value="reset"><?= $sec['is_new'] ? 'Delete section' : 'Undo changes' ?></button>
    </form>
<?php endif; ?>

<?php staff_foot(); ?>
