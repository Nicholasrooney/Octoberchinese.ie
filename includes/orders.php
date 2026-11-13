<?php
/*
 * Click and collect orders: the catalogue, collection times, storage and email.
 *
 * The catalogue is built from includes/menu-data.php, so a dish added or
 * re-priced on the menu is orderable at the new price straight away. The
 * browser only ever sends dish keys and quantities; every price is looked up
 * again here, so nobody can change what they pay by editing the page.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/hours.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/mailer.php';

/* ------------------------------------------------------------ catalogue */

/** Statuses, in the order an order moves through them. */
function order_statuses(): array
{
    return [
        'new'       => 'New',
        'accepted'  => 'Preparing',
        'ready'     => 'Ready',
        'collected' => 'Collected',
        'cancelled' => 'Cancelled',
    ];
}

/**
 * Choices that apply to every dish in a section, where the printed menu puts
 * the choice in the section heading rather than on each dish.
 */
function order_section_choices(): array
{
    return [
        'lunch-dumplings' => ['Steamed', 'In hot & sour soup'],
    ];
}

/** "Choice of a, b or c." in a dish description becomes a required choice. */
function order_parse_choices(string $desc): array
{
    if (!preg_match('/^Choice of (.+?)\.?$/i', trim($desc), $m)) {
        return [];
    }
    $parts = preg_split('/,\s*|\s+or\s+/i', $m[1]);
    $parts = array_map(fn($p) => ucfirst(trim(preg_replace('/^or\s+/i', '', $p))), $parts);
    return array_values(array_filter($parts, fn($p) => $p !== ''));
}

function order_slug(string $s): string
{
    $s = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $s));
    return trim($s, '-');
}

/**
 * Every orderable dish, grouped by section.
 *
 * @return array{sections: array, items: array<string, array>}
 */
function order_catalogue(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $menu = $lunchMenu = [];
    require __DIR__ . '/menu-data.php';

    $sections = [];
    $items    = [];
    $sources  = [['alacarte', $menu], ['lunch', $lunchMenu]];

    $allowed = $GLOBALS['ordering']['menus'] ?? ['alacarte', 'lunch'];

    foreach ($sources as [$menuKey, $menuSections]) {
        if (!in_array($menuKey, $allowed, true)) {
            continue;
        }
        foreach ($menuSections as $sec) {
            // "Add a soft drink for €1.50" in a lunch section intro becomes a tick box.
            $addons = [];
            if (preg_match('/Add a soft drink for €([\d.]+)/i', (string) $sec['intro'], $m)) {
                $addons['drink'] = ['label' => 'Add a soft drink', 'price' => (float) $m[1]];
            }

            $keys = [];
            foreach ($sec['items'] as $d) {
                if ($d['price'] === '' || !is_numeric($d['price'])) {
                    continue;   // "price on request" dishes are not orderable online
                }

                $key  = $sec['id'] . '--' . order_slug($d['name']);
                $base = $key;
                for ($n = 2; isset($items[$key]); $n++) {
                    $key = $base . '-' . $n;
                }

                // Printed menus use ¼ and ½ for duck; spell them out for ordering.
                $words    = ['¼' => 'Quarter', '½' => 'Half'];
                $variants = [['label' => $words[$d['price_label']] ?? $d['price_label'], 'price' => (float) $d['price']]];
                foreach ($d['more_prices'] as [$label, $price]) {
                    $variants[] = ['label' => $words[$label] ?? $label, 'price' => (float) $price];
                }

                $choices = order_section_choices()[$sec['id']] ?? order_parse_choices((string) $d['desc']);

                $items[$key] = [
                    'key'       => $key,
                    'name'      => $d['name'],
                    'cn'        => $d['cn'],
                    'desc'      => $d['desc'],
                    'section'   => $sec['id'],
                    'menu'      => $menuKey,
                    'variants'  => $variants,
                    'choices'   => $choices,
                    'addons'    => $addons,
                    'veg'       => (bool) $d['veg'],
                    'chef'      => (bool) $d['chef'],
                    'spice'     => (int) $d['spice'],
                    'allergens' => $d['allergens'],
                ];
                $keys[] = $key;
            }

            if ($keys) {
                $sections[] = [
                    'id'    => $sec['id'],
                    'title' => $sec['title'],
                    'cn'    => $sec['cn'],
                    'note'  => $sec['note'],
                    'menu'  => $menuKey,
                    'keys'  => $keys,
                ];
            }
        }
    }

    return $cache = ['sections' => $sections, 'items' => $items];
}

/* ------------------------------------------------------------ times */

function order_tz(): DateTimeZone
{
    return new DateTimeZone('Europe/Dublin');
}

function order_today(?DateTimeImmutable $now = null): string
{
    return ($now ?? new DateTimeImmutable('now', order_tz()))->format('Y-m-d');
}

/** Is online ordering paused by staff for today? */
function order_paused(?string $today = null): bool
{
    return setting_get('ordering_paused_date') === ($today ?? order_today());
}

function order_set_paused(bool $paused, string $actor = ''): bool
{
    return setting_set('ordering_paused_date', $paused ? order_today() : null, $actor);
}

/**
 * Collection times still open today, earliest first, as 'H:i'.
 * Fully booked times are left out.
 */
function order_slots(?DateTimeImmutable $now = null, ?array $taken = null): array
{
    global $ordering;

    $now    = $now ?? new DateTimeImmutable('now', order_tz());
    $today  = $now->format('Y-m-d');
    $window = hours_for_date($today);

    if (!$window || empty($ordering['enabled']) || order_paused($today)) {
        return [];
    }

    $step  = max(5, (int) $ordering['slot_minutes']);
    $prep  = (int) $ordering['prep_minutes'];
    $open  = new DateTimeImmutable("$today {$window[0]}", order_tz());
    $close = (new DateTimeImmutable("$today {$window[1]}", order_tz()))
        ->modify('-' . (int) $ordering['last_order_before_close'] . ' minutes');
    if (!empty($ordering['collect_until'])) {
        $until = new DateTimeImmutable("$today {$ordering['collect_until']}", order_tz());
        $close = min($close, $until);
    }

    // Earliest: opening time or now, plus prep, rounded up to the next step.
    $start = max($open, $now)->modify("+$prep minutes");
    $mins  = (int) $start->format('H') * 60 + (int) $start->format('i');
    $round = (int) (ceil($mins / $step) * $step);
    $start = (new DateTimeImmutable($today, order_tz()))->modify("+$round minutes");

    $max   = (int) $ordering['max_orders_per_slot'];
    $taken = $taken ?? ($max > 0 ? order_slot_counts($today) : []);

    $slots = [];
    for ($t = $start; $t <= $close; $t = $t->modify("+$step minutes")) {
        $hm = $t->format('H:i');
        if ($max > 0 && ($taken[$hm] ?? 0) >= $max) {
            continue;
        }
        $slots[] = $hm;
    }
    return $slots;
}

/** Live orders per collection time for a date. */
function order_slot_counts(string $date): array
{
    try {
        return order_query(function (PDO $pdo) use ($date) {
            $stmt = $pdo->prepare(
                "SELECT collect_time, COUNT(*) AS n FROM orders
                 WHERE collect_date = ? AND status <> 'cancelled' GROUP BY collect_time"
            );
            $stmt->execute([$date]);
            $out = [];
            foreach ($stmt->fetchAll() as $r) {
                $out[$r['collect_time']] = (int) $r['n'];
            }
            return $out;
        });
    } catch (Throwable $e) {
        return [];   // no database yet: offer every time rather than none
    }
}

/**
 * Why ordering is not possible right now, or null if it is.
 * Used to show a friendly message instead of an empty time list.
 */
function order_closed_message(?DateTimeImmutable $now = null): ?string
{
    global $ordering, $site;

    $now   = $now ?? new DateTimeImmutable('now', order_tz());
    $today = $now->format('Y-m-d');
    $phone = $site['phone'] ? ' You can still ring us on ' . $site['phone'] . '.' : '';

    if (empty($ordering['enabled'])) {
        return 'Online ordering is switched off at the moment.' . $phone;
    }
    if (order_paused($today)) {
        return 'We have paused online orders for today because the kitchen is very busy.' . $phone;
    }
    if (order_slots($now)) {
        return null;
    }

    $next = hours_next_open_date($now->modify('+1 day')->format('Y-m-d'));
    $when = '';
    if ($next) {
        $w    = hours_for_date($next);
        $day  = new DateTimeImmutable($next, order_tz());
        $when = ' Ordering opens again ' . ($day->format('Y-m-d') === $now->modify('+1 day')->format('Y-m-d') ? 'tomorrow' : $day->format('l'))
              . ' from ' . $w[0] . '.';
    }

    $lunchOnly = !empty($ordering['collect_until']);
    return hours_for_date($today)
        ? ($lunchOnly ? 'Online orders are for lunch, collected by ' . $ordering['collect_until'] . '. We are not taking any more today.' : 'We are not taking any more orders for collection today.') . $when
        : 'We are closed today.' . $when;
}

/* ------------------------------------------------------------ validation */

class OrderError extends RuntimeException {}

/**
 * Turns the basket the browser sent into clean order lines, priced here.
 *
 * @param array $basket list of ['key','variant','qty','choice','addons'=>[],'note']
 * @return array{lines: array, subtotal: float, count: int}
 */
function order_price_basket(array $basket, string $collectTime): array
{
    global $ordering;

    $cat   = order_catalogue()['items'];
    $lines = [];
    $total = 0.0;
    $count = 0;

    foreach ($basket as $row) {
        if (!is_array($row)) {
            continue;
        }
        $key  = (string) ($row['key'] ?? '');
        $item = $cat[$key] ?? null;
        if (!$item) {
            throw new OrderError('One of the dishes in your basket is no longer on the menu. Please remove it and try again.');
        }

        $qty = (int) ($row['qty'] ?? 0);
        if ($qty < 1 || $qty > (int) $ordering['max_qty_per_item']) {
            throw new OrderError('Check the quantity for ' . $item['name'] . '.');
        }

        $vi = (int) ($row['variant'] ?? 0);
        if (!isset($item['variants'][$vi])) {
            $vi = 0;
        }
        $variant = $item['variants'][$vi];
        $unit    = $variant['price'];

        $extras = [];
        if ($item['choices']) {
            $choice = (string) ($row['choice'] ?? '');
            if (!in_array($choice, $item['choices'], true)) {
                throw new OrderError('Choose an option for ' . $item['name'] . '.');
            }
            $extras[] = $choice;
        }
        foreach ((array) ($row['addons'] ?? []) as $addonKey) {
            if (isset($item['addons'][$addonKey])) {
                $unit    += $item['addons'][$addonKey]['price'];
                $extras[] = $item['addons'][$addonKey]['label'];
            }
        }

        if ($item['menu'] === 'lunch' && $collectTime > $ordering['lunch_until']) {
            throw new OrderError($item['name'] . ' is on the lunch menu, which is only for collection up to ' . $ordering['lunch_until'] . '. Choose an earlier time or remove it.');
        }

        $note = trim(preg_replace('/\s+/', ' ', (string) ($row['note'] ?? '')));
        $note = function_exists('mb_substr') ? mb_substr($note, 0, 140) : substr($note, 0, 140);

        $lines[] = [
            'key'        => $key,
            'name'       => $item['name'],
            'variant'    => trim(($variant['label'] !== '' ? $variant['label'] . ' ' : '') . implode(', ', $extras)),
            'qty'        => $qty,
            'unit_price' => round($unit, 2),
            'note'       => $note,
        ];
        $total += $unit * $qty;
        $count += $qty;
    }

    if (!$lines) {
        throw new OrderError('Your basket is empty.');
    }
    if ($count > 60) {
        throw new OrderError('That is a very large order. Please ring us so we can make sure it is ready on time.');
    }
    if ((float) $ordering['min_order'] > 0 && $total < (float) $ordering['min_order']) {
        throw new OrderError('The minimum order for collection is ' . order_money((float) $ordering['min_order']) . '.');
    }

    return ['lines' => $lines, 'subtotal' => round($total, 2), 'count' => $count];
}

function order_money(float $amount): string
{
    return '€' . number_format($amount, 2);
}

/** Short number shown to the customer and the kitchen, e.g. #0042. */
function order_ref(int $id): string
{
    return '#' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

/* ------------------------------------------------------------ storage */

function order_create_tables(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql    = (string) file_get_contents(__DIR__ . '/../db/schema.sql');

    foreach (db_sql_statements($sql, $driver) as $statement) {
        if (preg_match('/CREATE TABLE IF NOT EXISTS (orders|order_items)\b/i', $statement)) {
            $pdo->exec($statement);
        }
    }
}

/** Runs a query, creating the order tables once and retrying if missing. */
function order_query(callable $work)
{
    $pdo = db();
    try {
        return $work($pdo);
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (preg_match('/order/i', $msg) && (stripos($msg, "doesn't exist") !== false || stripos($msg, 'no such table') !== false)) {
            order_create_tables($pdo);
            return $work($pdo);
        }
        throw $e;
    }
}

/**
 * Saves an order and its lines in one transaction, re-checking the slot is
 * not full inside it (two people can pick the last place in the same second).
 * Returns the saved order with its id and token.
 */
function order_save(array $o, array $priced): array
{
    global $ordering;

    return order_query(function (PDO $pdo) use ($o, $priced, $ordering) {
        $pdo->beginTransaction();
        try {
            $max = (int) $ordering['max_orders_per_slot'];
            if ($max > 0) {
                $sql = "SELECT COUNT(*) FROM orders WHERE collect_date = ? AND collect_time = ? AND status <> 'cancelled'"
                     . (db_is_mysql($pdo) ? ' FOR UPDATE' : '');
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$o['date'], $o['time']]);
                if ((int) $stmt->fetchColumn() >= $max) {
                    throw new OrderError('That collection time has just filled up. Please choose another.');
                }
            }

            $now   = db_now();
            $token = bin2hex(random_bytes(16));
            $pdo->prepare(
                'INSERT INTO orders (public_token, name, phone, email, collect_date, collect_time, asap, notes,
                                     subtotal, item_count, status, marketing_consent, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $token, $o['name'], $o['phone'], $o['email'], $o['date'], $o['time'], $o['asap'] ? 1 : 0,
                $o['notes'] !== '' ? $o['notes'] : null, $priced['subtotal'], $priced['count'], 'new',
                !empty($o['marketing_consent']) ? 1 : 0, $now, $now,
            ]);
            $id = (int) $pdo->lastInsertId();

            $line = $pdo->prepare(
                'INSERT INTO order_items (order_id, item_key, name, variant, qty, unit_price, note) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($priced['lines'] as $l) {
                $line->execute([$id, $l['key'], $l['name'], $l['variant'], $l['qty'], $l['unit_price'], $l['note']]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return order_get($id);
    });
}

function order_get(int $id): ?array
{
    return order_query(function (PDO $pdo) use ($id) {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $o = $stmt->fetch();
        return $o ? order_with_items($pdo, $o) : null;
    });
}

function order_by_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }
    return order_query(function (PDO $pdo) use ($token) {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE public_token = ?');
        $stmt->execute([$token]);
        $o = $stmt->fetch();
        return $o ? order_with_items($pdo, $o) : null;
    });
}

function order_with_items(PDO $pdo, array $o): array
{
    $stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $stmt->execute([$o['id']]);
    $o['items'] = $stmt->fetchAll();
    return $o;
}

/** Orders for collection on a date, in collection order. */
function orders_for_date(string $date): array
{
    return order_query(function (PDO $pdo) use ($date) {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE collect_date = ? ORDER BY collect_time, id');
        $stmt->execute([$date]);
        return array_map(fn($o) => order_with_items($pdo, $o), $stmt->fetchAll());
    });
}

function order_set_status(int $id, string $status): bool
{
    if (!isset(order_statuses()[$status])) {
        return false;
    }
    return order_query(function (PDO $pdo) use ($id, $status) {
        $stmt = $pdo->prepare('UPDATE orders SET status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$status, db_now(), $id]);
        return $stmt->rowCount() > 0;
    });
}

/* ------------------------------------------------------------ email */

function order_lines_text(array $o): array
{
    $out = [];
    foreach ($o['items'] as $l) {
        $out[] = sprintf('%2d x %s%s  %s', $l['qty'], $l['name'],
            $l['variant'] !== '' ? ' (' . $l['variant'] . ')' : '',
            order_money($l['qty'] * (float) $l['unit_price']));
        if ($l['note'] !== '') {
            $out[] = '      Note: ' . $l['note'];
        }
    }
    $out[] = '';
    $out[] = 'Total: ' . order_money((float) $o['subtotal']);
    return $out;
}

function order_status_url(array $o): string
{
    global $site;
    return 'https://' . $site['domain'] . '/order-status.php?t=' . $o['public_token'];
}

/** Alert to the restaurant. */
function order_mail_alert(array $o): bool
{
    global $ordering, $site;

    $lines = array_merge([
        'New click and collect order ' . order_ref((int) $o['id']) . '.',
        '',
        'Collect: ' . ($o['asap'] ? 'As soon as possible, about ' : 'Today at ') . $o['collect_time'],
        'Name:    ' . $o['name'],
        'Phone:   ' . $o['phone'],
        'Email:   ' . ($o['email'] ?: '—'),
    ], !empty($o['notes']) ? ['Notes:   ' . $o['notes']] : [], [''], order_lines_text($o), [
        'To pay on collection.',
        '',
        'Open the orders screen: https://' . $site['domain'] . '/staff/orders.php',
    ]);

    $recipients = array_values(array_filter((array) ($ordering['notify_email'] ?? [])));

    return mail_send_alert(
        sprintf('Order %s for %s: %s, %s', order_ref((int) $o['id']), $o['collect_time'], $o['name'], order_money((float) $o['subtotal'])),
        implode("\n", $lines),
        ['reply_to' => $o['email'] ?: null],
        $recipients
    );
}

/** Receipt to the customer. */
function order_mail_confirmation(array $o): bool
{
    global $ordering, $site;

    if (empty($o['email'])) {
        return false;
    }

    $lines = array_merge([
        'Hi ' . mail_first_name($o['name']) . ',',
        '',
        'Thanks for your order. We have it, and the kitchen will start on it shortly.',
        '',
        'Order:    ' . order_ref((int) $o['id']),
        'Collect:  today at ' . $o['collect_time'],
        'Where:    ' . $site['name'] . ', ' . $site['address'],
        '',
    ], order_lines_text($o), [
        $ordering['payment_note'],
        '',
        'Follow your order here: ' . order_status_url($o),
        '',
        'Need to change something? Ring us on ' . $site['phone'] . ' and quote ' . order_ref((int) $o['id']) . '.',
        '',
        $site['name'],
    ]);

    return mail_send($o['email'], 'Your order ' . order_ref((int) $o['id']) . ' at ' . $site['name'], implode("\n", $lines));
}

/** "Your order is ready" email, sent when staff mark it ready. */
function order_mail_ready(array $o): bool
{
    global $site;

    if (empty($o['email'])) {
        return false;
    }
    $body = implode("\n", [
        'Hi ' . mail_first_name($o['name']) . ',',
        '',
        'Your order ' . order_ref((int) $o['id']) . ' is ready to collect at ' . $site['address'] . '.',
        '',
        'See you soon,',
        $site['name'],
    ]);
    return mail_send($o['email'], 'Your order ' . order_ref((int) $o['id']) . ' is ready', $body);
}
