<?php
/*
 * Stats: what the website is doing for October, and roughly what it is worth.
 *
 * Top to bottom it answers, in order:
 *   1. How many people came, and what did they do? (taps, from our own stats)
 *   2. What came of it? (real online bookings, from the bookings table)
 *   3. What is that worth against what the site costs? (an estimate, with its
 *      working shown, from assumptions that can be changed on this page)
 *   4. What happened off the site? (Google profile and Search Console figures,
 *      typed in once a month), with links to the full Google reports
 *
 * Read includes/stats.php for what is counted and why it needs no consent.
 */

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/stats.php';
staff_require_login();
staff_require_database();

$periods = stats_periods();
$period  = (string) ($_GET['p'] ?? '30d');
if (!isset($periods[$period])) {
    $period = '30d';
}
[$periodLabel, $from, $to] = $periods[$period];
$days = (int) (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days + 1;

// The month the "figures from elsewhere" form is editing.
$month = (string) ($_GET['m'] ?? (new DateTimeImmutable('first day of last month'))->format('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = (new DateTimeImmutable('first day of last month'))->format('Y-m');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = 'stats.php?p=' . urlencode($period) . '&m=' . urlencode($month);

    if (!staff_check_token($_POST['token'] ?? null)) {
        staff_flash('That form expired. Try again.');
    } elseif (($_POST['action'] ?? '') === 'assumptions') {
        foreach (array_keys(stats_assumptions()) as $key) {
            $value = str_replace(['€', '%', ','], '', trim((string) ($_POST[$key] ?? '')));
            if (is_numeric($value) && (float) $value >= 0) {
                setting_set('stats_' . $key, (string) round((float) $value, 2), staff_actor());
            }
        }
        staff_flash('Assumptions saved.');
        $back .= '#value';
    } elseif (($_POST['action'] ?? '') === 'monthly') {
        $saved = stats_monthly_set($month, (array) ($_POST['metric'] ?? []));
        staff_flash($saved ? 'Figures for ' . date('F Y', strtotime($month . '-01')) . ' saved.' : 'That did not save. Try again.');
        $back .= '#monthly';
    }

    header('Location: ' . $back, true, 303);
    exit;
}

$t        = stats_totals($from, $to);
$b        = stats_bookings($from, $to);
$c        = stats_enquiries($from, $to);
$a        = stats_assumptions();
$roi      = stats_roi($t, $b, $c, $a, $days);
$daily    = stats_daily($from, $to, ['click_directions', 'click_book_table', 'click_phone']);
$sources  = stats_breakdown($from, $to, 'page_view', 'source');
$pages    = stats_breakdown($from, $to, 'page_view', 'page');
$devices  = stats_breakdown($from, $to, 'page_view', 'device', 3);
$bookWhere = stats_breakdown($from, $to, 'click_book_table', 'location');
$phoneWhere = stats_breakdown($from, $to, 'click_phone', 'location');
$photos   = stats_breakdown($from, $to, 'gallery_open', 'label', 10);
$sections = stats_breakdown($from, $to, 'menu_section', 'label', 10);
$heroes   = stats_breakdown($from, $to, 'page_view', 'page', 20);
$dirApps  = stats_breakdown($from, $to, 'click_directions', 'label');
$dirWhere = stats_breakdown($from, $to, 'click_directions', 'location');
$monthly  = stats_monthly_get($month);

// The previous period of the same length, for the arrows on the tiles.
$prevTo   = (new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d');
$prevFrom = (new DateTimeImmutable($prevTo))->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
$prev     = stats_totals($prevFrom, $prevTo);
$prevB    = stats_bookings($prevFrom, $prevTo);

$actions = $t['click_directions'] + $t['click_phone'] + $t['click_book_table'];

$sourceNames = [
    'direct' => 'Typed in / saved', 'google' => 'Google search', 'google_maps' => 'Google Maps',
    'instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok',
    'tripadvisor' => 'Tripadvisor', 'bing' => 'Bing', 'chatgpt' => 'ChatGPT', 'other' => 'Other sites',
    'internal' => 'Within the site',
];
$pageNames = [
    '/' => 'Home', '/index.php' => 'Home', '/menu.php' => 'Menu', '/booking.php' => 'Book a table',
    '/gallery.php' => 'Gallery', '/privacy.php' => 'Privacy', '/order.php' => 'Order online',
    '/october/' => 'October design: home', '/october/index.php' => 'October design: home',
    '/october/menu.php' => 'October design: menu', '/october/gallery.php' => 'October design: gallery',
];
$whereNames = ['nav' => 'Top menu', 'hero' => 'Top of the home page', 'footer' => 'Footer', 'page' => 'Elsewhere on the page',
               'mobile-cta' => 'Phone bar at the bottom', 'visit' => 'Find us section', 'cookie_banner' => 'Cookie notice'];
$ga  = $stats['ga4_property'] ?? '';
$gaA = $stats['ga4_account'] ?? '';
$gaUrl  = $ga ? "https://analytics.google.com/analytics/web/#/a{$gaA}p{$ga}/reports/intelligenthome" : '';
$gscUrl = !empty($stats['search_console']) ? 'https://search.google.com/search-console/performance/search-analytics?resource_id=' . rawurlencode($stats['search_console']) : '';
$appNames      = ['google_maps' => 'Google Maps', 'apple_maps' => 'Apple Maps', 'waze' => 'Waze', 'get_directions_button' => 'Get directions button'];

function money(float $v): string
{
    return '€' . number_format($v, $v >= 100 ? 0 : 2);
}

/** "▲ 12%" against the previous period, or nothing when there is no base. */
function change(int $now, int $before): string
{
    if ($before === 0) {
        return $now > 0 ? '<span class="chg up">new</span>' : '';
    }
    $pct = (int) round(($now - $before) / $before * 100);
    if ($pct === 0) {
        return '<span class="chg">same</span>';
    }
    return sprintf('<span class="chg %s">%s %d%%</span>', $pct > 0 ? 'up' : 'down', $pct > 0 ? '▲' : '▼', abs($pct));
}

/** A labelled bar list, biggest first. */
function bars(array $rows, array $names = []): void
{
    if (!$rows) {
        echo '<p class="sub">Nothing yet in this period.</p>';
        return;
    }
    $max = max($rows) ?: 1;
    echo '<ul class="bars">';
    foreach ($rows as $key => $n) {
        $w = max(2, (int) round($n / $max * 100));
        printf('<li><span class="bars-name">%s</span><span class="bars-track"><span style="width:%d%%"></span></span><span class="bars-n">%s</span></li>',
            e($names[$key] ?? ucfirst(str_replace(['_', '/'], [' ', ''], (string) $key)) ?: 'Home'), $w, number_format($n));
    }
    echo '</ul>';
}

staff_head('Stats', 'stats', true);
?>

<?php if ($msg = staff_flash()): ?><div class="flash"><?= e($msg) ?></div><?php endif; ?>

<div class="stats-head">
    <div>
        <h1>Website stats</h1>
        <p class="sub"><?= e($periodLabel) ?> · <?= e(date('j M', strtotime($from))) ?> to <?= e(date('j M Y', strtotime($to))) ?></p>
    </div>
    <nav class="stats-periods" aria-label="Period">
        <?php foreach ($periods as $key => [$label]): ?>
            <a href="?p=<?= e($key) ?>&amp;m=<?= e($month) ?>" class="<?= $key === $period ? 'on' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
</div>

<section class="tiles" aria-label="Headline numbers">
    <div class="tile">
        <span class="tile-label">Visitors</span>
        <strong><?= number_format($t['visitors']) ?></strong>
        <?= change($t['visitors'], $prev['visitors']) ?>
        <span class="tile-note"><?= number_format($t['page_view']) ?> pages viewed</span>
    </div>
    <div class="tile tile-key">
        <span class="tile-label">Directions taps</span>
        <strong><?= number_format($t['click_directions']) ?></strong>
        <?= change($t['click_directions'], $prev['click_directions']) ?>
        <span class="tile-note">people heading to George’s Street</span>
    </div>
    <div class="tile tile-key">
        <span class="tile-label">Phone taps</span>
        <strong><?= number_format($t['click_phone']) ?></strong>
        <?= change($t['click_phone'], $prev['click_phone']) ?>
        <span class="tile-note">calls to book or ask</span>
    </div>
    <div class="tile">
        <span class="tile-label">Book a table taps</span>
        <strong><?= number_format($t['click_book_table']) ?></strong>
        <?= change($t['click_book_table'], $prev['click_book_table']) ?>
        <span class="tile-note"><?= number_format($t['booking_form_start']) ?> started the form</span>
    </div>
    <div class="tile tile-key">
        <span class="tile-label">Online bookings</span>
        <strong><?= number_format($b['bookings']) ?></strong>
        <?= change($b['bookings'], $prevB['bookings']) ?>
        <span class="tile-note"><?= number_format($b['guests']) ?> guests · real bookings</span>
    </div>
    <div class="tile">
        <span class="tile-label">Menu taps</span>
        <strong><?= number_format($t['click_menu']) ?></strong>
        <?= change($t['click_menu'], $prev['click_menu']) ?>
        <span class="tile-note"><?= number_format($t['menu_section']) ?> section jumps</span>
    </div>
    <div class="tile">
        <span class="tile-label">Photos opened</span>
        <strong><?= number_format($t['gallery_open']) ?></strong>
        <?= change($t['gallery_open'], $prev['gallery_open']) ?>
        <span class="tile-note"><?= number_format($t['click_gallery']) ?> gallery taps</span>
    </div>
    <div class="tile">
        <span class="tile-label">Instagram &amp; reviews</span>
        <strong><?= number_format($t['click_instagram'] + $t['click_review']) ?></strong>
        <span class="tile-note"><?= number_format($t['click_instagram']) ?> Instagram · <?= number_format($t['click_review']) ?> review taps</span>
    </div>
</section>

<section class="card google-links">
    <h2>Google reports</h2>
    <p class="sub">The full reports live with Google. Analytics only sees visitors who press Accept on the cookie notice; the numbers on this page count everyone.</p>
    <p>
        <?php if ($gaUrl): ?><a class="btn-p" href="<?= e($gaUrl) ?>" target="_blank" rel="noopener">Open Google Analytics</a><?php endif; ?>
        <?php if ($gscUrl): ?><a class="btn-s" href="<?= e($gscUrl) ?>" target="_blank" rel="noopener">Open Search Console</a><?php endif; ?>
        <a class="btn-s" href="https://business.google.com/" target="_blank" rel="noopener">Open Google Business Profile</a>
    </p>
</section>

<p class="stats-rate sub">
    <?php if ($t['visitors'] > 0): ?>
        <strong><?= number_format($actions) ?></strong> taps on directions, call or book from
        <strong><?= number_format($t['visitors']) ?></strong> visitors: about <?= number_format($actions / $t['visitors'] * 10, 1) ?> for every 10 people.
    <?php else: ?>
        No visits recorded in this period yet. Numbers start the day the site goes live.
    <?php endif; ?>
</p>

<section class="card">
    <h2>Day by day</h2>
    <?php
    // A small hand-drawn chart: visitors as a soft area, the three money
    // actions as lines on top. No chart library, so nothing to load or break.
    $w = 960; $h = 240; $padL = 36; $padB = 26; $padT = 12;
    $series = ['visitors' => 'Visitors', 'click_directions' => 'Directions', 'click_book_table' => 'Book taps', 'click_phone' => 'Calls'];
    $max = 1;
    foreach ($daily as $row) {
        foreach (array_keys($series) as $k) { $max = max($max, $row[$k]); }
    }
    $max   = (int) (ceil($max / 4) * 4);   // four even gridlines
    $n     = count($daily);
    $x     = fn($i) => $padL + ($n > 1 ? $i / ($n - 1) : 0.5) * ($w - $padL - 8);
    $y     = fn($v) => $padT + (1 - $v / $max) * ($h - $padT - $padB);
    $keys  = array_keys($daily);
    $path  = function (string $k) use ($daily, $keys, $x, $y) {
        $pts = [];
        foreach ($keys as $i => $day) { $pts[] = round($x($i), 1) . ',' . round($y($daily[$day][$k]), 1); }
        return 'M' . implode('L', $pts);
    };
    ?>
    <svg class="chart" viewBox="0 0 <?= $w ?> <?= $h ?>" role="img" aria-label="Visitors and actions per day">
        <?php for ($g = 0; $g <= 4; $g++): $gv = $max / 4 * $g; ?>
            <line x1="<?= $padL ?>" x2="<?= $w - 8 ?>" y1="<?= round($y($gv), 1) ?>" y2="<?= round($y($gv), 1) ?>" class="grid"/>
            <text x="<?= $padL - 6 ?>" y="<?= round($y($gv), 1) + 4 ?>" class="axis" text-anchor="end"><?= (int) $gv ?></text>
        <?php endfor; ?>
        <path d="<?= $path('visitors') ?>L<?= round($x($n - 1), 1) ?>,<?= $h - $padB ?>L<?= $padL ?>,<?= $h - $padB ?>Z" class="area"/>
        <path d="<?= $path('visitors') ?>" class="line line-visitors"/>
        <path d="<?= $path('click_directions') ?>" class="line line-directions"/>
        <path d="<?= $path('click_book_table') ?>" class="line line-order"/>
        <path d="<?= $path('click_phone') ?>" class="line line-phone"/>
        <?php $step = max(1, (int) ceil($n / 8)); foreach ($keys as $i => $day): if ($i % $step) continue; ?>
            <text x="<?= round($x($i), 1) ?>" y="<?= $h - 6 ?>" class="axis" text-anchor="middle"><?= e(date('j M', strtotime($day))) ?></text>
        <?php endforeach; ?>
    </svg>
    <ul class="legend">
        <?php foreach ($series as $k => $label): ?>
            <li><span class="key key-<?= e(str_replace(['click_', 'book_table'], ['', 'order'], $k)) ?>"></span><?= e($label) ?></li>
        <?php endforeach; ?>
    </ul>
</section>

<div class="stats-grid">
    <section class="card">
        <h2>Where visitors come from</h2>
        <?php bars(array_diff_key($sources, ['internal' => 0]), $sourceNames); ?>
        <p class="sub">Add <code>?utm_source=flyer</code> (or qr, instagram…) to links you share and they show up here by name.</p>
    </section>
    <section class="card">
        <h2>Most viewed pages</h2>
        <?php bars($pages, $pageNames); ?>
        <h2 class="mt">Phones vs computers</h2>
        <?php bars($devices); ?>
    </section>
    <section class="card">
        <h2>Directions: which map app</h2>
        <?php bars($dirApps, $appNames); ?>
        <h2 class="mt">Directions: tapped from</h2>
        <?php bars($dirWhere, $whereNames); ?>
    </section>
    <section class="card">
        <h2>Phone taps: tapped from</h2>
        <?php bars($phoneWhere, $whereNames); ?>
        <h2 class="mt">Book a table: tapped from</h2>
        <?php bars($bookWhere, $whereNames); ?>
    </section>
    <section class="card">
        <h2>Photos people open most</h2>
        <?php bars($photos); ?>
    </section>
    <section class="card">
        <h2>Menu sections people jump to</h2>
        <?php bars($sections); ?>
    </section>
</div>

<section class="card" id="value">
    <h2>What it is worth (estimate)</h2>
    <p class="sub">Bookings and enquiries are real. Everything else is a tap, multiplied by your own guess of how many turn into a sale. Change the guesses below and the page works it out again.</p>
    <table class="roi">
        <tbody>
            <?php foreach ($roi['lines'] as $line): ?>
                <tr>
                    <th scope="row"><?= e($line['label']) ?></th>
                    <td class="sub"><?= e($line['working']) ?></td>
                    <td class="num"><?= money($line['value']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><th scope="row">Estimated value</th><td></td><td class="num"><?= money($roi['value']) ?></td></tr>
            <?php if ($roi['cost'] > 0): ?>
                <tr><th scope="row">Website &amp; marketing cost</th><td class="sub"><?= money($a['monthly_cost']) ?> a month, for <?= $days ?> days</td><td class="num">− <?= money($roi['cost']) ?></td></tr>
                <tr class="roi-total"><th scope="row">Return</th><td class="sub">for every €1 spent</td><td class="num"><?= money($roi['roi']) ?></td></tr>
            <?php endif; ?>
        </tfoot>
    </table>

    <details class="assume"<?= $roi['cost'] > 0 ? '' : ' open' ?>>
        <summary>Change the assumptions</summary>
        <form method="post" class="assume-form">
            <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
            <input type="hidden" name="action" value="assumptions">
            <label>Average spend per person (€)<input name="avg_spend_per_head" inputmode="decimal" value="<?= e($a['avg_spend_per_head']) ?>"></label>
            <label>Average people per table<input name="avg_party_size" inputmode="decimal" value="<?= e($a['avg_party_size']) ?>"></label>
            <label>% of directions taps that come in to eat<input name="directions_visit" inputmode="numeric" value="<?= e($a['directions_visit']) ?>"></label>
            <label>% of calls that become a table<input name="call_conversion" inputmode="numeric" value="<?= e($a['call_conversion']) ?>"></label>
            <label>Website &amp; marketing cost a month (€)<input name="monthly_cost" inputmode="decimal" value="<?= e($a['monthly_cost']) ?>"></label>
            <button class="btn-p" type="submit">Save assumptions</button>
        </form>
    </details>
</section>

<section class="card" id="monthly">
    <div class="stats-head">
        <div>
            <h2>Figures from elsewhere</h2>
            <p class="sub">Google Business Profile and Search Console keep their own numbers. Copy them in once a month to see the whole picture next to the website's.</p>
        </div>
        <form method="get" class="month-pick">
            <input type="hidden" name="p" value="<?= e($period) ?>">
            <label for="m" class="sr-only">Month</label>
            <input type="month" id="m" name="m" value="<?= e($month) ?>" onchange="this.form.submit()">
        </form>
    </div>

    <?php if (!empty($monthly['gsc_clicks']) && !empty($monthly['gsc_impressions'])): ?>
        <p class="stats-rate"><strong><?= number_format($monthly['gsc_clicks']) ?></strong> clicks from Google search out of
            <strong><?= number_format($monthly['gsc_impressions']) ?></strong> times October was shown in <?= e(date('F Y', strtotime($month . '-01'))) ?>:
            <?= number_format($monthly['gsc_clicks'] / $monthly['gsc_impressions'] * 100, 1) ?>% of searchers clicked.</p>
    <?php endif; ?>

    <form method="post" class="monthly-form">
        <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
        <input type="hidden" name="action" value="monthly">
        <?php foreach (stats_monthly_metrics() as $key => [$label, $where]): ?>
            <label>
                <span><?= e($label) ?> <small><?= e($where) ?></small></span>
                <input name="metric[<?= e($key) ?>]" inputmode="decimal" value="<?= isset($monthly[$key]) ? e(rtrim(rtrim(number_format($monthly[$key], 2, '.', ''), '0'), '.')) : '' ?>">
            </label>
        <?php endforeach; ?>
        <button class="btn-p" type="submit">Save <?= e(date('F Y', strtotime($month . '-01'))) ?></button>
    </form>
</section>

<p class="sub stats-foot">Counted on this website's own server: no cookies, no IP addresses, nothing personal, so every visitor is included whether or not they accept cookies. Staff browsing is left out. Google Analytics has the deeper reports for visitors who accept cookies.</p>

<?php staff_foot(); ?>
