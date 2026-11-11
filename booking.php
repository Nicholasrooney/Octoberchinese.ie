<?php
require __DIR__ . '/includes/booking-handler.php';

$pageTitle       = 'Book a Table | October Chinese Dining, Dún Laoghaire';
$pageDescription = 'Book a table at October Chinese Dining on George’s Street Upper, Dún Laoghaire. Choose your date, time and party size. We will contact you if anything needs to change.';
$pageKeywords    = 'book a table Dún Laoghaire, Chinese restaurant booking Dún Laoghaire, reserve table Dún Laoghaire';
$activePage      = 'booking';
$pageScripts     = ['js/booking.js'];
$pageStyles      = ['css/booking.css'];

require_once __DIR__ . '/includes/seo.php';
$pageSchema = [seo_breadcrumbs(['Book a table' => 'booking.php'])];
require __DIR__ . '/includes/header.php';

function field_error(array $errors, string $field): string {
    return isset($errors[$field])
        ? '<p class="field-error" id="' . e($field) . '-error">' . e($errors[$field]) . '</p>'
        : '';
}
function error_attrs(array $errors, string $field): string {
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="' . e($field) . '-error"' : '';
}

// What the booking widget shows: a date, the times still free on it, and the
// few free times nearest to the time the visitor would like.
$tz        = new DateTimeZone('Europe/Dublin');
$shownDate = $old['date'];
if ($shownDate === '') {
    // Today if there is still a time left, otherwise the next day we are open.
    $shownDate = $today->format('Y-m-d');
    $todayFree = array_filter(booking_slot_availability($shownDate, (int) $old['guests']),
        fn($free, $t) => $free && $t > (new DateTimeImmutable('now', $tz))->format('H:i'), ARRAY_FILTER_USE_BOTH);
    if (!$todayFree) {
        $shownDate = booking_public_next_date($today->modify('+1 day')->format('Y-m-d')) ?? $shownDate;
    }
    $old['date'] = $shownDate;
}
$slots = booking_slot_availability($shownDate, (int) $old['guests']);
if ($shownDate === $today->format('Y-m-d')) {
    $nowHm = (new DateTimeImmutable('now', $tz))->format('H:i');
    foreach ($slots as $t => $free) {
        if ($t <= $nowHm) $slots[$t] = false;
    }
}
if ($old['time'] !== '') {
    $prefTime = $old['time'];
}

/** The up-to-5 free times closest to the preferred time, in time order. */
function booking_nearest(array $slots, string $pref, int $n = 5): array
{
    $mins = fn($t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
    $free = array_keys(array_filter($slots));
    usort($free, fn($a, $b) => abs($mins($a) - $mins($pref)) <=> abs($mins($b) - $mins($pref)) ?: strcmp($a, $b));
    $pick = array_slice($free, 0, $n);
    sort($pick);
    return $pick;
}
$nearest   = booking_nearest($slots, $prefTime);
$freeCount = count(array_filter($slots));

// Real numbers only: how many tables were booked online today.
$bookedToday = 0;
try {
    $stmt = db()->prepare('SELECT COUNT(*) FROM bookings WHERE created_at >= ?');
    $stmt->execute([$today->format('Y-m-d') . ' 00:00:00']);
    $bookedToday = (int) $stmt->fetchColumn();
} catch (Throwable $e) {
    $bookedToday = 0;
}

// Dates offered in the date picker.
$dateOptions = [];
for ($d = $today; $d <= $lastDay; $d = $d->modify('+1 day')) {
    $ymd = $d->format('Y-m-d');
    $label = $ymd === $today->format('Y-m-d') ? 'Today, ' . $d->format('j M')
           : ($ymd === $today->modify('+1 day')->format('Y-m-d') ? 'Tomorrow, ' . $d->format('j M') : $d->format('D j M'));
    $takes = booking_public_takes_date($ymd);
    // With booking windows, list only the days staff have opened.
    if (windows_enabled() && !$takes) {
        continue;
    }
    $dateOptions[$ymd] = [$label, $takes];
}
$prefOptions = hours_all_slots();
?>

<section class="page-hero page-hero-slim">
    <img class="page-hero-img" src="images/sea-bass-pour.jpg" alt="" aria-hidden="true">
    <div class="container page-hero-inner">
        <p class="eyebrow">Reservations</p>
        <h1>Book a table <span lang="zh-Hans">订座</span></h1>
    </div>
</section>

<div class="container booking-layout">
    <div class="booking-side">
        <h2 class="booking-side-title">Your table, held for you</h2>
        <p>Send us your details and we'll hold a table for you. We'll contact you if we can't fit you in at that time.</p>

        <p class="booking-notice"><strong>Planning a weekend dinner?</strong>
            Book ahead to get the time you want.</p>

        <p>When we are busy, tables are held for
            <?= (int) ($booking['table_minutes'] ?? 60) ?> minutes.</p>

        <p>More than <?= (int) $booking['max_guests'] ?> people?
            <?php if ($site['phone']): ?>
                Call us on <a href="<?= e($telHref) ?>"><?= e($site['phone']) ?></a>.
            <?php elseif ($site['email']): ?>
                Email <a href="mailto:<?= e($site['email']) ?>"><?= e($site['email']) ?></a>.
            <?php elseif (!empty($site['instagram'])): ?>
                Message us on <a href="<?= e($site['instagram']) ?>" target="_blank" rel="noopener">Instagram</a>.
            <?php endif; ?>
        </p>
        <?php if (!empty($site['hours'])): ?>
            <dl class="hours">
                <?php foreach ($site['hours'] as $days => $time): ?>
                    <div><dt><?= e($days) ?></dt><dd><?= e($time) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>

        <img class="booking-photo" src="images/bao-buns-overhead.jpg" alt="Bao buns branded with the October tree in a bamboo steamer" loading="lazy">
    </div>

    <div class="booking-main">
        <?php if ($confirmed && isset($_GET['sent'])): ?>
            <?php
            // Reported to analytics as a completed booking. Party size, time
            // and weekday only: never the name, phone or email.
            $confirmedDay = strtok((string) $confirmed['date'], ' ');
            ?>
            <div class="confirmation" role="status"
                 data-track-view="booking_submitted"
                 data-guests="<?= (int) $confirmed['guests'] ?>"
                 data-time="<?= e($confirmed['time']) ?>"
                 data-day="<?= e($confirmedDay) ?>">
                <h2>Booking request sent</h2>
                <p>Thanks, <?= e($confirmed['name']) ?>. We've got your request for
                    <strong><?= (int) $confirmed['guests'] ?> <?= $confirmed['guests'] === 1 ? 'guest' : 'guests' ?></strong>
                    on <strong><?= e($confirmed['date']) ?></strong> at <strong><?= e($confirmed['time']) ?></strong>.</p>
                <p>We'll be in touch if anything needs to change.</p>
                <a class="btn btn-green" href="menu.php">See the menu</a>
            </div>
        <?php else: ?>

            <?php if (isset($errors['form'])): ?>
                <div class="form-alert" role="alert"><?= e($errors['form']) ?></div>
            <?php elseif ($errors): ?>
                <div class="form-alert" role="alert">Some details need fixing. Check the highlighted fields below.</div>
            <?php endif; ?>

            <form class="booking-form ot" method="post" action="booking.php" novalidate data-ot>
                <input type="hidden" name="token" value="<?= e($_SESSION['booking_token']) ?>">
                <div class="hp" aria-hidden="true">
                    <label for="website">Leave this empty</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <h2 class="ot-title">Make a booking</h2>

                <div class="ot-pickers">
                    <label class="ot-pick ot-pick-wide">
                        <span class="visually-hidden">Number of people</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>
                        <select name="guests" id="guests" data-ot-guests<?= error_attrs($errors, 'guests') ?>>
                            <?php for ($i = 1; $i <= $booking['max_guests']; $i++): ?>
                                <option value="<?= $i ?>" <?= (string) $i === $old['guests'] ? 'selected' : '' ?>><?= $i ?> <?= $i === 1 ? 'person' : 'people' ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label class="ot-pick">
                        <span class="visually-hidden">Date</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                        <select name="date" id="date" data-ot-date<?= error_attrs($errors, 'date') ?>>
                            <?php foreach ($dateOptions as $ymd => [$label, $open]): ?>
                                <option value="<?= e($ymd) ?>" <?= $ymd === $old['date'] ? 'selected' : '' ?> <?= $open ? '' : 'disabled' ?>><?= e($label) ?><?= $open ? '' : ' · closed' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="ot-pick">
                        <span class="visually-hidden">Preferred time</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        <select name="pref" data-ot-pref>
                            <?php foreach ($prefOptions as $t): ?>
                                <option value="<?= e($t) ?>" <?= $t === $prefTime ? 'selected' : '' ?>><?= e($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <?= field_error($errors, 'date') ?>
                <?= field_error($errors, 'guests') ?>
                <noscript><button class="btn btn-green btn-block ot-find" type="submit" name="find" value="1">Show times</button></noscript>

                <div class="ot-times" data-ot-times>
                    <p class="ot-label">Select a time</p>
                    <div class="ot-chips" data-ot-chips role="radiogroup" aria-label="Available times">
                        <?php foreach ($nearest as $t): ?>
                            <label class="ot-chip"><input type="radio" name="time" value="<?= e($t) ?>" <?= $old['time'] === $t ? 'checked' : '' ?> required><span><?= e($t) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="ot-chips ot-all" data-ot-all hidden></div>
                    <p class="ot-none" data-ot-none <?= $nearest ? 'hidden' : '' ?>><?= e($slots ? 'No tables left that day for ' . $old['guests'] . '. Try another date' : booking_day_note($shownDate)) ?><?php if ($site['phone']): ?>, or ring us on <a href="<?= e($telHref) ?>"><?= e($site['phone']) ?></a><?php endif; ?>.</p>
                    <?= field_error($errors, 'time') ?>

                    <ul class="ot-facts">
                        <?php if ($bookedToday >= 1): ?>
                            <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/></svg>Booked <?= $bookedToday ?> <?= $bookedToday === 1 ? 'time' : 'times' ?> today</li>
                        <?php endif; ?>
                        <li data-ot-left <?= $freeCount > 0 && $freeCount <= 6 ? '' : 'hidden' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="13" cy="12" r="8"/><path d="M13 8v4l2.5 1.5M2 9h3M1 12h4M2 15h3"/></svg><span>You’re in luck! We still have <strong data-ot-left-n><?= $freeCount ?></strong> times left</span></li>
                    </ul>
                    <button type="button" class="ot-more" data-ot-more <?= $freeCount > count($nearest) ? '' : 'hidden' ?>>View full availability</button>
                </div>

                <div class="ot-details" data-ot-details>
                    <div class="ot-summary" data-ot-summary>
                        <span data-ot-summary-text></span>
                        <button type="button" class="ot-change" data-ot-change>Change</button>
                    </div>

                    <p class="ot-label">Seating</p>
                    <div class="ot-seats">
                        <?php foreach ($booking['seating'] as $option): ?>
                            <label class="ot-seat"><input type="radio" name="seating" value="<?= e($option) ?>" <?= $old['seating'] === $option ? 'checked' : '' ?>><span><?= e($option) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <p class="field-hint ot-hint">The banquette runs along the wall under the gold tree.</p>

                    <div class="field">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" required autocomplete="name" maxlength="80"
                               value="<?= e($old['name']) ?>"<?= error_attrs($errors, 'name') ?>>
                        <?= field_error($errors, 'name') ?>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" required autocomplete="tel" maxlength="20"
                                   value="<?= e($old['phone']) ?>"<?= error_attrs($errors, 'phone') ?>>
                            <?= field_error($errors, 'phone') ?>
                        </div>
                        <div class="field">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required autocomplete="email" maxlength="120"
                                   value="<?= e($old['email']) ?>"<?= error_attrs($errors, 'email') ?>>
                            <?= field_error($errors, 'email') ?>
                        </div>
                    </div>
                    <div class="field">
                        <label for="notes">Special requests <span class="optional">(optional)</span></label>
                        <textarea id="notes" name="notes" rows="3" maxlength="500"
                                  placeholder="Allergies, a high chair, a birthday"<?= error_attrs($errors, 'notes') ?>><?= e($old['notes']) ?></textarea>
                        <?= field_error($errors, 'notes') ?>
                    </div>

                    <div class="field consent">
                        <label class="consent-label" for="marketing_consent">
                            <input type="checkbox" id="marketing_consent" name="marketing_consent" value="1"
                                   <?= $consent ? 'checked' : '' ?>>
                            <span>Email me now and then about offers and events at October. Optional — your table is booked either way, and you can stop any time.</span>
                        </label>
                    </div>

                    <p class="privacy-note">
                        We use your details to manage this booking and nothing else.
                        See our <a href="privacy.php">privacy notice</a>.
                    </p>
                    <button class="btn btn-green btn-block" type="submit">Complete booking</button>
                </div>
            </form>
            <script type="application/json" id="ot-data"><?= json_encode([
                'slots' => $slots, 'date' => $shownDate, 'guests' => (int) $old['guests'],
                'phone' => $site['phone'], 'tel' => $telHref,
                'open'  => (bool) ($errors || $old['time'] !== ''),
            ], JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
