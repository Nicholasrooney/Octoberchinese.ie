<?php
/*
 * Day view — the default screen, and the one that replaces the paper diary.
 */

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/mailer.php';
staff_require_login();
staff_require_database();

$tz    = new DateTimeZone('Europe/Dublin');
$today = new DateTimeImmutable('today', $tz);

$date = $_GET['date'] ?? $today->format('Y-m-d');
$day  = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
if (!$day) {
    $day  = $today;
    $date = $today->format('Y-m-d');
}

// Status buttons and the weekend toggle post back here.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!staff_check_token($_POST['token'] ?? null)) {
        staff_flash('That action expired. Try again.');
    } elseif (isset($_POST['weekend'])) {
        /*
         * One button, both directions. The form sends the state it wants
         * rather than "flip it", so a double tap or a stale page cannot end
         * up doing the opposite of what the person pressing it expected.
         */
        $wantPaused = $_POST['weekend'] === 'pause';

        if (hours_set_weekend_paused($wantPaused, staff_actor())) {
            $dates = hours_weekend_dates();
            $first = new DateTimeImmutable(reset($dates), $tz);
            $last  = new DateTimeImmutable(end($dates), $tz);
            // Name both months when the weekend straddles one, or a Hallowe'en
            // weekend reads as "30-1 November".
            $span  = $first->format('M') === $last->format('M')
                ? $first->format('j') . '-' . $last->format('j M')
                : $first->format('j M') . ' - ' . $last->format('j M');

            staff_flash($wantPaused
                ? 'Online bookings paused for this weekend (' . $span . '). '
                  . 'Bookings already taken are unaffected, and you can still add one here by hand.'
                : 'Online bookings open again for this weekend (' . $span . ').');
        } else {
            // The write failed, so say so rather than show a green tick over
            // a weekend that is still taking bookings.
            staff_flash('That did not save — the weekend is unchanged. Try again, '
                      . 'and if it keeps failing the database is not reachable.');
        }

        /*
         * Back to whichever view the button was pressed on. The form sends
         * 'day' or 'week', never an address, so the only two destinations are
         * the two built here — a crafted post cannot bounce anyone elsewhere.
         */
        if (($_POST['view'] ?? '') === 'week') {
            header('Location: week.php?from=' . urlencode($date), true, 303);
            exit;
        }
    } else {
        $id     = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if ($id && in_array($status, booking_statuses(), true)) {
            $before = booking_get($id);
            if (booking_set_status($id, $status, staff_actor()) && $before) {
                // Only the ones worth an email: a freed table or a no-show.
                if ($status === 'cancelled' || $status === 'no_show') {
                    mail_booking_change_alert(
                        $before,
                        $status === 'cancelled' ? 'cancelled' : 'marked a no-show'
                    );
                }
            }
            staff_flash('Updated.');
        }
    }
    header('Location: index.php?date=' . urlencode($date), true, 303);
    exit;
}

$rows    = bookings_for_date($date);
$summary = bookings_day_summary($date);
$max     = (int) ($booking['max_bookings_per_hour'] ?? 0);
$used    = capacity_used($date);
$flash   = staff_flash();

// Group the bookings under their time slot.
$bySlot = [];
foreach ($rows as $row) {
    $bySlot[$row['booking_time']][] = $row;
}
ksort($bySlot);

staff_head($day->format('D j M'), 'day');
?>

<?php if ($flash): ?><p class="flash"><?= e($flash) ?></p><?php endif; ?>

<?php
/*
 * A date taken off the public form still shows here, with its bookings —
 * staff can add one by hand if they want to.
 *
 * Only the hand-written dates get a banner. When the day is shut because the
 * weekend is paused, the switch below says so already and offers the button
 * to undo it, so a banner on top of it would be the same sentence twice.
 */
require_once __DIR__ . '/../includes/hours.php';
$blockedDates = $booking['no_bookings_dates'] ?? [];
$blockedByList = array_key_exists($date, $blockedDates) || in_array($date, $blockedDates, true);
// Only worth repeating the message if somebody wrote a specific reason.
$blockedWhy  = trim((string) ($blockedDates[$date] ?? ''));
?>
<?php if ($blockedByList): ?>
    <p class="flash flash-warn">Closed to new online bookings.<?= $blockedWhy !== '' ? ' ' . e($blockedWhy) : '' ?>
        You can still add one here by hand.</p>
<?php endif; ?>

<?php
$weekendView     = 'day';
$weekendFormDate = $date;
require __DIR__ . '/_weekend-switch.php';
?>

<div class="dayhead">
    <a class="nav-arrow" href="?date=<?= e($day->modify('-1 day')->format('Y-m-d')) ?>" aria-label="Previous day">&#8249;</a>
    <div class="dayhead-mid">
        <h1><?= e($day->format('l')) ?><span><?= e($day->format('j F Y')) ?></span></h1>
        <?php if ($date !== $today->format('Y-m-d')): ?>
            <a class="today-link" href="index.php">Back to today</a>
        <?php endif; ?>
    </div>
    <a class="nav-arrow" href="?date=<?= e($day->modify('+1 day')->format('Y-m-d')) ?>" aria-label="Next day">&#8250;</a>
</div>

<div class="totals">
    <div><strong><?= (int) $summary['bookings'] ?></strong><span>bookings</span></div>
    <div><strong><?= (int) $summary['covers'] ?></strong><span>covers</span></div>
    <a class="btn-p btn-add" href="booking-edit.php?date=<?= e($date) ?>">+ Add booking</a>
</div>

<?php if (!$bySlot): ?>
    <div class="card empty">
        <p>Nothing booked for <?= e($day->format('l j F')) ?>.</p>
        <a class="btn-p" href="booking-edit.php?date=<?= e($date) ?>">Add the first one</a>
    </div>
<?php else: ?>
    <?php foreach ($bySlot as $time => $slotRows): ?>
        <section class="slot">
            <h2 class="slot-head">
                <?= e($time) ?>
                <?php if ($max > 0): ?>
                    <?php $inHour = (int) ($used[capacity_hour($time)] ?? 0); ?>
                    <span class="slot-cap <?= $inHour >= $max ? 'full' : '' ?>">
                        <?= $inHour ?>/<?= $max ?> <?= e(capacity_noun($max)) ?> this hour
                    </span>
                <?php endif; ?>
            </h2>

            <?php foreach ($slotRows as $b): ?>
                <?php staff_booking_card($b); ?>
                <?php if (!in_array($b['status'], ['cancelled', 'completed'], true)): ?>
                    <form class="quick" method="post" action="index.php?date=<?= e($date) ?>">
                        <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                        <?php
                        $next = [
                            'requested' => [['confirmed', 'Confirm'], ['cancelled', 'Cancel']],
                            'confirmed' => [['seated', 'Seated'], ['no_show', 'No show'], ['cancelled', 'Cancel']],
                            'seated'    => [['completed', 'Done']],
                            'no_show'   => [['confirmed', 'Undo']],
                        ][$b['status']] ?? [];
                        foreach ($next as [$value, $label]):
                        ?>
                            <button class="btn-s btn-<?= e($value) ?>" name="status" value="<?= e($value) ?>"><?= e($label) ?></button>
                        <?php endforeach; ?>
                    </form>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php staff_foot(); ?>
