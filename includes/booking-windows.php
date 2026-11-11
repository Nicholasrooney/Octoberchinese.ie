<?php
/*
 * Booking windows: the times staff open for online booking.
 *
 * With $booking['windows_only'] on, the public booking form offers nothing
 * except the times inside an open window. A window is one date with a first
 * and a last booking time, e.g. tomorrow 15:00 to 19:00 means people can book
 * a table at 15:00, 15:30 ... 19:00. Staff can close a window at any time
 * (and open it again), which takes those times off the website at once.
 * Bookings already made in a closed window are kept.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/hours.php';

function windows_enabled(): bool
{
    return !empty($GLOBALS['booking']['windows_only']);
}

function windows_tz(): DateTimeZone
{
    return new DateTimeZone('Europe/Dublin');
}

function windows_create_table(PDO $pdo): void
{
    $sql = (string) file_get_contents(__DIR__ . '/../db/schema.sql');
    foreach (db_sql_statements($sql, $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) as $statement) {
        if (preg_match('/CREATE TABLE IF NOT EXISTS booking_windows\b/i', $statement)) {
            $pdo->exec($statement);
        }
    }
}

/** Runs a query, creating the table once and retrying if it is missing. */
function windows_query(callable $work)
{
    $pdo = db();
    try {
        return $work($pdo);
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'booking_windows') !== false
            && (stripos($msg, "doesn't exist") !== false || stripos($msg, 'no such table') !== false)) {
            windows_create_table($pdo);
            return $work($pdo);
        }
        throw $e;
    }
}

/** Times staff can choose for a window: every 15 minutes, 10:00 to 23:00. */
function windows_time_choices(): array
{
    $out = [];
    for ($m = 10 * 60; $m <= 23 * 60; $m += 15) {
        $out[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }
    return $out;
}

/** Per-request cache of windows by date; cleared whenever a window changes. */
function &windows_cache(): array
{
    static $cache = ['dates' => [], 'upcoming' => null];
    return $cache;
}

function windows_cache_clear(): void
{
    $c = &windows_cache();
    $c = ['dates' => [], 'upcoming' => null];
}

/** Open windows for one date, earliest first. */
function windows_for_date(string $date): array
{
    $all   = &windows_cache();
    $cache = &$all['dates'];
    if (isset($cache[$date])) {
        return $cache[$date];
    }
    try {
        return $cache[$date] = windows_query(function (PDO $pdo) use ($date) {
            $stmt = $pdo->prepare('SELECT * FROM booking_windows WHERE window_date = ? AND is_open = 1 ORDER BY first_time');
            $stmt->execute([$date]);
            return $stmt->fetchAll();
        });
    } catch (Throwable $e) {
        return $cache[$date] = [];   // no database: no windows, so no online booking
    }
}

/**
 * The times the public form may offer on a date: every booking slot inside an
 * open window, minus any that have already passed today.
 */
function windows_slots_for_date(string $date, ?DateTimeImmutable $now = null): array
{
    global $booking;
    $step = max(5, (int) ($booking['slot_minutes'] ?? 30));
    $now  = $now ?? new DateTimeImmutable('now', windows_tz());

    $slots = [];
    foreach (windows_for_date($date) as $w) {
        $t   = new DateTimeImmutable("$date {$w['first_time']}", windows_tz());
        $end = new DateTimeImmutable("$date {$w['last_time']}", windows_tz());
        for (; $t <= $end; $t = $t->modify("+$step minutes")) {
            if ($t > $now) {
                $slots[$t->format('H:i')] = true;
            }
        }
    }
    $slots = array_keys($slots);
    sort($slots);
    return $slots;
}

/** Is there an open window from now on? Decides whether "Book a table" shows anywhere. */
function windows_any_upcoming(): bool
{
    $c = &windows_cache();
    if ($c['upcoming'] === null) {
        $c['upcoming'] = (bool) windows_next_date();
    }
    return $c['upcoming'];
}

/** The first date, from $from on, with a bookable time left in an open window. */
function windows_next_date(?string $from = null): ?string
{
    $now   = new DateTimeImmutable('now', windows_tz());
    $from  = $from ?? $now->format('Y-m-d');
    try {
        $dates = windows_query(function (PDO $pdo) use ($from) {
            $stmt = $pdo->prepare('SELECT DISTINCT window_date FROM booking_windows WHERE window_date >= ? AND is_open = 1 ORDER BY window_date LIMIT 30');
            $stmt->execute([$from]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        });
    } catch (Throwable $e) {
        return null;
    }
    foreach ($dates as $d) {
        if (windows_slots_for_date((string) $d, $now)) {
            return (string) $d;
        }
    }
    return null;
}

/** Every window from a date on (open and closed), for the staff page. */
function windows_list(string $from, int $limit = 60): array
{
    return windows_query(function (PDO $pdo) use ($from, $limit) {
        $stmt = $pdo->prepare('SELECT * FROM booking_windows WHERE window_date >= ? ORDER BY window_date, first_time LIMIT ' . (int) $limit);
        $stmt->execute([$from]);
        return $stmt->fetchAll();
    });
}

/** Recent past windows, newest first, for the staff page. */
function windows_past(string $before, int $limit = 10): array
{
    return windows_query(function (PDO $pdo) use ($before, $limit) {
        $stmt = $pdo->prepare('SELECT * FROM booking_windows WHERE window_date < ? ORDER BY window_date DESC, first_time LIMIT ' . (int) $limit);
        $stmt->execute([$before]);
        return $stmt->fetchAll();
    });
}

/**
 * Checks and saves a new window. Returns [id, null] or [null, error message].
 */
function window_add(string $date, string $first, string $last, string $note, string $actor): array
{
    global $booking;

    $tz    = windows_tz();
    $today = new DateTimeImmutable('today', $tz);
    $d     = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
    $times = windows_time_choices();

    if (!$d || $d->format('Y-m-d') !== $date) {
        return [null, 'Choose a date.'];
    }
    if ($d < $today) {
        return [null, 'That date has passed.'];
    }
    if ($d > $today->modify('+' . (int) $booking['days_ahead'] . ' days')) {
        return [null, 'Choose a date within the next ' . (int) $booking['days_ahead'] . ' days.'];
    }
    if (!in_array($first, $times, true) || !in_array($last, $times, true)) {
        return [null, 'Choose the first and last booking times.'];
    }
    if ($last < $first) {
        return [null, 'The last booking time must be the same as or after the first.'];
    }
    if ($date === $today->format('Y-m-d') && $last <= (new DateTimeImmutable('now', $tz))->format('H:i')) {
        return [null, 'Those times have already passed today.'];
    }
    $note = trim(preg_replace('/\s+/', ' ', $note));
    $note = function_exists('mb_substr') ? mb_substr($note, 0, 200) : substr($note, 0, 200);

    $id = windows_query(function (PDO $pdo) use ($date, $first, $last, $note, $actor) {
        $now = db_now();
        $pdo->prepare('INSERT INTO booking_windows (window_date, first_time, last_time, note, is_open, created_by, created_at, updated_at)
                       VALUES (?, ?, ?, ?, 1, ?, ?, ?)')
            ->execute([$date, $first, $last, $note, $actor, $now, $now]);
        return (int) $pdo->lastInsertId();
    });
    windows_cache_clear();
    return [$id, null];
}

function window_set_open(int $id, bool $open): bool
{
    return windows_query(function (PDO $pdo) use ($id, $open) {
        $stmt = $pdo->prepare('UPDATE booking_windows SET is_open = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$open ? 1 : 0, db_now(), $id]);
        windows_cache_clear();
        return $stmt->rowCount() > 0;
    });
}

function window_delete(int $id): bool
{
    return windows_query(function (PDO $pdo) use ($id) {
        $stmt = $pdo->prepare('DELETE FROM booking_windows WHERE id = ?');
        $stmt->execute([$id]);
        windows_cache_clear();
        return $stmt->rowCount() > 0;
    });
}

/** Live bookings that fall inside a window, for the staff page. */
function window_booking_count(array $w): int
{
    try {
        $stmt = db()->prepare("SELECT COUNT(*) FROM bookings WHERE booking_date = ? AND booking_time >= ? AND booking_time <= ?
                               AND status NOT IN ('cancelled', 'no_show')");
        $stmt->execute([$w['window_date'], $w['first_time'], $w['last_time']]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * The times the public booking form offers on a date. With windows on, only
 * window times; otherwise the normal opening-hours slots.
 */
function booking_public_slots(string $date): array
{
    return windows_enabled() ? windows_slots_for_date($date) : hours_slots_for_date($date);
}

/** Can the public form offer this date at all? */
function booking_public_takes_date(string $date): bool
{
    if (windows_enabled()) {
        return (bool) windows_slots_for_date($date) && hours_blocked_message($date) === null;
    }
    return hours_takes_bookings($date) && (bool) hours_slots_for_date($date);
}

/** Where to point people when a date has nothing: the next date that does. */
function booking_public_next_date(string $from): ?string
{
    return windows_enabled() ? windows_next_date($from) : hours_next_open_date($from);
}
