<?php
/*
 * Tests for the staff menu editor and photo swaps. Run from the site folder:
 *
 *     php db/test-menu-edits.php
 */
require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../includes/menu-edits.php';

fresh_database();
menu_edits_rows(true);
photo_swaps(true);

$load = function () {
    $menu = $lunchMenu = [];
    require __DIR__ . '/../includes/menu-data.php';
    return [$menuTyped, $lunchMenuTyped, $menu, $lunchMenu, $allergenKey];
};
[$typed, $typedLunch, $menu, $lunch, $allergenKey] = $load();
$GLOBALS['allergenKey'] = $allergenKey;

section('Nothing changed');
check('public menu matches the typed-in one', count($menu), count($typed));
$built = menu_build($typed, $typedLunch, true);
$bass  = null;
foreach ($built['alacarte'] as $sec) foreach ($sec['items'] as $d) if ($d['name'] === 'Steamed Sea Bass') { $bass = $d; $bassSec = $sec; }
check('dish has a stable key', $bass['key'], 'alacarte:october-special:steamed-sea-bass');
check('price as typed', $bass['price'], '22.50');

section('Changing a price');
check('bad price refused', menu_save_price($bass, 'alacarte', $bassSec['key'], 'abc', 'tester') !== null, true);
check('saved', menu_save_price($bass, 'alacarte', $bassSec['key'], '23.5', 'tester'), null);
[, , $menu] = $load();
$found = menu_find_item(menu_build($typed, $typedLunch, true), $bass['key']);
check('new price shows, tidied', $found['item']['price'], '23.50');
check('rest of the dish unchanged', $found['item']['desc'], $bass['desc']);
check('allergens kept', $found['item']['allergens'], $bass['allergens']);

section('Hiding and editing');
menu_save_item($bass['key'], 'alacarte', $bassSec['key'], ['name' => 'Steamed Sea Bass', 'price' => '23.50', 'desc' => 'Ginger and soy.',
    'allergens' => ['4', '11', 'XX'], 'spice' => 9, 'hidden' => true], false, 'tester');
$f = menu_find_item(menu_build($typed, $typedLunch, true), $bass['key'])['item'];
check('description changed', $f['desc'], 'Ginger and soy.');
check('unknown allergen codes dropped', $f['allergens'], ['4', '11']);
check('spice capped at 5', $f['spice'], 5);
$public = menu_build($typed, $typedLunch);
$names  = [];
foreach ($public['alacarte'] as $sec) foreach ($sec['items'] as $d) $names[] = $d['name'];
check('hidden dish off the website', in_array('Steamed Sea Bass', $names, true), false);
menu_reset_item($bass['key']);
check('undo puts it back as printed', menu_find_item(menu_build($typed, $typedLunch, true), $bass['key'])['item']['price'], '22.50');

section('Adding sections and dishes');
$sKey = menu_new_key('lunch');
check('section needs a title', menu_save_section($sKey, 'lunch', ['title' => ''], true, 'tester') !== null, true);
menu_save_section($sKey, 'lunch', ['title' => 'Click & Collect Lunch', 'intro' => 'For collection'], true, 'tester');
$b = menu_build($typed, $typedLunch);
check('empty new section not shown publicly', menu_find_section($b, $sKey), null);
$iKey = menu_new_key($sKey);
check('dish needs a name', menu_save_item($iKey, 'lunch', $sKey, ['name' => ''], true, 'tester') !== null, true);
menu_save_item($iKey, 'lunch', $sKey, ['name' => 'Chicken Rice Box', 'price' => '11', 'allergens' => ['1W']], true, 'tester');
$b = menu_build($typed, $typedLunch);
$s = menu_find_section($b, $sKey);
check('new section shows once it has a dish', $s['title'], 'Click & Collect Lunch');
check('new dish in it', $s['items'][0]['name'] . ' €' . $s['items'][0]['price'], 'Chicken Rice Box €11.00');
check('new section is last on the lunch menu', end($b['lunch'])['key'], $sKey);

section('Orders use the edited lunch menu');
require_once __DIR__ . '/../includes/orders.php';
$GLOBALS['ordering']['menus'] = ['lunch'];
$cat = order_catalogue();
$menusInCatalogue = array_unique(array_column($cat['items'], 'menu'));
check('only lunch dishes orderable', array_values($menusInCatalogue), ['lunch']);
check('staff-added dish is orderable', in_array('Chicken Rice Box', array_column($cat['items'], 'name'), true), true);
menu_reset_section($sKey);
check('deleting a new section deletes its dishes', menu_edits_rows(true)['items'], []);

section('Collection times: lunch only, until 16:00');
$GLOBALS['ordering']['enabled'] = true;
$GLOBALS['ordering']['collect_until'] = '16:00';
$GLOBALS['ordering']['max_orders_per_slot'] = 0;
$tz  = new DateTimeZone('Europe/Dublin');
$wed = new DateTimeImmutable('next wednesday 11:00', $tz);
$slots = order_slots($wed, []);
check('Wednesday first collection after opening + prep', $slots[0], '13:00');
check('last collection 16:00', end($slots), '16:00');
$tue = new DateTimeImmutable('next tuesday 11:00', $tz);
check('Tuesday (opens 15:30): only 16:00', order_slots($tue, []), ['16:00']);
$mon = new DateTimeImmutable('next monday 11:00', $tz);
check('Monday closed', order_slots($mon, []), []);
check('after 16:00 nothing', order_slots($wed->setTime(16, 1), []), []);
check('closed message mentions lunch', strpos((string) order_closed_message($wed->setTime(16, 30)), 'lunch') !== false, true);
$GLOBALS['ordering']['enabled'] = false;

section('Edit form keeps allergens');
// The edit page ticks boxes by comparing codes; PHP turns '4' keys into ints.
$ticked = [];
foreach ($allergenKey as $code => $label) if (in_array((string) $code, array_map('strval', $bass['allergens']), true)) $ticked[] = (string) $code;
check('every printed allergen is ticked on the form', $ticked, array_map('strval', $bass['allergens']));
menu_save_item($bass['key'], 'alacarte', $bassSec['key'], ['name' => 'Steamed Sea Bass', 'price' => '22.50', 'allergens' => $ticked], false, 'tester');
check('saving the form keeps them', menu_find_item(menu_build($typed, $typedLunch, true), $bass['key'])['item']['allergens'], $bass['allergens']);
menu_reset_item($bass['key']);

section('Photos');
$tmp = tempnam(sys_get_temp_dir(), 'ph') . '.jpg';
$im  = imagecreatetruecolor(3000, 2000);
imagefill($im, 0, 0, imagecolorallocate($im, 200, 60, 20));
imagejpeg($im, $tmp, 90);
[$pid, $perr] = photo_store_file($tmp, 'test', 'tester', 1600);
check('stored', $perr, null);
$row = db()->query("SELECT width, height, mime FROM photos WHERE id = $pid")->fetch();
check('shrunk to 1600 on the long edge', [(int) $row['width'], (int) $row['height'], $row['mime']], [1600, 1067, 'image/jpeg']);
file_put_contents($tmp, 'not an image');
check('non-image refused', photo_store_file($tmp, 'x', 'tester')[0], null);

menu_save_item($bass['key'], 'alacarte', $bassSec['key'], ['name' => 'Steamed Sea Bass', 'price' => '22.50', 'photo_id' => $pid], false, 'tester');
check('dish photo url', menu_find_item(menu_build($typed, $typedLunch, true), $bass['key'])['item']['photo'], '/photo.php?id=' . $pid);
menu_save_item($bass['key'], 'alacarte', $bassSec['key'], ['name' => 'Steamed Sea Bass', 'price' => '22.50', 'photo_id' => 0], false, 'tester');
check('photo removed', menu_find_item(menu_build($typed, $typedLunch, true), $bass['key'])['item']['photo'], '');

photo_set_swap('images/interior.jpg', $pid, 'tester');
$html = '<img src="images/interior.jpg"><img src="../images/interior.jpg"><img src="images/interior-2.jpg">';
check('site photo swapped in the page', photos_rewrite_html($html),
      '<img src="/photo.php?id=' . $pid . '"><img src="/photo.php?id=' . $pid . '"><img src="images/interior-2.jpg">');
photo_set_swap('images/interior.jpg', null, 'tester');
check('original back', photos_rewrite_html($html), $html);
$list = photo_site_images();
check('site photo list finds the home page hero', in_array('images/oct/beef-fillet-steam.jpg', array_column($list, 'path'), true), true);

finish();
