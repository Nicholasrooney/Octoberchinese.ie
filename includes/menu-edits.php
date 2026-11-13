<?php
/*
 * Staff menu editor and photo swaps.
 *
 * includes/menu-data.php is the printed menu, typed in once. Staff change it
 * from staff/menu.php without touching code: every change is a row in
 * menu_edits (dishes) or menu_sections (sections), and a row always wins over
 * the typed-in version. New dishes and sections live only in those tables.
 *
 * Photos staff upload are stored in the database (photos), never on disk, so a
 * deploy from Git cannot wipe them. photo.php serves them. A photo can be put
 * on a dish, or swapped in for any picture on the site (photo_swaps), which
 * photos_start_buffer() does by rewriting the page's HTML on the way out.
 *
 * If the database is missing or down, everything here quietly falls back to
 * the typed-in menu and the original pictures: the site never breaks.
 */

require_once __DIR__ . '/db.php';

const MENU_NAMES = ['alacarte' => 'À la carte', 'lunch' => 'Lunch menu'];

/* ------------------------------------------------------------ storage */

function menu_edits_create_tables(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql    = (string) file_get_contents(__DIR__ . '/../db/schema.sql');
    foreach (db_sql_statements($sql, $driver) as $statement) {
        if (preg_match('/CREATE TABLE IF NOT EXISTS (menu_edits|menu_sections|photos|photo_swaps)\b/i', $statement)) {
            $pdo->exec($statement);
        }
    }
}

/** Runs a query, creating the tables once and retrying if they are missing. */
function menu_edits_query(callable $work)
{
    $pdo = db();
    try {
        return $work($pdo);
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (preg_match('/menu_edits|menu_sections|photos|photo_swaps/i', $msg)
            && (stripos($msg, "doesn't exist") !== false || stripos($msg, 'no such table') !== false)) {
            menu_edits_create_tables($pdo);
            return $work($pdo);
        }
        throw $e;
    }
}

/** All edit rows, loaded once per request. Empty if there is no database. */
function menu_edits_rows(bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }
    try {
        if (!db_ready()) {
            return $cache = ['items' => [], 'sections' => []];
        }
        $cache = menu_edits_query(function (PDO $pdo) {
            $items = [];
            foreach ($pdo->query('SELECT * FROM menu_edits ORDER BY sort_order, item_key') as $r) {
                $items[$r['item_key']] = $r;
            }
            $sections = [];
            foreach ($pdo->query('SELECT * FROM menu_sections ORDER BY sort_order, section_key') as $r) {
                $sections[$r['section_key']] = $r;
            }
            return ['items' => $items, 'sections' => $sections];
        });
    } catch (Throwable $e) {
        error_log('Menu edits unavailable: ' . $e->getMessage());
        $cache = ['items' => [], 'sections' => []];
    }
    return $cache;
}

/* ------------------------------------------------------------ keys */

function menu_slug(string $s): string
{
    $s = strtolower(trim($s));
    $s = strtr($s, ['&' => ' and ', 'é' => 'e', 'è' => 'e', 'à' => 'a']);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'item';
}

/** Section key: menu + the section id from menu-data.php, e.g. lunch:lunch-bowls. */
function menu_section_key(string $menuKey, string $sectionId): string
{
    return $menuKey . ':' . $sectionId;
}

/* ------------------------------------------------------------ building the menu */

/**
 * The menu with every staff change applied.
 *
 * Each section gains 'key', 'menu', 'hidden', 'is_new'; each dish gains 'key',
 * 'photo' (a URL or ''), 'hidden', 'edited', 'is_new'. With $includeHidden
 * false (the website), hidden sections and dishes are left out.
 *
 * @return array{alacarte: array, lunch: array}
 */
function menu_build(array $menu, array $lunchMenu, bool $includeHidden = false): array
{
    $rows = menu_edits_rows();
    $out  = [];

    foreach (['alacarte' => $menu, 'lunch' => $lunchMenu] as $menuKey => $sections) {
        $built = [];

        foreach ($sections as $sec) {
            $sKey = menu_section_key($menuKey, $sec['id']);
            $sec  = menu_apply_section($sec, $rows['sections'][$sKey] ?? null);
            $sec['key']    = $sKey;
            $sec['menu']   = $menuKey;
            $sec['is_new'] = false;

            $items = [];
            $seen  = [];
            foreach ($sec['items'] as $d) {
                $base = $sKey . ':' . menu_slug($d['name']);
                $key  = $base;
                for ($n = 2; isset($seen[$key]); $n++) {
                    $key = $base . '-' . $n;
                }
                $seen[$key] = true;
                $items[] = menu_apply_item($d, $rows['items'][$key] ?? null, $key);
            }
            $sec['items'] = $items;
            $built[$sKey] = $sec;
        }

        // Sections staff added.
        foreach ($rows['sections'] as $sKey => $r) {
            if ((int) $r['is_new'] === 1 && $r['menu'] === $menuKey && !isset($built[$sKey])) {
                $sec = menu_apply_section([
                    'id' => str_replace(':', '-', $sKey), 'title' => '', 'cn' => '', 'note' => '', 'intro' => '', 'items' => [],
                ], $r);
                $sec['key']    = $sKey;
                $sec['menu']   = $menuKey;
                $sec['is_new'] = true;
                $built[$sKey]  = $sec;
            }
        }

        // Dishes staff added, at the end of their section.
        foreach ($rows['items'] as $iKey => $r) {
            if ((int) $r['is_new'] === 1 && isset($built[$r['section_key']])) {
                $built[$r['section_key']]['items'][] = menu_apply_item(dish($r['name'], '', '', []), $r, $iKey);
            }
        }

        if (!$includeHidden) {
            foreach ($built as $k => $sec) {
                if ($sec['hidden']) {
                    unset($built[$k]);
                    continue;
                }
                $built[$k]['items'] = array_values(array_filter($sec['items'], fn($d) => !$d['hidden']));
                if (!$built[$k]['items'] && $sec['is_new']) {
                    unset($built[$k]);   // an empty new section has nothing to show yet
                }
            }
        }
        $out[$menuKey] = array_values($built);
    }

    return $out;
}

function menu_apply_section(array $sec, ?array $r): array
{
    $sec['hidden'] = false;
    $sec['edited'] = false;
    if ($r) {
        foreach (['title', 'cn', 'note', 'intro'] as $f) {
            $sec[$f] = (string) $r[$f];
        }
        $sec['hidden'] = (int) $r['hidden'] === 1;
        $sec['edited'] = true;
    }
    return $sec;
}

function menu_apply_item(array $d, ?array $r, string $key): array
{
    $d['key']    = $key;
    $d['photo']  = $d['photo'] ?? '';   // a photo typed into menu-data.php, if any
    $d['hidden'] = false;
    $d['edited'] = false;
    $d['is_new'] = false;
    if ($r) {
        $d['name']      = (string) $r['name'];
        $d['cn']        = (string) $r['cn'];
        $d['price']     = (string) $r['price'];
        $d['desc']      = (string) $r['description'];
        $d['allergens'] = $r['allergens'] === '' ? [] : explode(',', (string) $r['allergens']);
        $d['veg']       = (int) $r['veg'] === 1;
        $d['spice']     = (int) $r['spice'];
        // photo_id: NULL keeps the typed-in photo, 0 means "no photo", else an upload.
        if ($r['photo_id'] !== null) {
            $d['photo'] = (int) $r['photo_id'] > 0 ? photo_url((int) $r['photo_id']) : '';
        }
        $d['hidden']    = (int) $r['hidden'] === 1;
        $d['edited']    = true;
        $d['is_new']    = (int) $r['is_new'] === 1;
        if ($d['is_new']) {
            $d['price_label'] = '';
            $d['more_prices'] = [];
        }
    }
    return $d;
}

/** Finds one dish (with its section) in the built menu, hidden ones included. */
function menu_find_item(array $built, string $key): ?array
{
    foreach ($built as $sections) {
        foreach ($sections as $sec) {
            foreach ($sec['items'] as $d) {
                if ($d['key'] === $key) {
                    return ['item' => $d, 'section' => $sec];
                }
            }
        }
    }
    return null;
}

function menu_find_section(array $built, string $key): ?array
{
    foreach ($built as $sections) {
        foreach ($sections as $sec) {
            if ($sec['key'] === $key) {
                return $sec;
            }
        }
    }
    return null;
}

/* ------------------------------------------------------------ saving */

/** Tidies a price typed by staff: '22.5' → '22.50'. '' means no price shown. */
function menu_clean_price(string $p): ?string
{
    $p = trim(str_replace(['€', ',', ' '], ['', '.', ''], $p));
    if ($p === '') {
        return '';
    }
    if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $p)) {
        return null;
    }
    return number_format((float) $p, 2, '.', '');
}

/**
 * Saves a dish. $f holds name, cn, price, desc, allergens (array), veg, spice,
 * hidden, and optionally photo_id. Returns an error message or null.
 */
function menu_save_item(string $key, string $menuKey, string $sectionKey, array $f, bool $isNew, string $actor): ?string
{
    $name = trim((string) ($f['name'] ?? ''));
    if ($name === '') {
        return 'The dish needs a name.';
    }
    $price = menu_clean_price((string) ($f['price'] ?? ''));
    if ($price === null) {
        return 'The price should be a number, like 12.50.';
    }
    $known     = array_map('strval', array_keys($GLOBALS['allergenKey'] ?? []));
    $allergens = array_values(array_filter(array_map('strval', (array) ($f['allergens'] ?? [])), fn($c) => in_array($c, $known, true)));

    $existing = menu_edits_rows()['items'][$key] ?? null;
    $photoId  = array_key_exists('photo_id', $f) ? $f['photo_id'] : ($existing['photo_id'] ?? null);

    menu_edits_query(function (PDO $pdo) use ($key, $menuKey, $sectionKey, $name, $price, $f, $allergens, $photoId, $isNew, $existing, $actor) {
        $vals = [
            $menuKey, $sectionKey, mb_substr($name, 0, 160), mb_substr(trim((string) ($f['cn'] ?? '')), 0, 80), $price,
            mb_substr(trim((string) ($f['desc'] ?? '')), 0, 500), implode(',', $allergens),
            !empty($f['veg']) ? 1 : 0, max(0, min(5, (int) ($f['spice'] ?? 0))),
            $photoId === null ? null : (int) $photoId, !empty($f['hidden']) ? 1 : 0, $actor, db_now(),
        ];
        if ($existing) {
            $pdo->prepare('UPDATE menu_edits SET menu = ?, section_key = ?, name = ?, cn = ?, price = ?, description = ?, allergens = ?,
                           veg = ?, spice = ?, photo_id = ?, hidden = ?, updated_by = ?, updated_at = ? WHERE item_key = ?')
                ->execute(array_merge($vals, [$key]));
        } else {
            $sort = $isNew ? (int) $pdo->query('SELECT COUNT(*) FROM menu_edits')->fetchColumn() + 1 : 0;
            $pdo->prepare('INSERT INTO menu_edits (menu, section_key, name, cn, price, description, allergens, veg, spice,
                           photo_id, hidden, updated_by, updated_at, item_key, is_new, sort_order)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute(array_merge($vals, [$key, $isNew ? 1 : 0, $sort]));
        }
    });
    menu_edits_rows(true);
    return null;
}

/** Sets only the price of a dish, keeping everything else as it is now. */
function menu_save_price(array $item, string $menuKey, string $sectionKey, string $price, string $actor): ?string
{
    $clean = menu_clean_price($price);
    if ($clean === null) {
        return $item['name'] . ': the price should be a number, like 12.50.';
    }
    if ($clean === (string) $item['price']) {
        return null;
    }
    return menu_save_item($item['key'], $menuKey, $sectionKey, [
        'name' => $item['name'], 'cn' => $item['cn'], 'price' => $clean, 'desc' => $item['desc'],
        'allergens' => $item['allergens'], 'veg' => $item['veg'], 'spice' => $item['spice'], 'hidden' => $item['hidden'],
    ], $item['is_new'], $actor);
}

/** A new, unused key for a dish or section staff are adding. */
function menu_new_key(string $prefix): string
{
    return $prefix . ':new-' . bin2hex(random_bytes(4));
}

/** Puts a typed-in dish back as printed, or deletes a dish staff added. */
function menu_reset_item(string $key): void
{
    menu_edits_query(fn(PDO $pdo) => $pdo->prepare('DELETE FROM menu_edits WHERE item_key = ?')->execute([$key]));
    menu_edits_rows(true);
}

function menu_save_section(string $key, string $menuKey, array $f, bool $isNew, string $actor): ?string
{
    $title = trim((string) ($f['title'] ?? ''));
    if ($title === '') {
        return 'The section needs a title.';
    }
    $existing = menu_edits_rows()['sections'][$key] ?? null;
    menu_edits_query(function (PDO $pdo) use ($key, $menuKey, $f, $title, $isNew, $existing, $actor) {
        $vals = [
            $menuKey, mb_substr($title, 0, 120), mb_substr(trim((string) ($f['cn'] ?? '')), 0, 60),
            mb_substr(trim((string) ($f['note'] ?? '')), 0, 120), mb_substr(trim((string) ($f['intro'] ?? '')), 0, 500),
            !empty($f['hidden']) ? 1 : 0, $actor, db_now(),
        ];
        if ($existing) {
            $pdo->prepare('UPDATE menu_sections SET menu = ?, title = ?, cn = ?, note = ?, intro = ?, hidden = ?, updated_by = ?, updated_at = ?
                           WHERE section_key = ?')->execute(array_merge($vals, [$key]));
        } else {
            $sort = $isNew ? (int) $pdo->query('SELECT COUNT(*) FROM menu_sections')->fetchColumn() + 1 : 0;
            $pdo->prepare('INSERT INTO menu_sections (menu, title, cn, note, intro, hidden, updated_by, updated_at, section_key, is_new, sort_order)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute(array_merge($vals, [$key, $isNew ? 1 : 0, $sort]));
        }
    });
    menu_edits_rows(true);
    return null;
}

/** Puts a typed-in section back as printed, or deletes a new section and its dishes. */
function menu_reset_section(string $key): void
{
    $isNew = (int) (menu_edits_rows()['sections'][$key]['is_new'] ?? 0) === 1;
    menu_edits_query(function (PDO $pdo) use ($key, $isNew) {
        $pdo->prepare('DELETE FROM menu_sections WHERE section_key = ?')->execute([$key]);
        if ($isNew) {
            $pdo->prepare('DELETE FROM menu_edits WHERE section_key = ? AND is_new = 1')->execute([$key]);
        }
    });
    menu_edits_rows(true);
}

/* ------------------------------------------------------------ photos */

function photo_url(int $id): string
{
    return '/photo.php?id=' . $id;
}

/**
 * Stores an uploaded picture: turned upright, shrunk to at most $maxSide
 * pixels on the long edge, saved as JPEG (PNG if it has see-through parts).
 * Returns [photo id, null] or [null, error message].
 */
function photo_store_upload(array $file, string $label, string $actor, int $maxSide = 2000): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, 'Choose a photo first.'];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return [null, 'That photo is too big to upload. Try one under 20 MB.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return [null, 'The upload did not come through. Try again.'];
    }
    return photo_store_file($file['tmp_name'], $label, $actor, $maxSide);
}

function photo_store_file(string $path, string $label, string $actor, int $maxSide = 2000): array
{
    if (!function_exists('imagecreatefromstring')) {
        return [null, 'This server cannot process photos (GD is missing).'];
    }
    $info = @getimagesize($path);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return [null, 'That file is not a photo we can use. Use a JPG, PNG or WebP.'];
    }
    $img = @imagecreatefromstring((string) file_get_contents($path));
    if (!$img) {
        return [null, 'That photo could not be read. Try saving it as a JPG first.'];
    }

    // Phones store "which way up" separately; apply it so nothing shows sideways.
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $rot  = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($rot) {
            $img = imagerotate($img, $rot, 0);
        }
    }

    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1, $maxSide / max($w, $h));
    if ($scale < 1) {
        $nw  = (int) round($w * $scale);
        $nh  = (int) round($h * $scale);
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $img = $dst;
        [$w, $h] = [$nw, $nh];
    }

    $alpha = $info[2] !== IMAGETYPE_JPEG && photo_has_transparency($img);
    ob_start();
    if ($alpha) {
        imagesavealpha($img, true);
        imagepng($img, null, 8);
        $mime = 'image/png';
    } else {
        imageinterlace($img, true);
        imagejpeg($img, null, 84);
        $mime = 'image/jpeg';
    }
    $data = (string) ob_get_clean();

    $id = menu_edits_query(function (PDO $pdo) use ($mime, $w, $h, $data, $label, $actor) {
        $stmt = $pdo->prepare('INSERT INTO photos (mime, width, height, bytes, label, data, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bindValue(1, $mime);
        $stmt->bindValue(2, $w, PDO::PARAM_INT);
        $stmt->bindValue(3, $h, PDO::PARAM_INT);
        $stmt->bindValue(4, strlen($data), PDO::PARAM_INT);
        $stmt->bindValue(5, mb_substr($label, 0, 160));
        $stmt->bindValue(6, $data, PDO::PARAM_LOB);
        $stmt->bindValue(7, $actor);
        $stmt->bindValue(8, db_now());
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    });
    return [$id, null];
}

function photo_has_transparency($img): bool
{
    $w = imagesx($img);
    $h = imagesy($img);
    $step = max(1, (int) (max($w, $h) / 60));
    for ($x = 0; $x < $w; $x += $step) {
        for ($y = 0; $y < $h; $y += $step) {
            if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 0) {
                return true;
            }
        }
    }
    return false;
}

/** ['images/x.jpg' => photo id] for every site picture staff have replaced. */
function photo_swaps(bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }
    try {
        if (!db_ready()) {
            return $cache = [];
        }
        $cache = menu_edits_query(function (PDO $pdo) {
            $out = [];
            foreach ($pdo->query('SELECT path, photo_id FROM photo_swaps') as $r) {
                $out[$r['path']] = (int) $r['photo_id'];
            }
            return $out;
        });
    } catch (Throwable $e) {
        $cache = [];
    }
    return $cache;
}

function photo_set_swap(string $path, ?int $photoId, string $actor): void
{
    menu_edits_query(function (PDO $pdo) use ($path, $photoId, $actor) {
        $pdo->prepare('DELETE FROM photo_swaps WHERE path = ?')->execute([$path]);
        if ($photoId) {
            $pdo->prepare('INSERT INTO photo_swaps (path, photo_id, updated_by, updated_at) VALUES (?, ?, ?, ?)')
                ->execute([$path, $photoId, $actor, db_now()]);
        }
    });
    photo_swaps(true);
}

/** Rewrites the page's HTML so replaced pictures point at the new upload. */
function photos_rewrite_html(string $html, ?array $swaps = null): string
{
    $swaps = $swaps ?? photo_swaps();
    if (!$swaps) {
        return $html;
    }
    $map = [];
    foreach ($swaps as $path => $id) {
        $map['~(?:\.\./)*' . preg_quote($path, '~') . '(?:\?[^"\'\s)]*)?~'] = photo_url($id);
    }
    return (string) preg_replace(array_keys($map), array_values($map), $html);
}

/** Call before a public page prints anything. */
function photos_start_buffer(): void
{
    static $started = false;
    if ($started || PHP_SAPI === 'cli' && !getenv('PHOTOS_BUFFER')) {
        return;
    }
    $started = true;
    if (photo_swaps()) {
        ob_start(fn(string $html) => photos_rewrite_html($html));
    }
}

/**
 * Every picture under images/ that the website uses, for staff/photos.php.
 * @return list<array{path: string, pages: list<string>}>
 */
function photo_site_images(): array
{
    $root  = dirname(__DIR__);
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/images', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (preg_match('/\.(jpe?g|png|webp)$/i', $f->getFilename()) && strpos($f->getPathname(), '/images/logo/') === false) {
            $files[] = 'images/' . str_replace('\\', '/', substr($f->getPathname(), strlen($root . '/images/')));
        }
    }

    // Which pages mention each picture.
    $sources = array_merge(glob($root . '/*.php') ?: [], glob($root . '/includes/*.php') ?: [], glob($root . '/october/*.php') ?: []);
    $text = [];
    foreach ($sources as $s) {
        $text[basename(dirname($s)) === 'october' ? 'october/' . basename($s) : basename($s)] = (string) file_get_contents($s);
    }

    $out = [];
    foreach ($files as $path) {
        $short = substr($path, strlen('images/'));
        $pages = [];
        foreach ($text as $page => $src) {
            if (strpos($src, $short) !== false) {
                $pages[] = $page;
            }
        }
        $out[] = ['path' => $path, 'pages' => $pages];
    }
    usort($out, fn($a, $b) => [empty($a['pages']), $a['path']] <=> [empty($b['pages']), $b['path']]);
    return $out;
}
