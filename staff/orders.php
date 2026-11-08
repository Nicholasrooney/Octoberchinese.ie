<?php
/*
 * Click and collect orders for today: the kitchen's ticket screen.
 *
 * New orders sit at the top in their own section until somebody accepts them.
 * The page refreshes itself every 30 seconds and plays a chime when a new
 * order arrives, so it can be left open on a tablet by the pass.
 */

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/orders.php';
staff_require_login();
staff_require_database();

$today = order_today();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!staff_check_token($_POST['token'] ?? null)) {
        staff_flash('That action expired. Try again.');
        header('Location: orders.php', true, 303);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'pause') {
        $pause = ($_POST['paused'] ?? '') === '1';
        staff_flash(order_set_paused($pause, staff_actor())
            ? ($pause ? 'Online orders paused for the rest of today.' : 'Online orders are back on.')
            : 'That did not save. Try again.');
    } elseif ($id && $action === 'status') {
        $status = (string) ($_POST['status'] ?? '');
        if (order_set_status($id, $status)) {
            if ($status === 'ready') {
                $o = order_get($id);
                if ($o) {
                    try { order_mail_ready($o); } catch (Throwable $e) { error_log('Ready email failed: ' . $e->getMessage()); }
                }
            }
            staff_flash(order_ref($id) . ' marked ' . strtolower(order_statuses()[$status]) . '.');
        }
    }

    header('Location: orders.php#order-' . $id, true, 303);
    exit;
}

$orders = orders_for_date($today);
$groups = ['new' => [], 'accepted' => [], 'ready' => [], 'done' => []];
foreach ($orders as $o) {
    $g = in_array($o['status'], ['collected', 'cancelled'], true) ? 'done' : $o['status'];
    $groups[$g][] = $o;
}
$takings = array_sum(array_map(fn($o) => $o['status'] === 'cancelled' ? 0 : (float) $o['subtotal'], $orders));
$paused  = order_paused();
$newIds  = array_map(fn($o) => (int) $o['id'], $groups['new']);
$flash   = staff_flash();

// Next step for each status, and the button wording.
$next = [
    'new'      => ['accepted', 'Accept'],
    'accepted' => ['ready', 'Mark ready'],
    'ready'    => ['collected', 'Collected'],
];

header('Refresh: 30');
staff_head('Orders', 'orders');
?>

<?php if ($flash): ?><p class="flash"><?= e($flash) ?></p><?php endif; ?>

<div class="orders-top">
    <div>
        <h1>Collection orders</h1>
        <p class="sub"><?= e((new DateTimeImmutable($today))->format('l j F')) ?> · <?= count($orders) ?> orders · <?= e(order_money($takings)) ?></p>
    </div>
    <form method="post" class="weekend-switch <?= $paused ? 'is-paused' : '' ?>">
        <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
        <input type="hidden" name="action" value="pause">
        <input type="hidden" name="paused" value="<?= $paused ? '0' : '1' ?>">
        <span class="weekend-text">
            <strong>Online orders</strong>
            <span class="weekend-state"><?= $paused ? 'Paused for today' : 'Taking orders' ?></span>
        </span>
        <button class="btn-s"><?= $paused ? 'Turn back on' : 'Pause today' ?></button>
    </form>
</div>

<?php
$titles = ['new' => 'New: needs accepting', 'accepted' => 'Preparing', 'ready' => 'Ready, waiting for collection', 'done' => 'Collected and cancelled'];
foreach ($groups as $g => $list):
    if (!$list && $g !== 'new') continue;
?>
    <h2 class="orders-h orders-h-<?= e($g) ?>"><?= e($titles[$g]) ?> <span class="count"><?= count($list) ?></span></h2>
    <?php if (!$list): ?>
        <div class="card empty"><p>No new orders.</p><p class="sub">This page checks for new orders every 30 seconds.</p></div>
    <?php endif; ?>

    <?php foreach ($list as $o): ?>
        <article class="card ticket ticket-<?= e($o['status']) ?>" id="order-<?= (int) $o['id'] ?>">
            <header class="ticket-head">
                <div class="ticket-time"><?= e($o['collect_time']) ?><?= $o['asap'] ? '<small>ASAP</small>' : '' ?></div>
                <div class="ticket-who">
                    <h3><?= e(order_ref((int) $o['id'])) ?> · <?= e($o['name']) ?></h3>
                    <p class="sub">Placed <?= e(substr($o['created_at'], 11, 5)) ?> · <?= (int) $o['item_count'] ?> items · <?= e(order_money((float) $o['subtotal'])) ?> to pay
                        · <span class="tag tag-o-<?= e($o['status']) ?>"><?= e(order_statuses()[$o['status']]) ?></span></p>
                </div>
            </header>

            <ul class="ticket-lines">
                <?php foreach ($o['items'] as $l): ?>
                    <li><strong><?= (int) $l['qty'] ?>×</strong> <?= e($l['name']) ?>
                        <?php if ($l['variant'] !== ''): ?><em><?= e($l['variant']) ?></em><?php endif; ?>
                        <?php if ($l['note'] !== ''): ?><span class="ticket-note">“<?= e($l['note']) ?>”</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!empty($o['notes'])): ?><p class="bk-note">Order note: <?= e($o['notes']) ?></p><?php endif; ?>

            <div class="ticket-do">
                <a class="btn-s btn-call" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $o['phone'])) ?>">Call <?= e($o['phone']) ?></a>
                <?php if (isset($next[$o['status']])): [$to, $label] = $next[$o['status']]; ?>
                    <form method="post">
                        <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                        <button class="btn-s btn-go" name="status" value="<?= e($to) ?>"><?= e($label) ?></button>
                    </form>
                <?php endif; ?>
                <?php if (!in_array($o['status'], ['collected', 'cancelled'], true)): ?>
                    <form method="post" data-confirm="Cancel order <?= e(order_ref((int) $o['id'])) ?>? Ring the customer to let them know.">
                        <input type="hidden" name="token" value="<?= e(staff_token()) ?>">
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                        <button class="btn-s btn-cancel" name="status" value="cancelled">Cancel</button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
<?php endforeach; ?>

<script>
// Chime when an order arrives that this screen has not seen before.
(function () {
  var ids = <?= json_encode($newIds) ?>, seen = [];
  try { seen = JSON.parse(sessionStorage.getItem('octoberSeenOrders') || '[]'); } catch (e) {}
  var fresh = ids.filter(function (id) { return seen.indexOf(id) === -1; });
  try { sessionStorage.setItem('octoberSeenOrders', JSON.stringify(seen.concat(fresh))); } catch (e) {}
  if (ids.length) document.title = '(' + ids.length + ') New orders · October staff';
  if (!fresh.length) return;
  try {
    var ctx = new (window.AudioContext || window.webkitAudioContext)();
    [880, 1175, 880].forEach(function (f, i) {
      var o = ctx.createOscillator(), g = ctx.createGain();
      o.frequency.value = f; o.connect(g); g.connect(ctx.destination);
      g.gain.setValueAtTime(0.0001, ctx.currentTime + i * 0.25);
      g.gain.exponentialRampToValueAtTime(0.3, ctx.currentTime + i * 0.25 + 0.02);
      g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + i * 0.25 + 0.22);
      o.start(ctx.currentTime + i * 0.25); o.stop(ctx.currentTime + i * 0.25 + 0.25);
    });
  } catch (e) {}
})();
</script>

<?php staff_foot(); ?>
