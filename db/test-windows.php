<?php
/*
 * Tests for booking windows. Run from the site folder:
 *
 *     php db/test-windows.php
 */
require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../includes/booking-windows.php';

fresh_database();
$tz       = new DateTimeZone('Europe/Dublin');
$today    = new DateTimeImmutable('today', $tz);
$tomorrow = $today->modify('+1 day')->format('Y-m-d');
$booking['windows_only'] = true;
$booking['online'] = true;
$booking['slot_minutes'] = 30;

section('No windows');
check('no slots without a window', booking_public_slots($tomorrow), []);
check('booking is off', windows_next_date(), null);

section('Adding');
[$id, $err] = window_add($tomorrow, '15:00', '19:00', 'test', 'tester');
check('saved', $err, null);
check('slots run first to last, every 30 minutes', windows_slots_for_date($tomorrow),
      ['15:00', '15:30', '16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00']);
check('next bookable date is tomorrow', windows_next_date(), $tomorrow);
check('takes the date', booking_public_takes_date($tomorrow), true);
check('other days still closed', booking_public_slots($today->modify('+2 days')->format('Y-m-d')), []);
[, $e2] = window_add($tomorrow, '19:00', '15:00', '', 'tester');
check('last before first refused', $e2 !== null, true);
[, $e3] = window_add($today->modify('-1 day')->format('Y-m-d'), '15:00', '19:00', '', 'tester');
check('past date refused', $e3, 'That date has passed.');
[$id2] = window_add($tomorrow, '20:00', '21:00', '', 'tester');
check('two windows on one day combine', count(windows_slots_for_date($tomorrow)), 12);

section('Closing and reopening');
window_set_open($id, false);
check('closing removes its times', windows_slots_for_date($tomorrow), ['20:00', '20:30', '21:00']);
window_set_open($id2, false);
check('all closed: nothing bookable', windows_slots_for_date($tomorrow), []);
window_set_open($id, true);
check('reopened', count(windows_slots_for_date($tomorrow)), 9);
check('past times today are not offered', windows_slots_for_date($today->format('Y-m-d'), new DateTimeImmutable('today 23:59', $tz)), []);

finish();
