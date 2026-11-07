<?php
/*
 * The weekend switch, shared by the day and week views.
 *
 * One file rather than two copies: the two views would drift, and the one
 * that drifted would be the one somebody was looking at when they needed to
 * trust it.
 *
 * Always describes the weekend being offered right now, whatever day or week
 * is on screen, so it reads the same on a Monday as on a Saturday. It rolls to
 * the next weekend by itself once Sunday has passed, so a pause never has to
 * be remembered and undone.
 *
 * The caller sets:
 *   $weekendView      'day' or 'week' — where to come back to after pressing
 *   $weekendFormDate  the date that identifies the page to come back to
 */

require_once __DIR__ . '/../includes/hours.php';
require_once __DIR__ . '/../includes/settings.php';

$weekendView     = $weekendView     ?? 'day';
$weekendFormDate = $weekendFormDate ?? (new DateTimeImmutable('today', $tz))->format('Y-m-d');

$weekendDates  = hours_weekend_dates();
$weekendPaused = hours_weekend_paused();

/*
 * A weekend can also be shut by a hand-written date in config.php. The switch
 * has to tell the truth about that, or it sits there saying "taking bookings
 * as normal" over a weekend the form is refusing — and somebody presses Pause,
 * sees nothing change, and stops trusting the button.
 */
$weekendOpenDates = array_values(array_filter($weekendDates, 'hours_takes_bookings'));
$weekendShut      = $weekendPaused || $weekendOpenDates === [];

$weekendFrom = new DateTimeImmutable(reset($weekendDates), $tz);
$weekendTo   = new DateTimeImmutable(end($weekendDates), $tz);
$weekendSpan = $weekendFrom->format('D j') . ' – ' . $weekendTo->format('D j M');
$weekendMeta = $weekendPaused ? setting_meta('paused_weekend') : null;

/*
 * When it was paused is worth showing; who is usually not. There is one shared
 * staff login, so staff_actor() says "staff" to a room full of staff. Show a
 * name only if somebody is actually identified.
 */
$weekendWhen = '';
$weekendWho  = '';
if ($weekendMeta) {
    $stamp = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $weekendMeta['updated_at'], $tz)
          ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $weekendMeta['updated_at'], $tz);
    if ($stamp) {
        $weekendWhen = $stamp->format('Y-m-d') === $today->format('Y-m-d')
            ? 'Paused at ' . $stamp->format('H:i') . ' today.'
            : 'Paused ' . $stamp->format('D j M') . '.';
    }
    $actor = trim((string) $weekendMeta['updated_by']);
    if ($actor !== '' && $actor !== 'staff') {
        $weekendWho = 'By ' . $actor . '.';
    }
}
?>
<?php /* The date rides in the action, the way the status forms in index.php
         already do it, so the handler's existing $date is correct and there is
         not a second way of saying the same thing. */ ?>
<form class="weekend-switch<?= $weekendShut ? ' is-paused' : '' ?>" method="post"
      action="index.php?date=<?= e($weekendFormDate) ?>">
    <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
    <?php /* 'day' or 'week', never a URL — the handler rebuilds the address
             itself so a crafted post cannot bounce anyone off the site. */ ?>
    <input type="hidden" name="view" value="<?= e($weekendView) ?>">
    <input type="hidden" name="weekend" value="<?= $weekendPaused ? 'open' : 'pause' ?>">

    <div class="weekend-text">
        <strong>This weekend<span class="weekend-span"><?= e($weekendSpan) ?></span></strong>
        <span class="weekend-state">
            <?php if ($weekendPaused): ?>
                Not taking online bookings.
                <?= $weekendWhen !== '' ? e($weekendWhen) : '' ?>
                <?= $weekendWho !== '' ? e($weekendWho) : '' ?>
                You can still add one by hand.
            <?php elseif ($weekendShut): ?>
                Not taking online bookings — these dates are closed in the site
                settings, not by this button. You can still add one by hand.
            <?php else: ?>
                Taking online bookings as normal.
            <?php endif; ?>
        </span>
    </div>

    <?php if (!$weekendShut || $weekendPaused): ?>
        <button class="<?= $weekendPaused ? 'btn-p' : 'btn-s btn-pause' ?>" type="submit">
            <?= $weekendPaused ? 'Allow bookings again' : 'Pause bookings for this weekend' ?>
        </button>
    <?php endif; ?>
</form>
