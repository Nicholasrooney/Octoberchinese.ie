<?php
/*
 * Settings staff can change from the website.
 *
 * A two-column key/value table, deliberately separate from config.php.
 * config.php is tracked in git, so anything staff changed there would be
 * silently undone by the next deploy — a weekend somebody had paused would
 * quietly start taking bookings again, and nobody would notice until the
 * covers walked in. The database survives deploys; the file does not.
 *
 * Reads FAIL OPEN. If the database is unreachable, setting_get() returns the
 * default rather than throwing, so a database wobble can never take the public
 * booking form down with it. For the weekend pause that means the form keeps
 * taking bookings, which is the status quo and recoverable with a phone call.
 * The alternative — refusing everyone for a weekend the café is open — loses
 * business and shows no symptom at all.
 */

require_once __DIR__ . '/db.php';

function settings_create_table(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql    = (string) file_get_contents(__DIR__ . '/../db/schema.sql');

    foreach (db_sql_statements($sql, $driver) as $statement) {
        if (stripos($statement, 'settings') !== false) {
            $pdo->exec($statement);
        }
    }
}

/**
 * The request-lifetime cache, by reference so callers can clear it.
 *
 * Worth having: hours_blocked_message() is called in a loop when the form
 * hunts for the next bookable day, and that should not be fourteen round
 * trips to the database for the same answer.
 */
function &settings_cache(): array
{
    static $cache = [];
    return $cache;
}

/** Forget one cached key, or all of them. Used after a write, and by tests. */
function settings_forget(?string $key = null): void
{
    $cache = &settings_cache();

    if ($key === null) {
        $cache = [];
    } else {
        unset($cache[$key]);
    }
}

/** One setting, or $default when it is unset, empty or unreadable. */
function setting_get(string $key, ?string $default = null): ?string
{
    $cache = &settings_cache();

    if (!array_key_exists($key, $cache)) {
        $cache[$key] = settings_read($key);
    }

    return $cache[$key] ?? $default;
}

/** The uncached read. Returns null for "no value", including on any failure. */
function settings_read(string $key): ?string
{
    try {
        $pdo = db();

        try {
            $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
        } catch (Throwable $e) {
            // First use against a live database that predates this table.
            settings_create_table($pdo);
            $value = false;
        }

        return $value === false || $value === null || $value === '' ? null : (string) $value;
    } catch (Throwable $e) {
        return null;   // no database, bad credentials, server down — fail open
    }
}

/**
 * Write a setting. Pass null to clear it.
 *
 * Returns false instead of throwing when the write cannot happen, so the staff
 * area can say "that did not save" rather than show a stack trace to somebody
 * holding a phone in their other hand.
 */
function setting_set(string $key, ?string $value, string $actor = ''): bool
{
    $now = (new DateTimeImmutable('now', new DateTimeZone('Europe/Dublin')))->format('Y-m-d H:i:s');

    try {
        $pdo = db();
    } catch (Throwable $e) {
        return false;
    }

    // Two goes: if the table is missing, create it and repeat.
    for ($attempt = 0; $attempt < 2; $attempt++) {
        try {
            if ($value === null) {
                $pdo->prepare('DELETE FROM settings WHERE setting_key = ?')->execute([$key]);
            } else {
                $pdo->prepare(settings_upsert_sql($pdo))->execute([$key, $value, $actor, $now]);
            }

            settings_forget($key);
            return true;
        } catch (Throwable $e) {
            if ($attempt > 0) {
                return false;
            }
            try {
                settings_create_table($pdo);
            } catch (Throwable $inner) {
                return false;
            }
        }
    }

    return false;
}

/** Upsert, spelled the way the driver in front of us spells it. */
function settings_upsert_sql(PDO $pdo): string
{
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

    return $mysql
        ? 'INSERT INTO settings (setting_key, setting_value, updated_by, updated_at)
           VALUES (?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                                   updated_by    = VALUES(updated_by),
                                   updated_at    = VALUES(updated_at)'
        : 'INSERT INTO settings (setting_key, setting_value, updated_by, updated_at)
           VALUES (?, ?, ?, ?)
           ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value,
                                                  updated_by    = excluded.updated_by,
                                                  updated_at    = excluded.updated_at';
}

/** Who changed a setting and when, for showing it back in the staff area. */
function setting_meta(string $key): ?array
{
    try {
        $stmt = db()->prepare('SELECT updated_by, updated_at FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return null;
    }

    return $row ?: null;
}
