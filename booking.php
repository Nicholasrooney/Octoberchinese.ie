<?php
require __DIR__ . '/includes/booking-handler.php';

$pageTitle       = 'Book a Table | October Chinese Dining, Dún Laoghaire';
$pageDescription = 'Book a table at October Chinese Dining on George’s Street Upper, Dún Laoghaire. Choose your date, time and party size. We will contact you if anything needs to change.';
$pageKeywords    = 'book a table Dún Laoghaire, Chinese restaurant booking Dún Laoghaire, reserve table Dún Laoghaire';
$activePage      = 'booking';

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

// Which slots still have room on the date being shown. Advisory only — the
// binding check happens inside the insert transaction.
$shownDate = $old['date'] !== '' ? $old['date'] : $today->format('Y-m-d');
$slots     = booking_slot_availability($shownDate, (int) $old['guests']);
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

            <form class="booking-form" method="post" action="booking.php" novalidate>
                <input type="hidden" name="token" value="<?= e($_SESSION['booking_token']) ?>">
                <div class="hp" aria-hidden="true">
                    <label for="website">Leave this empty</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <fieldset>
                    <legend>Your table</legend>
                    <div class="field-row">
                        <div class="field">
                            <label for="date">Date</label>
                            <input type="date" id="date" name="date" required
                                   min="<?= e($today->format('Y-m-d')) ?>" max="<?= e($lastDay->format('Y-m-d')) ?>"
                                   value="<?= e($old['date']) ?>"<?= error_attrs($errors, 'date') ?>>
                            <?= field_error($errors, 'date') ?>
                        </div>
                        <div class="field">
                            <label for="time">Time</label>
                            <select id="time" name="time" required<?= error_attrs($errors, 'time') ?>>
                                <option value="">Choose a time</option>
                                <?php foreach ($slots as $t => $free): ?>
                                    <option value="<?= e($t) ?>"
                                            <?= $old['time'] === $t ? 'selected' : '' ?>
                                            <?= $free ? '' : 'disabled' ?>><?= e($t) ?><?= $free ? '' : ' — fully booked' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="slot-note" id="slot-note" <?= $slots ? 'hidden' : '' ?>>
                                <?= $slots ? '' : e(booking_day_note($shownDate)) ?>
                            </p>
                            <?= field_error($errors, 'time') ?>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label for="guests">Number of guests</label>
                            <select id="guests" name="guests" required<?= error_attrs($errors, 'guests') ?>>
                                <?php for ($i = 1; $i <= $booking['max_guests']; $i++): ?>
                                    <option value="<?= $i ?>" <?= (string) $i === $old['guests'] ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <?= field_error($errors, 'guests') ?>
                        </div>
                        <div class="field">
                            <label for="seating">Seating</label>
                            <select id="seating" name="seating">
                                <?php foreach ($booking['seating'] as $option): ?>
                                    <option value="<?= e($option) ?>" <?= $old['seating'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="field-hint">The banquette runs along the wall under the gold tree.</p>
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Your details</legend>
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
                        <label for="notes">Anything we should know? <span class="optional">(optional)</span></label>
                        <textarea id="notes" name="notes" rows="4" maxlength="500"
                                  placeholder="Allergies, a high chair, a birthday"<?= error_attrs($errors, 'notes') ?>><?= e($old['notes']) ?></textarea>
                        <?= field_error($errors, 'notes') ?>
                    </div>
                </fieldset>

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
                <button class="btn btn-green btn-block" type="submit">Request booking</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
