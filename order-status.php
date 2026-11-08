<?php
/*
 * The customer's order tracker: received → preparing → ready → collected.
 * Linked from the confirmation and the receipt email. The long random token
 * in the link is the only key, so one customer cannot look up another's order.
 * Refreshes itself every 20 seconds while the order is still in progress.
 */
require_once __DIR__ . '/includes/orders.php';

redirect_home_unless(ordering_online());

$order = null;
try {
    $order = order_by_token((string) ($_GET['t'] ?? ''));
} catch (Throwable $e) {
    error_log('Order status failed: ' . $e->getMessage());
}

if (!$order) {
    http_response_code(404);
}

$live = $order && in_array($order['status'], ['new', 'accepted', 'ready'], true);

header('X-Robots-Tag: noindex, nofollow');
if ($live) {
    header('Refresh: 20');
}

$pageTitle       = $order ? 'Order ' . order_ref((int) $order['id']) . ' | October Chinese Dining' : 'Order not found | October Chinese Dining';
$pageDescription = 'Your click and collect order at October Chinese Dining.';
$activePage      = 'order';
$pageStyles      = ['css/order.css'];
$pageSchema      = [];
require __DIR__ . '/includes/header.php';
?>

<section class="page-hero page-hero-slim">
    <img class="page-hero-img" src="images/oct/wonton-soup.jpg" alt="" aria-hidden="true">
    <div class="container page-hero-inner">
        <p class="eyebrow">Click &amp; collect</p>
        <h1>Your order <span lang="zh-Hans">订单</span></h1>
    </div>
</section>

<div class="container">
    <div class="track">
        <?php if (!$order): ?>
            <div class="track-card">
                <h2>We could not find that order</h2>
                <p>Check the link in your receipt email, or ring us on <?= e($site['phone']) ?>.</p>
                <a class="btn btn-green" href="order.php">Start a new order</a>
            </div>
        <?php else:
            $steps  = ['new' => 'Received', 'accepted' => 'Preparing', 'ready' => 'Ready', 'collected' => 'Collected'];
            $keys   = array_keys($steps);
            $at     = array_search($order['status'], $keys, true);
            $first  = mail_first_name($order['name']);
            $titles = [
                'new'       => 'Thanks, ' . $first . '. We have your order.',
                'accepted'  => 'The kitchen is cooking your order.',
                'ready'     => 'Your order is ready to collect.',
                'collected' => 'Enjoy your meal, ' . $first . '.',
                'cancelled' => 'This order was cancelled.',
            ];
        ?>
            <div class="track-card" role="status" <?= isset($_GET['new']) ? 'data-track-view="order_placed"' : '' ?>>
                <p class="track-ref">Order <?= e(order_ref((int) $order['id'])) ?></p>
                <h2><?= e($titles[$order['status']] ?? '') ?></h2>

                <?php if ($order['status'] === 'cancelled'): ?>
                    <p class="track-cancel">If you didn’t expect this, please ring us on <?= e($site['phone']) ?>.</p>
                <?php else: ?>
                    <p class="track-when">Collect <?= $order['asap'] ? 'as soon as possible, from about' : 'today at' ?> <strong><?= e($order['collect_time']) ?></strong>
                        from <?= e($site['address']) ?>.</p>
                    <?php if ($order['status'] === 'ready'): ?>
                        <p class="track-ready">Come on in and give your name or order number at the counter.</p>
                    <?php endif; ?>
                    <ol class="track-steps">
                        <?php foreach ($keys as $i => $k): ?>
                            <li class="<?= $i < $at ? 'is-done' : ($i === $at ? ($k === 'collected' ? 'is-done' : 'is-now') : '') ?>">
                                <span><?= $i < $at || $k === 'collected' && $i === $at ? '✓' : $i + 1 ?></span><?= e($steps[$k]) ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <ul class="track-lines">
                    <?php foreach ($order['items'] as $l): ?>
                        <li>
                            <span><?= (int) $l['qty'] ?> × <?= e($l['name']) ?>
                                <?php if ($l['variant'] !== ''): ?><small><?= e($l['variant']) ?></small><?php endif; ?>
                                <?php if ($l['note'] !== ''): ?><small>“<?= e($l['note']) ?>”</small><?php endif; ?>
                            </span>
                            <span><?= e(order_money($l['qty'] * (float) $l['unit_price'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="track-total"><span>Total</span><span><?= e(order_money((float) $order['subtotal'])) ?></span></p>
                <p class="ob-pay"><?= e($ordering['payment_note']) ?></p>

                <p class="track-help">
                    <?php if ($live): ?>This page updates by itself. <?php endif; ?>
                    Need to change something? Ring us on <a href="tel:+353<?= e(ltrim(preg_replace('/[^0-9]/', '', $site['phone']), '0')) ?>"><?= e($site['phone']) ?></a>
                    and quote <?= e(order_ref((int) $order['id'])) ?>.
                </p>
                <a class="btn btn-green" href="<?= e($site['maps_url']) ?>" target="_blank" rel="noopener">Directions</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
