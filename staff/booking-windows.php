<?php
/*
 * Booking windows: open online booking for chosen days and times.
 *
 * Online booking is off unless a window here is open. "Add booking window"
 * takes a date, a first and a last booking time; the website then offers those
 * times straight away. "Close" takes them off the website at once (bookings
 * already made are kept); "Open again" puts them back.
 */

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/booking-windows.php';
staff_require_login();
staff_require_database();

$tz       = new DateTimeZone('Europe/Dublin');
$today    = new DateTimeImmutable('today', $tz);
$tomorrow = $today->modify('+1 day')->format('Y-m-d');
$times    = windows_time_choices();
$errors   = null;
$form     = ['date' => $tomorrow, 'first' => '15:00', 'last' => '19:00', 'note' => ''];
$showForm = isset($_GET['add']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!staff_check_token($_POST['token'] ?? null)) {
        staff_flash('That action expired. Try again.');
        header('Location: booking-windows.php', true, 303);
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'add') {
        foreach ($form as $k => $v) {
            $form[$k] = trim((string) ($_POST[$k] ?? $v));
        }
        [$newId, $errors] = window_add($form['date'], $form['first'], $form['last'], $form['note'], staff_actor());
        if ($newId) {
            $d = new DateTimeImmutable($form['date'], $tz);
            staff_flash('Booking window added: ' . $d->format('l j F') . ', ' . $form['first'] . ' to ' . $form['last'] . '. Those times are now bookable on the website.');
            header('Location: booking-windows.php#w-' . $newId, true, 303);
            exit;
        }
        $showForm = true;
    } elseif ($id && in_array($action, ['close', 'open'], true)) {
        window_set_open($id, $action === 'open');
        staff_flash($action === 'open' ? 'Window opened again. Those times are bookable on the website.' : 'Window closed. Those times are off the website. Any bookings already made are kept.');
        header('Location: booking-windows.php#w-' . $id, true, 303);
        exit;
    } elseif ($id && $action === 'delete') {
        window_delete($id);
        staff_flash('Window deleted.');
        header('Location: booking-windows.php', true, 303);
        exit;
    }
}

$upcoming = windows_list($today->format('Y-m-d'));
$past     = windows_past($today->format('Y-m-d'));
$liveNow  = windows_any_upcoming();
$flash    = staff_flash();

$fmtDay = function (string $date) use ($today, $tz): string {
    $d = new DateTimeImmutable($date, $tz);
    if ($d == $today) return 'Today, ' . $d->format('j M');
    if ($d == $today->modify('+1 day')) return 'Tomorrow, ' . $d->format('j M');
    return $d->format('D j M');
};

staff_head('Booking windows', 'windows');
?>

<?php if ($flash): ?><p class="flash"><?= e($flash) ?></p><?php endif; ?>

<div class="orders-top">
    <div>
        <h1>Booking windows</h1>
        <p class="sub">Online booking is closed except during the times you open here.</p>
    </div>
    <?php if (!$showForm): ?>
        <a class="btn-p" href="booking-windows.php?add#add">+ Add booking window</a>
    <?php endif; ?>
</div>

<p class="flash <?= $liveNow ? '' : 'flash-warn' ?>">
    <?= $liveNow
        ? 'Online booking is ON: customers can book the open times below. The “Book a table” button is showing on the website.'
        : 'Online booking is OFF: there are no open windows, so the website asks people to ring instead.' ?>
</p>

<?php if ($showForm): ?>
    <form class="card form" id="add" method="post" action="booking-windows.php">
        <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
        <input type="hidden" name="action" value="add">
        <h2>Add booking window</h2>
        <?php if ($errors): ?><p class="err" role="alert"><?= e($errors) ?></p><?php endif; ?>
        <div class="row">
            <div class="field">
                <label for="date">Date</label>
                <input id="date" name="date" type="date" required value="<?= e($form['date']) ?>"
                       min="<?= e($today->format('Y-m-d')) ?>" max="<?= e($today->modify('+' . (int) $booking['days_ahead'] . ' days')->format('Y-m-d')) ?>">
            </div>
            <div class="field">
                <label for="first">First booking time</label>
                <select id="first" name="first">
                    <?php foreach ($times as $t): ?><option <?= $t === $form['first'] ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="last">Last booking time</label>
                <select id="last" name="last">
                    <?php foreach ($times as $t): ?><option <?= $t === $form['last'] ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field">
            <label for="note">Note <span class="opt">(optional, staff only)</span></label>
            <input id="note" name="note" type="text" maxlength="200" value="<?= e($form['note']) ?>" placeholder="e.g. quiet afternoon, two tables free">
        </div>
        <p class="sub">Customers can book any time from the first to the last booking time, every <?= (int) $booking['slot_minutes'] ?> minutes, up to <?= (int) $booking['max_bookings_per_hour'] ?> bookings an hour.</p>
        <div class="form-do">
            <button class="btn-p" type="submit">Submit</button>
            <a class="btn-s" href="booking-windows.php">Cancel</a>
        </div>
    </form>
<?php endif; ?>

<h2 class="orders-h">Upcoming <span class="count"><?= count($upcoming) ?></span></h2>
<?php if (!$upcoming): ?>
    <div class="card empty">
        <p>No booking windows yet.</p>
        <p class="sub">Press “+ Add booking window” to open some times for online booking.</p>
    </div>
<?php endif; ?>

<?php foreach ($upcoming as $w): $open = (int) $w['is_open'] === 1; $n = window_booking_count($w); ?>
    <article class="card window <?= $open ? 'window-open' : 'window-closed' ?>" id="w-<?= (int) $w['id'] ?>">
        <div class="window-main">
            <div>
                <h3><?= e($fmtDay($w['window_date'])) ?> · <?= e($w['first_time']) ?> to <?= e($w['last_time']) ?></h3>
                <p class="sub">
                    <span class="tag <?= $open ? 'tag-o-ready' : 'tag-o-cancelled' ?>"><?= $open ? 'Open for booking' : 'Closed' ?></span>
                    · <?= $n ?> <?= $n === 1 ? 'booking' : 'bookings' ?> in this window
                    <?php if ($w['note'] !== ''): ?> · <?= e($w['note']) ?><?php endif; ?>
                </p>
            </div>
            <div class="ticket-do">
                <form method="post">
                    <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                    <?php if ($open): ?>
                        <button class="btn-s btn-pause" name="action" value="close">Close</button>
                    <?php else: ?>
                        <button class="btn-s btn-go" name="action" value="open">Open again</button>
                    <?php endif; ?>
                </form>
                <?php if (!$open): ?>
                    <form method="post" data-confirm="Delete this window? This doesn’t affect any bookings.">
                        <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                        <button class="btn-s btn-cancel" name="action" value="delete">Delete</button>
                    </form>
                <?php endif; ?>
                <a class="btn-s" href="index.php?date=<?= e($w['window_date']) ?>">See the day</a>
            </div>
        </div>
    </article>
<?php endforeach; ?>

<?php if ($past): ?>
    <details class="window-past">
        <summary>Past windows</summary>
        <ul>
            <?php foreach ($past as $w): ?>
                <li><?= e((new DateTimeImmutable($w['window_date'], $tz))->format('D j M')) ?> · <?= e($w['first_time']) ?> to <?= e($w['last_time']) ?><?= (int) $w['is_open'] ? '' : ' (closed)' ?></li>
            <?php endforeach; ?>
        </ul>
    </details>
<?php endif; ?>

<?php staff_foot(); ?>
