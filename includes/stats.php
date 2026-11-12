<?php
/*
 * Website stats: our own record of what people do on the site.
 *
 * Google Analytics only sees the visitors who accept cookies, which on a
 * restaurant site is often half of them or fewer. This counts everyone, because it stores
 * nothing that needs consent: no cookie is set, no IP address is kept, and no
 * name, phone or email ever reaches it. Each row is just "someone tapped
 * Get directions on the home page from a phone, having come from Google".
 *
 * Daily visitors are counted with a hash of the IP address and browser, made
 * with a random salt that is thrown away at midnight. Within one day the same
 * person counts once; after that the hashes cannot be linked to each other or
 * worked back to an address, not even by us.
 *
 * Bookings and catering enquiries are NOT counted from taps. The stats page
 * reads them straight from their own tables, so a booking is a real booking.
 *
 * js/main.js sends events to track.php; staff/stats.php reads them back.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';

/**
 * Every event the site may record, with how the stats page names it.
 * track.php refuses anything not listed, so a stranger posting junk to the
 * endpoint cannot invent new rows on the dashboard.
 */
function stats_events(): array
{
    return [
        'page_view'          => 'Page views',
        'click_directions'   => 'Directions taps',
        'click_phone'        => 'Phone taps',
        'click_book_table'   => 'Book a table taps',
        'click_order'        => 'Order online taps',
        'click_menu'         => 'Menu taps',
        'click_gallery'      => 'Gallery taps',
        'click_catering'     => 'Catering taps',
        'click_instagram'    => 'Instagram taps',
        'click_email'        => 'Email taps',
        'click_review'       => 'Google review taps',
        'click_preview'      => 'Taps to the October (Hunan-style) design',
        'booking_form_start' => 'Started a booking',
        'gallery_open'       => 'Photos opened',
        'menu_section'       => 'Menu sections jumped to',
        'menu_tab'           => 'Menu tabs opened',
        'menu_veg_filter'    => 'Vegetarian filter used',
    ];
}

function stats_enabled(): bool
{
    global $stats;
    return !empty($stats['enabled']);
}

function stats_create_tables(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql    = (string) file_get_contents(__DIR__ . '/../db/schema.sql');

    foreach (db_sql_statements($sql, $driver) as $statement) {
        if (preg_match('/CREATE TABLE IF NOT EXISTS stats_/i', $statement)) {
            $pdo->exec($statement);
        }
    }
}

/**
 * Runs $work against the database, creating the stats tables the first time.
 * Returns $fallback when there is no database yet, so a café that has not set
 * one up still gets a working site, just without stats.
 */
function stats_query(callable $work, $fallback = null)
{
    static $ready = false;

    try {
        $pdo = db();
        if (!$ready) {
            stats_create_tables($pdo);
            $ready = true;
        }
        return $work($pdo);
    } catch (Throwable $e) {
        return $fallback;
    }
}

/**
 * Where a visitor came from, in the handful of buckets a café owner cares
 * about. Only the referring site's name is used, never the full address.
 */
function stats_source(string $referrer, string $ownHost = '', string $utm = ''): string
{
    $utm = strtolower(trim($utm));
    if ($utm !== '') {
        return substr(preg_replace('/[^a-z0-9_-]/', '', $utm), 0, 30) ?: 'campaign';
    }

    $host = strtolower((string) parse_url($referrer, PHP_URL_HOST));
    if ($host === '') {
        return 'direct';
    }
    $host = preg_replace('/^(www|m|l|lm)\./', '', $host);
    if ($ownHost !== '' && $host === preg_replace('/^www\./', '', strtolower($ownHost))) {
        return 'internal';
    }

    $buckets = [
        'google_maps' => '/^maps\.google\./',
        'google'      => '/(^|\.)google\./',
        'instagram'   => '/(^|\.)instagram\.com$/',
        'facebook'    => '/(^|\.)(facebook\.com|fb\.com|fb\.me)$/',
        'tiktok'      => '/(^|\.)tiktok\.com$/',
        'tripadvisor' => '/(^|\.)tripadvisor\./',
        'bing'        => '/(^|\.)bing\.com$/',
        'chatgpt'     => '/(^|\.)(chatgpt\.com|openai\.com)$/',
    ];
    foreach ($buckets as $name => $pattern) {
        if (preg_match($pattern, $host)) {
            return $name;
        }
    }

    return 'other';
}

/**
 * Crawlers and link previewers. They rarely run JavaScript, so they barely
 * reach track.php anyway; this catches the ones that do.
 */
function stats_is_bot(string $userAgent): bool
{
    if (trim($userAgent) === '') {
        return true;
    }
    return (bool) preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|headless|lighthouse|pingdom|monitor|curl|wget|python/i', $userAgent);
}

function stats_device(string $userAgent): string
{
    if (preg_match('/iPad|Tablet/i', $userAgent)) {
        return 'tablet';
    }
    return preg_match('/Mobile|Android|iPhone/i', $userAgent) ? 'mobile' : 'desktop';
}

/**
 * Today's anonymous visitor key: the same all day for one browser, and
 * unlinkable once the day's salt has been replaced.
 */
function stats_visitor_key(string $ip, string $userAgent, ?string $today = null): string
{
    $today = $today ?? (new DateTimeImmutable('now', new DateTimeZone('Europe/Dublin')))->format('Y-m-d');

    $stored = (string) setting_get('stats_salt', '');
    [$day, $salt] = array_pad(explode('|', $stored, 2), 2, '');

    if ($day !== $today || strlen($salt) < 32) {
        $salt = bin2hex(random_bytes(16));
        setting_set('stats_salt', $today . '|' . $salt, 'stats');
    }

    return substr(hash_hmac('sha256', $ip . '|' . $userAgent, $salt), 0, 16);
}

/**
 * Tidies one incoming event. Returns null when it should not be stored.
 *
 * @param array $in  the posted fields: event, label, location, page, ref, utm
 */
function stats_clean_event(array $in, string $userAgent, string $ownHost = ''): ?array
{
    $event = (string) ($in['event'] ?? '');
    if (!array_key_exists($event, stats_events()) || stats_is_bot($userAgent)) {
        return null;
    }

    $short = function ($value, int $max): string {
        $value = preg_replace('/[^\p{L}\p{N} _.\/#:-]/u', '', (string) $value);
        return mb_substr(trim($value), 0, $max);
    };

    $page = parse_url((string) ($in['page'] ?? ''), PHP_URL_PATH) ?: '/';

    return [
        'event'    => $event,
        'label'    => $short($in['label'] ?? '', 80),
        'location' => $short($in['location'] ?? '', 40),
        'page'     => $short($page, 60),
        // Only a page view carries where the visit came from.
        'source'   => $event === 'page_view'
            ? stats_source((string) ($in['ref'] ?? ''), $ownHost, (string) ($in['utm'] ?? ''))
            : '',
        'device'   => stats_device($userAgent),
    ];
}

function stats_record(array $clean, string $visitor): bool
{
    return (bool) stats_query(function (PDO $pdo) use ($clean, $visitor) {
        $now = db_now();
        $st  = $pdo->prepare(
            'INSERT INTO stats_events (created_at, day, event, label, location, page, source, device, visitor)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        return $st->execute([
            $now, substr($now, 0, 10), $clean['event'], $clean['label'], $clean['location'],
            $clean['page'], $clean['source'], $clean['device'], $visitor,
        ]);
    }, false);
}

/* ------------------------------------------------------------------------
 * Reading it back, for staff/stats.php
 * --------------------------------------------------------------------- */

/**
 * The periods the stats page offers, each as [label, from, to] dates.
 */
function stats_periods(?string $today = null): array
{
    $tz = new DateTimeZone('Europe/Dublin');
    $d  = new DateTimeImmutable($today ?? 'today', $tz);

    $monthStart     = $d->modify('first day of this month');
    $lastMonthStart = $monthStart->modify('-1 month');

    return [
        '7d'         => ['Last 7 days',  $d->modify('-6 days')->format('Y-m-d'),  $d->format('Y-m-d')],
        '30d'        => ['Last 30 days', $d->modify('-29 days')->format('Y-m-d'), $d->format('Y-m-d')],
        'month'      => [$monthStart->format('F Y') . ' so far', $monthStart->format('Y-m-d'), $d->format('Y-m-d')],
        'last_month' => [$lastMonthStart->format('F Y'), $lastMonthStart->format('Y-m-d'), $monthStart->modify('-1 day')->format('Y-m-d')],
        '90d'        => ['Last 90 days', $d->modify('-89 days')->format('Y-m-d'), $d->format('Y-m-d')],
    ];
}

/** Count of each event between two dates, plus distinct daily visitors. */
function stats_totals(string $from, string $to): array
{
    return stats_query(function (PDO $pdo) use ($from, $to) {
        $st = $pdo->prepare('SELECT event, COUNT(*) AS n FROM stats_events WHERE day BETWEEN ? AND ? GROUP BY event');
        $st->execute([$from, $to]);
        $out = array_fill_keys(array_keys(stats_events()), 0);
        foreach ($st as $row) {
            $out[$row['event']] = (int) $row['n'];
        }

        // A visitor is counted once per day they visit.
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM (SELECT DISTINCT day, visitor FROM stats_events
             WHERE day BETWEEN ? AND ? AND event = 'page_view' AND visitor <> '') v"
        );
        $st->execute([$from, $to]);
        $out['visitors'] = (int) $st->fetchColumn();

        return $out;
    }, array_fill_keys(array_merge(array_keys(stats_events()), ['visitors']), 0));
}

/** One count per day for the chart: [day => [event => n, 'visitors' => n]]. */
function stats_daily(string $from, string $to, array $events): array
{
    $days = [];
    $cur  = new DateTimeImmutable($from);
    $end  = new DateTimeImmutable($to);
    while ($cur <= $end) {
        $days[$cur->format('Y-m-d')] = array_fill_keys(array_merge($events, ['visitors']), 0);
        $cur = $cur->modify('+1 day');
    }

    return stats_query(function (PDO $pdo) use ($from, $to, $events, $days) {
        if ($events) {
            $in = implode(',', array_fill(0, count($events), '?'));
            $st = $pdo->prepare("SELECT day, event, COUNT(*) AS n FROM stats_events
                                 WHERE day BETWEEN ? AND ? AND event IN ($in) GROUP BY day, event");
            $st->execute(array_merge([$from, $to], $events));
            foreach ($st as $row) {
                $day = substr((string) $row['day'], 0, 10);
                if (isset($days[$day])) {
                    $days[$day][$row['event']] = (int) $row['n'];
                }
            }
        }

        $st = $pdo->prepare("SELECT day, COUNT(DISTINCT visitor) AS n FROM stats_events
                             WHERE day BETWEEN ? AND ? AND event = 'page_view' AND visitor <> '' GROUP BY day");
        $st->execute([$from, $to]);
        foreach ($st as $row) {
            $day = substr((string) $row['day'], 0, 10);
            if (isset($days[$day])) {
                $days[$day]['visitors'] = (int) $row['n'];
            }
        }

        return $days;
    }, $days);
}

/**
 * Counts of one column for one event, biggest first. Used for "which
 * platform", "where from", "which button" and "which page".
 */
function stats_breakdown(string $from, string $to, string $event, string $column, int $limit = 8): array
{
    if (!in_array($column, ['label', 'location', 'page', 'source', 'device'], true)) {
        return [];
    }

    return stats_query(function (PDO $pdo) use ($from, $to, $event, $column, $limit) {
        $st = $pdo->prepare("SELECT $column AS k, COUNT(*) AS n FROM stats_events
                             WHERE day BETWEEN ? AND ? AND event = ?
                             GROUP BY $column ORDER BY n DESC LIMIT " . (int) $limit);
        $st->execute([$from, $to, $event]);
        $out = [];
        foreach ($st as $row) {
            $out[(string) $row['k'] === '' ? '(not set)' : (string) $row['k']] = (int) $row['n'];
        }
        return $out;
    }, []);
}

/**
 * Real bookings made on the website between two dates (by the day they were
 * made, not the day of the table), with the guests they bring.
 */
function stats_bookings(string $from, string $to): array
{
    return stats_query(function (PDO $pdo) use ($from, $to) {
        $st = $pdo->prepare(
            "SELECT COUNT(*) AS n, COALESCE(SUM(guests), 0) AS guests FROM bookings
             WHERE source = 'website' AND status NOT IN ('cancelled', 'declined')
             AND created_at >= ? AND created_at < ?"
        );
        $st->execute([$from . ' 00:00:00', stats_day_after($to)]);
        $row = $st->fetch() ?: [];
        return ['bookings' => (int) ($row['n'] ?? 0), 'guests' => (int) ($row['guests'] ?? 0)];
    }, ['bookings' => 0, 'guests' => 0]);
}

/** Catering and cake enquiries received between two dates, and their value. */
function stats_enquiries(string $from, string $to): array
{
    return stats_query(function (PDO $pdo) use ($from, $to) {
        $st = $pdo->prepare(
            "SELECT COUNT(*) AS n, COALESCE(SUM(estimate_total), 0) AS est FROM catering_enquiries
             WHERE created_at >= ? AND created_at < ?"
        );
        $st->execute([$from . ' 00:00:00', stats_day_after($to)]);
        $row = $st->fetch() ?: [];
        return ['enquiries' => (int) ($row['n'] ?? 0), 'estimate' => (int) ($row['est'] ?? 0)];
    }, ['enquiries' => 0, 'estimate' => 0]);
}

function stats_day_after(string $day): string
{
    return (new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
}

/* ------------------------------------------------------------------------
 * The money side: assumptions and monthly figures typed in by hand
 * --------------------------------------------------------------------- */

/** The ROI assumptions, as saved on the stats page or from config. */
function stats_assumptions(): array
{
    global $stats;
    $out = [];
    foreach (($stats['defaults'] ?? []) as $key => $default) {
        $saved     = setting_get('stats_' . $key);
        $out[$key] = is_numeric($saved) ? (float) $saved : (float) $default;
    }
    return $out;
}

/** Figures from outside the site, typed in once a month. */
function stats_monthly_metrics(): array
{
    return [
        'gbp_views'      => ['Google profile views',         'Google Business Profile → Performance'],
        'gbp_searches'   => ['Google profile searches',      'Google Business Profile → Performance'],
        'gbp_directions' => ['Google directions requests',   'Google Business Profile → Performance'],
        'gbp_calls'      => ['Google calls',                 'Google Business Profile → Performance'],
        'gbp_website'    => ['Google website clicks',        'Google Business Profile → Performance'],
        'gsc_clicks'     => ['Search clicks',                'Search Console → Performance'],
        'gsc_impressions'=> ['Search impressions',           'Search Console → Performance'],
        'gsc_position'   => ['Average search position',      'Search Console → Performance'],
        'ga_users'       => ['Google Analytics users',       'Analytics → Reports → Overview (consenting visitors only)'],
        'reviews_total'  => ['Google reviews (total)',       'Google Business Profile → Reviews'],
        'marketing_spend'=> ['Marketing spend (€)',          'Ads, flyers, boosted posts'],
    ];
}

function stats_monthly_get(string $month): array
{
    return stats_query(function (PDO $pdo) use ($month) {
        $st = $pdo->prepare('SELECT metric, value FROM stats_monthly WHERE month = ?');
        $st->execute([$month]);
        $out = [];
        foreach ($st as $row) {
            $out[$row['metric']] = (float) $row['value'];
        }
        return $out;
    }, []);
}

function stats_monthly_set(string $month, array $values): bool
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        return false;
    }

    return (bool) stats_query(function (PDO $pdo) use ($month, $values) {
        $now   = db_now();
        $known = stats_monthly_metrics();
        $del   = $pdo->prepare('DELETE FROM stats_monthly WHERE month = ? AND metric = ?');
        $ins   = $pdo->prepare('INSERT INTO stats_monthly (month, metric, value, updated_at) VALUES (?, ?, ?, ?)');

        foreach ($values as $metric => $value) {
            if (!isset($known[$metric])) {
                continue;
            }
            $del->execute([$month, $metric]);
            // People type "€2,640" as readily as "2640".
            $value = str_replace(['€', ',', ' '], '', trim((string) $value));
            if ($value !== '' && is_numeric($value)) {
                $ins->execute([$month, $metric, round((float) $value, 2), $now]);
            }
        }
        return true;
    }, false);
}

/**
 * What the website's actions are probably worth, from the counts and the
 * assumptions. Every line is shown on the page with its working, so nobody
 * mistakes an estimate for takings.
 *
 * @param array $t  stats_totals()
 * @param array $b  stats_bookings()
 * @param array $c  stats_enquiries()
 * @param array $a  stats_assumptions()
 * @param int   $days  length of the period, to scale the monthly cost
 */
function stats_roi(array $t, array $b, array $c, array $a, int $days): array
{
    $party = (float) ($a['avg_party_size'] ?? 2);
    $head  = (float) ($a['avg_spend_per_head'] ?? 0);
    $lines = [
        'bookings' => [
            'label'   => 'Online table bookings',
            'working' => sprintf('%d guests × €%.2f', $b['guests'], $head),
            'value'   => $b['guests'] * $head,
        ],
        'directions' => [
            'label'   => 'Directions taps',
            'working' => sprintf('%d %s × %d%% × %s people × €%.2f', $t['click_directions'], $t['click_directions'] === 1 ? 'tap' : 'taps',
                $a['directions_visit'], rtrim(rtrim(number_format($party, 1), '0'), '.'), $head),
            'value'   => $t['click_directions'] * $a['directions_visit'] / 100 * $party * $head,
        ],
        'calls' => [
            'label'   => 'Phone taps',
            'working' => sprintf('%d %s × %d%% × %s people × €%.2f', $t['click_phone'], $t['click_phone'] === 1 ? 'call' : 'calls',
                $a['call_conversion'], rtrim(rtrim(number_format($party, 1), '0'), '.'), $head),
            'value'   => $t['click_phone'] * $a['call_conversion'] / 100 * $party * $head,
        ],
    ];
    if ($c['enquiries'] > 0) {
        $lines['catering'] = [
            'label'   => 'Catering enquiries',
            'working' => sprintf('%d enquiries, quoted at €%d (not all will go ahead)', $c['enquiries'], $c['estimate']),
            'value'   => (float) $c['estimate'],
        ];
    }

    $value = array_sum(array_column($lines, 'value'));
    $cost  = $a['monthly_cost'] * $days / 30;

    return [
        'lines' => $lines,
        'value' => $value,
        'cost'  => $cost,
        'roi'   => $cost > 0 ? $value / $cost : null,
    ];
}
