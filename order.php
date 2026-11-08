<?php
/*
 * Click and collect ordering.
 *
 * Works like the big ordering apps: browse the menu, tap a dish to add it,
 * review the basket, pick a collection time, place the order. The kitchen is
 * emailed and sees it on staff/orders.php; the customer gets a receipt and a
 * page that shows when the order is ready.
 */
require __DIR__ . '/includes/order-handler.php';
require_once __DIR__ . '/includes/brand.php';

$catalogue = order_catalogue();
$slots     = order_slots();
$closedMsg = $slots ? null : order_closed_message();

$pageTitle       = 'Order for Collection | October Chinese Dining, Dún Laoghaire';
$pageDescription = 'Order October Chinese food online for collection from George’s Street Upper, Dún Laoghaire. Pick your dishes, choose a time, and pay when you collect.';
$pageKeywords    = 'Chinese takeaway Dún Laoghaire, order Chinese food online Dún Laoghaire, click and collect Dún Laoghaire';
$activePage      = 'order';
$pageStyles      = ['css/order.css'];
$pageScripts     = ['js/order.js'];

require_once __DIR__ . '/includes/seo.php';
$pageSchema = [seo_breadcrumbs(['Order for collection' => 'order.php'])];
require __DIR__ . '/includes/header.php';

// What the page script needs: dishes (keys, prices, options), times, rules.
$jsItems = [];
foreach ($catalogue['items'] as $key => $it) {
    $jsItems[$key] = [
        'name' => $it['name'], 'menu' => $it['menu'], 'variants' => $it['variants'],
        'choices' => $it['choices'], 'addons' => $it['addons'],
    ];
}
$jsData = [
    'items'      => $jsItems,
    'lunchUntil' => $ordering['lunch_until'],
    'minOrder'   => (float) $ordering['min_order'],
    'maxQty'     => (int) $ordering['max_qty_per_item'],
    'open'       => (bool) $slots,
    'restore'    => $orderBasketJson,
];

$first = $slots[0] ?? null;
$fieldError = fn($f) => isset($orderErrors[$f]) ? '<p class="field-error" id="' . $f . '-error">' . e($orderErrors[$f]) . '</p>' : '';
$errAttr    = fn($f) => isset($orderErrors[$f]) ? ' aria-invalid="true" aria-describedby="' . $f . '-error"' : '';
?>

<section class="page-hero page-hero-slim">
    <img class="page-hero-img" src="images/oct/dumplings-top.jpg" alt="" aria-hidden="true">
    <div class="container page-hero-inner">
        <p class="eyebrow">Click &amp; collect</p>
        <h1>Order for collection <span lang="zh-Hans">外卖自取</span></h1>
        <?php if ($first): ?>
            <p class="order-status-pill is-open"><span class="dot"></span> Taking orders · ready from <strong><?= e($first) ?></strong> today</p>
        <?php else: ?>
            <p class="order-status-pill is-closed"><span class="dot"></span> <?= e($closedMsg) ?></p>
        <?php endif; ?>
    </div>
</section>

<noscript>
    <div class="container"><p class="form-alert">Online ordering needs JavaScript switched on. You can also ring us on <?= e($site['phone']) ?> to order.</p></div>
</noscript>

<nav class="order-cats" aria-label="Menu sections">
    <div class="container order-cats-inner">
        <?php foreach ($catalogue['sections'] as $sec): ?>
            <a href="#sec-<?= e($sec['id']) ?>"><?= e($sec['title']) ?><?= $sec['menu'] === 'lunch' ? ' <small>Lunch</small>' : '' ?></a>
        <?php endforeach; ?>
    </div>
</nav>

<div class="container order-layout">
    <div class="order-menu">
        <?php if ($orderErrors): ?>
            <div class="form-alert" role="alert" id="order-errors">
                <?= e($orderErrors['form'] ?? $orderErrors['basket'] ?? 'Some details need fixing. Open your basket to check them.') ?>
            </div>
        <?php endif; ?>

        <p class="order-intro">Tap a dish to add it. Collection from 60 George’s Street Upper, Dún Laoghaire. <?= e($ordering['payment_note']) ?></p>

        <?php $lunchShown = false; foreach ($catalogue['sections'] as $sec): ?>
            <?php if ($sec['menu'] === 'lunch' && !$lunchShown): $lunchShown = true; ?>
                <div class="order-lunch-banner">
                    <h2>Lunch menu</h2>
                    <p>For collection before <?= e($ordering['lunch_until']) ?>.</p>
                </div>
            <?php endif; ?>
            <section class="order-sec" id="sec-<?= e($sec['id']) ?>" aria-labelledby="h-<?= e($sec['id']) ?>">
                <header class="order-sec-head">
                    <h2 id="h-<?= e($sec['id']) ?>"><?= e($sec['title']) ?></h2>
                    <?php if ($sec['cn']): ?><span lang="zh-Hans"><?= e($sec['cn']) ?></span><?php endif; ?>
                    <?php if ($sec['note']): ?><small><?= e($sec['note']) ?></small><?php endif; ?>
                </header>
                <ul class="order-items">
                    <?php foreach ($sec['keys'] as $key): $it = $catalogue['items'][$key]; ?>
                        <li>
                            <button type="button" class="order-item" data-add="<?= e($key) ?>">
                                <span class="oi-main">
                                    <span class="oi-name"><?= e($it['name']) ?>
                                        <?php if ($it['chef']): ?><span class="oi-tag" title="Chef’s recommendation"><?= icon('chef') ?></span><?php endif; ?>
                                        <?php if ($it['veg']): ?><span class="oi-tag oi-veg" title="Vegetarian">V</span><?php endif; ?>
                                    </span>
                                    <?php if ($it['cn']): ?><span class="oi-cn" lang="zh-Hans"><?= e($it['cn']) ?></span><?php endif; ?>
                                    <?php if ($it['desc']): ?><span class="oi-desc"><?= e($it['desc']) ?></span><?php endif; ?>
                                    <span class="oi-meta"><?= spice_meter($it['spice']) ?>
                                        <?php if ($it['allergens']): ?><span class="oi-allergens">Allergens <?= e(implode(', ', $it['allergens'])) ?></span><?php endif; ?>
                                    </span>
                                </span>
                                <span class="oi-side">
                                    <span class="oi-price"><?php
                                        $v = $it['variants'];
                                        echo e(($v[0]['label'] ? $v[0]['label'] . ' ' : '') . order_money($v[0]['price']));
                                        if (count($v) > 1) { echo '<small> · ' . e($v[1]['label'] . ' ' . order_money($v[1]['price'])) . '</small>'; }
                                    ?></span>
                                    <span class="oi-add" aria-hidden="true">+</span>
                                    <span class="oi-inbasket" data-count-for="<?= e($key) ?>" hidden></span>
                                </span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>

        <p class="order-small">Allergen numbers: 1 gluten (W wheat, B barley), 2 milk, 3 eggs, 4 fish, 5 crustaceans, 6 molluscs, 7 peanuts, 8 nuts, 9 sesame, 10 sulphites, 11 soya, 12 celery, 13 mustard, 14 lupin. Please tell us about any allergy in the order notes or ring us before ordering.</p>
    </div>

    <aside class="order-basket" id="basket" aria-label="Your order">
        <div class="ob-inner">
            <header class="ob-head">
                <h2>Your order</h2>
                <button type="button" class="ob-close" data-basket-close aria-label="Close basket">&times;</button>
            </header>
            <p class="ob-empty" data-basket-empty>Your basket is empty. Tap a dish to add it.</p>
            <ul class="ob-lines" data-basket-lines></ul>
            <div class="ob-total" data-basket-total hidden>
                <span>Total</span><strong data-total>€0.00</strong>
            </div>
            <p class="ob-warn" data-lunch-warn hidden></p>

            <form class="ob-checkout" method="post" action="order.php" data-checkout novalidate>
                <input type="hidden" name="token" value="<?= e($_SESSION['order_token']) ?>">
                <input type="hidden" name="basket" value="" data-basket-field>
                <div class="hp" aria-hidden="true"><label for="website">Leave this empty</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></div>

                <fieldset data-checkout-fields hidden>
                    <legend>Collection</legend>
                    <div class="field">
                        <label for="time">Collection time today</label>
                        <select id="time" name="time" data-time<?= $errAttr('time') ?>>
                            <?php if ($first): ?>
                                <option value="asap" <?= $orderOld['time'] === 'asap' ? 'selected' : '' ?>>As soon as possible (about <?= e($first) ?>)</option>
                                <?php foreach ($slots as $s): ?>
                                    <option value="<?= e($s) ?>" <?= $orderOld['time'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?= $fieldError('time') ?>
                    </div>

                    <legend class="ob-legend-2">Your details</legend>
                    <div class="field">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" autocomplete="name" maxlength="80" required value="<?= e($orderOld['name']) ?>"<?= $errAttr('name') ?>>
                        <?= $fieldError('name') ?>
                    </div>
                    <div class="field">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" autocomplete="tel" maxlength="20" required value="<?= e($orderOld['phone']) ?>"<?= $errAttr('phone') ?>>
                        <?= $fieldError('phone') ?>
                    </div>
                    <div class="field">
                        <label for="email">Email <span class="optional">(for your receipt)</span></label>
                        <input type="email" id="email" name="email" autocomplete="email" maxlength="120" required value="<?= e($orderOld['email']) ?>"<?= $errAttr('email') ?>>
                        <?= $fieldError('email') ?>
                    </div>
                    <div class="field">
                        <label for="notes">Order notes <span class="optional">(optional)</span></label>
                        <textarea id="notes" name="notes" rows="2" maxlength="300" placeholder="Allergies, no chopsticks needed…"<?= $errAttr('notes') ?>><?= e($orderOld['notes']) ?></textarea>
                        <?= $fieldError('notes') ?>
                    </div>
                    <label class="consent-label ob-consent">
                        <input type="checkbox" name="marketing_consent" value="1">
                        <span>Email me now and then about offers at October. Optional.</span>
                    </label>
                    <p class="ob-pay"><?= e($ordering['payment_note']) ?></p>
                    <p class="privacy-note">We use your details for this order only. See our <a href="privacy.php">privacy notice</a>.</p>
                </fieldset>

                <button type="button" class="btn btn-green btn-block" data-go-checkout disabled>Go to checkout</button>
                <button type="submit" class="btn btn-green btn-block" data-place hidden>Place order · <span data-total>€0.00</span></button>
                <?php if (!$slots): ?><p class="ob-closed"><?= e($closedMsg) ?></p><?php endif; ?>
            </form>
        </div>
    </aside>
</div>

<button type="button" class="order-mobile-bar" data-basket-open hidden>
    <span class="omb-count" data-count>0</span>
    <span>View order</span>
    <strong data-total>€0.00</strong>
</button>

<dialog class="order-dialog" data-item-dialog aria-labelledby="od-name">
    <form method="dialog" class="od-form" data-item-form>
        <button type="button" class="od-close" data-dialog-close aria-label="Close">&times;</button>
        <h2 id="od-name" data-od-name></h2>
        <p class="od-desc" data-od-desc></p>
        <div class="od-group" data-od-variants></div>
        <div class="od-group" data-od-choices></div>
        <div class="od-group" data-od-addons></div>
        <div class="field">
            <label for="od-note">Note for the kitchen <span class="optional">(optional)</span></label>
            <input type="text" id="od-note" maxlength="140" placeholder="e.g. extra spicy" data-od-note>
        </div>
        <p class="od-error" data-od-error hidden></p>
        <div class="od-foot">
            <div class="qty" role="group" aria-label="Quantity">
                <button type="button" data-qty="-1" aria-label="One fewer">−</button>
                <output data-od-qty>1</output>
                <button type="button" data-qty="1" aria-label="One more">+</button>
            </div>
            <button type="submit" class="btn btn-green" data-od-add>Add · <span data-od-price></span></button>
        </div>
    </form>
</dialog>

<script type="application/json" id="order-data"><?= json_encode($jsData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<?php if ($orderErrors): ?><script>window.orderHadErrors = true;</script><?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
