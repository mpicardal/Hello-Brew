<?php
declare(strict_types=1);

/*
 * Email service (external actor). Every email is saved in email_log so admins can see it.
 * MAIL_TRANSPORT in .env decides whether it is also delivered:
 *   log  – saved only (default, good for demos)
 *   smtp – delivered through an SMTP account such as Gmail (see .env.example)
 *   mail – delivered with PHP mail() (needs a mail server on the machine)
 */
require_once __DIR__ . '/vendor/PHPMailer/Exception.php';
require_once __DIR__ . '/vendor/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/vendor/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

/** Demo addresses (…@hellobrew.local, …@example.com) can't receive mail, so they are only logged. */
function is_deliverable_email(string $to): bool
{
    return (bool) filter_var($to, FILTER_VALIDATE_EMAIL)
        && !preg_match('/@([a-z0-9-]+\.)*(local|localhost|example\.(com|org|net)|test|invalid)$/i', $to);
}

/** Sends through SMTP. Returns '' on success or a short error message (never the password). */
function smtp_send(string $to, string $subject, string $body): string
{
    $user = trim((string) cfg('SMTP_USER', ''));
    $pass = str_replace(' ', '', (string) cfg('SMTP_PASS', ''));   // Gmail shows app passwords in groups of 4
    if ($user === '' || $pass === '') {
        return 'SMTP_USER or SMTP_PASS is missing in .env';
    }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = (string) cfg('SMTP_HOST', 'smtp.gmail.com');
        $mail->Port = (int) cfg('SMTP_PORT', '587');
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $pass;
        $mail->SMTPSecure = $mail->Port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout = 15;
        // Some hosts can't reach Gmail over IPv6 and the connection just times out; use IPv4 unless SMTP_IPV6=1.
        if (cfg('SMTP_IPV6', '0') !== '1') {
            $mail->SMTPOptions = ['socket' => ['bindto' => '0.0.0.0:0']];
        }
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($user, APP_NAME);                       // Gmail only sends "from" the signed-in account
        if (filter_var(CAFE_EMAIL, FILTER_VALIDATE_EMAIL) && is_deliverable_email(CAFE_EMAIL)) {
            $mail->addReplyTo(CAFE_EMAIL, APP_NAME);
        }
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;                                   // plain text, no HTML
        $mail->send();
        return '';
    } catch (Throwable $e) {
        return $mail->ErrorInfo ?: $e->getMessage();
    }
}

function send_mail(string $to, string $subject, string $body, string $type): void
{
    $subject = str_replace(["\r", "\n"], ' ', $subject);   // prevent header injection
    $status = 'logged';
    $transport = cfg('MAIL_TRANSPORT', 'log');
    if ($transport !== 'log' && is_deliverable_email($to)) {
        if ($transport === 'smtp') {
            $error = smtp_send($to, $subject, $body);
            $status = $error === '' ? 'sent' : 'failed';
            if ($error !== '') {
                error_log('[Hello Brew mail] ' . $error);
            }
        } elseif ($transport === 'mail') {
            $from = str_replace(["\r", "\n"], '', (string) cfg('MAIL_FROM', CAFE_EMAIL));
            $headers = "From: Hello Brew! <$from>\r\nContent-Type: text/plain; charset=UTF-8";
            $status = @mail($to, $subject, $body, $headers) ? 'sent' : 'failed';
        }
    }
    q('INSERT INTO email_log (to_email, subject, body, type, status) VALUES (?,?,?,?,?)',
        [$to, $subject, $body, $type, $status]);
}

function order_summary_text(int $orderId): string
{
    $o = q_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    $items = q_all('SELECT * FROM order_items WHERE order_id = ?', [$orderId]);
    $lines = [];
    foreach ($items as $i) {
        $lines[] = sprintf('  %dx %s%s  %s', $i['qty'], $i['product_name'],
            $i['variant_name'] ? ' (' . $i['variant_name'] . ')' : '', money($i['line_total']));
    }
    return implode("\n", $lines) . "\n\n"
        . 'Subtotal: ' . money($o['subtotal']) . "\n"
        . ((float) $o['discount'] > 0 ? 'Discount: -' . money($o['discount']) . "\n" : '')
        . 'Delivery: ' . money($o['delivery_fee']) . "\n"
        . 'Total:    ' . money($o['total']) . "\n";
}

/** "Shipping update" lines: where the order is now and what happens next. */
function shipping_update_text(array $o, string $riderNote): array
{
    $no = $o['order_no'];
    $address = trim($o['ship_line1'] . ($o['ship_line2'] ? ', ' . $o['ship_line2'] : '') . ', ' . $o['ship_city']);
    $cod = $o['payment_method'] === 'cod' && $o['payment_status'] === 'unpaid'
        ? 'Please prepare ' . money($o['total']) . ' in cash for the rider.' : 'Your order is already paid.';
    $rider = $o['courier_id'] ? (string) q_val('SELECT name FROM users WHERE id = ?', [$o['courier_id']]) : '';
    $riderFirst = $rider !== '' ? explode(' ', $rider)[0] : 'Our rider';
    $note = $riderNote !== '' ? "\nNote from {$riderFirst}: $riderNote" : '';
    return match ($o['status']) {
        'confirmed' => ["Order $no is confirmed",
            "Good news! We've confirmed your order and it's in the queue.\n\nWhat's next: we'll start preparing it shortly and email you at every step."],
        'preparing' => ["Order $no is being prepared",
            "Our baristas and kitchen are now preparing your order.\n\nWhat's next: once it's packed, a rider will pick it up."],
        'packed' => ["Order $no is packed and ready",
            "Your order is packed and waiting for {$riderFirst} to pick it up.\n\nWhat's next: we'll email you as soon as it's on the way."],
        'out_for_delivery' => ["Order $no is on the way!",
            "Your order has left the café and {$riderFirst} is on the way to:\n  $address\n\nPlease keep your phone nearby. $cod$note"],
        'delivered' => ["Order $no has been delivered",
            "Your order was delivered to $address. Enjoy!$note\n\nLoved it? You can rate the items from your order page."],
        'returned' => ["Order $no has been returned",
            "We've received the returned items for your order."],
        default => ["Update on order $no", 'Your order status is now: ' . status_label($o['status']) . '.'],
    };
}

function email_order_event(int $orderId, string $event, string $extra = ''): void
{
    $o = q_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$o) {
        return;
    }
    $hi = "Hi {$o['billing_name']},\n\n";
    $track = rtrim((string) cfg('APP_URL', 'http://localhost:8000'), '/') . '/account/order.php?no=' . rawurlencode($o['order_no']);
    $sign = "\n\nTrack your order: $track\n\n— The Hello Brew! team";
    switch ($event) {
        case 'confirmation':
            $subject = "Order {$o['order_no']} received — thank you!";
            $body = $hi . "We've received your order and the baristas are on it.\n\n" . order_summary_text($orderId)
                . "\nDelivering to: {$o['ship_recipient']}, {$o['ship_line1']}, {$o['ship_city']}"
                . "\nPayment: " . payment_label($o['payment_method']);
            break;
        case 'status':   // shipping updates
            [$subject, $text] = shipping_update_text($o, $extra);
            $body = $hi . $text;
            break;
        case 'cancelled':
            $paid = in_array($o['payment_status'], ['paid', 'partially_refunded', 'refunded'], true);
            $subject = "Order {$o['order_no']} has been cancelled";
            $body = $hi . "Your order {$o['order_no']} has been cancelled." . ($extra ? "\n$extra" : '') . "\n\n"
                . order_summary_text($orderId) . "\n"
                . ($paid ? 'Refund: the full amount of ' . money($o['total']) . " is being returned to your original payment method. You'll get a separate email with the refund reference."
                         : 'Payment: nothing was charged, so there is nothing to refund.')
                . "\n\nThe items have been put back in stock. We hope to serve you again soon!";
            break;
        case 'refund':
            $subject = "Refund processed for order {$o['order_no']}";
            $body = $hi . $extra;
            break;
        case 'return_update':
            $subject = "Update on your request for order {$o['order_no']}";
            $body = $hi . $extra;
            break;
        default:
            return;
    }
    send_mail($o['billing_email'], $subject, $body . $sign, $event);
}
