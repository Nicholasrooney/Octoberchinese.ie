<?php
/*
 * Tests for click and collect ordering. Run from the site folder:
 *
 *     php db/test-orders.php
 */

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../includes/orders.php';

fresh_database();
$tz = new DateTimeZone('Europe/Dublin');

// Pin the hours and rules so the tests don't depend on config.php.
$booking['service_hours'] = array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], ['12:30', '21:30']);
$ordering = array_merge($ordering, [
    'enabled' => true, 'prep_minutes' => 25, 'slot_minutes' => 15, 'last_order_before_close' => 15,
    'max_orders_per_slot' => 2, 'lunch_until' => '16:00', 'min_order' => 0, 'max_qty_per_item' => 20,
]);

section('Catalogue');
$cat = order_catalogue();
check('has à la carte and lunch dishes', count($cat['items']) > 40, true);
$duck = null; $chicken = null; $bowl = null; $dumpling = null;
foreach ($cat['items'] as $k => $it) {
    if ($it['name'] === 'Crispy Aromatic Duck') $duck = $it;
    if ($it['name'] === 'Stir-fried Chicken Fillet') $chicken = $it;
    if ($it['name'] === 'Cantonese Roast Duck' && $it['menu'] === 'lunch') $bowl = $it;
    if ($it['name'] === 'Beef Dumplings' && $it['menu'] === 'lunch') $dumpling = $it;
}
check('duck has quarter and half', array_column($duck['variants'], 'label'), ['Quarter', 'Half']);
check('chicken choices parsed from "Choice of"', $chicken['choices'], ['Ginger & scallions', 'Curry', 'Kung po', 'Cashew nuts']);
check('lunch bowl offers a soft drink', $bowl['addons']['drink']['price'], 1.5);
check('lunch dumplings: steamed or in soup', $dumpling['choices'], ['Steamed', 'In hot & sour soup']);

section('Collection times');
$at = fn($s) => new DateTimeImmutable("2026-11-09 $s", $tz);
check('before opening: first slot is opening + prep, rounded', order_slots($at('10:00'))[0], '13:00');
check('mid-afternoon: now + prep, rounded up', order_slots($at('15:07'))[0], '15:45');
$late = order_slots($at('15:07'));
check('last slot is 15 minutes before close', end($late), '21:15');
check('after last orders: none', order_slots($at('21:00')), []);
check('closed message after last orders', strpos((string) order_closed_message($at('21:00')), 'not taking any more orders') !== false, true);

section('Pricing');
$basket = [
    ['key' => $duck['key'], 'variant' => 1, 'qty' => 1],
    ['key' => $chicken['key'], 'qty' => 2, 'choice' => 'Curry', 'note' => 'extra   spicy'],
];
$p = order_price_basket($basket, '18:00');
check('server prices the basket', $p['subtotal'], 27.50 + 2 * 18.95);
check('item count', $p['count'], 3);
check('choice recorded on the line', $p['lines'][1]['variant'], 'Curry');
check('note tidied', $p['lines'][1]['note'], 'extra spicy');
check('add-on adds its price', order_price_basket([['key' => $bowl['key'], 'qty' => 1, 'addons' => ['drink']]], '13:00')['subtotal'], 17.0);
throws('missing choice is refused', OrderError::class, fn() => order_price_basket([['key' => $chicken['key'], 'qty' => 1]], '18:00'));
throws('unknown dish is refused', OrderError::class, fn() => order_price_basket([['key' => 'nope', 'qty' => 1]], '18:00'));
throws('silly quantity is refused', OrderError::class, fn() => order_price_basket([['key' => $duck['key'], 'qty' => 99]], '18:00'));
throws('lunch dish after 16:00 is refused', OrderError::class, fn() => order_price_basket([['key' => $bowl['key'], 'qty' => 1]], '17:00'));
throws('empty basket is refused', OrderError::class, fn() => order_price_basket([], '18:00'));

section('Saving and slot limits');
$details = ['name' => 'Test Person', 'phone' => '0871234567', 'email' => 't@example.com',
            'date' => '2026-11-09', 'time' => '18:00', 'asap' => false, 'notes' => ''];
$o1 = order_save($details, $p);
check('saved with lines', count($o1['items']), 2);
check('status starts as new', $o1['status'], 'new');
check('token is 32 hex characters', (bool) preg_match('/^[a-f0-9]{32}$/', $o1['public_token']), true);
check('found by token', order_by_token($o1['public_token'])['id'], $o1['id']);
check('bad token finds nothing', order_by_token('abc'), null);
order_save($details, $p);
throws('third order in a full slot is refused', OrderError::class, fn() => order_save($details, $p));
check('full slot is hidden from the times', in_array('18:00', order_slots($at('15:00')), true), false);
order_set_status((int) $o1['id'], 'cancelled');
check('cancelling frees the place', in_array('18:00', order_slots($at('15:00')), true), true);
check('ref format', order_ref(42), '#0042');

finish();
