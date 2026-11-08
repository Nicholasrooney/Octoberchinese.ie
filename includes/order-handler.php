<?php
/*
 * order.php form handling: turn the posted basket and details into a saved
 * order, or a list of errors. Prices are always worked out here, never taken
 * from the browser.
 */

require_once __DIR__ . '/orders.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']), 'samesite' => 'Lax']);
    session_start();
}
if (empty($_SESSION['order_token'])) {
    $_SESSION['order_token'] = bin2hex(random_bytes(16));
}

$orderErrors = [];
$orderOld    = ['name' => '', 'phone' => '', 'email' => '', 'time' => 'asap', 'notes' => ''];
$orderBasketJson = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($orderOld as $k => $v) {
        $orderOld[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $orderBasketJson = (string) ($_POST['basket'] ?? '');
    $consent = !empty($_POST['marketing_consent']);

    if (!hash_equals($_SESSION['order_token'], (string) ($_POST['token'] ?? ''))) {
        $orderErrors['form'] = 'The page expired. Please check your order and place it again.';
    }
    $isBot = !empty($_POST['website']);

    // One order a minute from the same visitor, to stop double taps and abuse.
    if (!empty($_SESSION['order_last_at']) && time() - (int) $_SESSION['order_last_at'] < 60) {
        $orderErrors['form'] = 'You placed an order a moment ago. If you need to change it, please ring us.';
    }

    $len = fn($s) => function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
    if ($len($orderOld['name']) < 2 || $len($orderOld['name']) > 80) {
        $orderErrors['name'] = 'Enter your name.';
    }
    $digits = preg_replace('/\D/', '', $orderOld['phone']);
    if (strlen($digits) < 7 || strlen($digits) > 15) {
        $orderErrors['phone'] = 'Enter a phone number, in case we need to reach you about your order.';
    }
    if (!filter_var($orderOld['email'], FILTER_VALIDATE_EMAIL)) {
        $orderErrors['email'] = 'Enter an email address for your receipt.';
    }
    if ($len($orderOld['notes']) > 300) {
        $orderErrors['notes'] = 'Keep notes under 300 characters.';
    }

    $slots = order_slots();
    $asap  = $orderOld['time'] === 'asap';
    $time  = $asap ? ($slots[0] ?? '') : $orderOld['time'];
    if (!$slots) {
        $orderErrors['form'] = order_closed_message() ?? 'We cannot take orders right now.';
    } elseif (!in_array($time, $slots, true)) {
        $orderErrors['time'] = 'That collection time is no longer available. Please choose another.';
    }

    $basket = json_decode($orderBasketJson, true);
    $priced = null;
    if (!is_array($basket) || !$basket) {
        $orderErrors['basket'] = 'Your basket is empty.';
    } elseif (!isset($orderErrors['time']) && $time !== '') {
        try {
            $priced = order_price_basket($basket, $time);
        } catch (OrderError $e) {
            $orderErrors['basket'] = $e->getMessage();
        }
    }

    if (!$orderErrors && !$isBot) {
        try {
            $saved = order_save([
                'name' => $orderOld['name'], 'phone' => $orderOld['phone'], 'email' => $orderOld['email'],
                'date' => order_today(), 'time' => $time, 'asap' => $asap, 'notes' => $orderOld['notes'],
                'marketing_consent' => $consent,
            ], $priced);

            // Emails are a bonus: a failure must never lose a saved order.
            try { order_mail_alert($saved); } catch (Throwable $e) { error_log('Order alert failed: ' . $e->getMessage()); }
            try { order_mail_confirmation($saved); } catch (Throwable $e) { error_log('Order receipt failed: ' . $e->getMessage()); }

            $_SESSION['order_last_at'] = time();
            $_SESSION['order_token']   = bin2hex(random_bytes(16));
            header('Location: order-status.php?t=' . $saved['public_token'] . '&new=1', true, 303);
            exit;
        } catch (OrderError $e) {
            $orderErrors['time'] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Order failed: ' . $e->getMessage());
            $orderErrors['form'] = 'Sorry, your order could not be sent. Please ring us to order.';
        }
    }
}
