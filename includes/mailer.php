<?php
declare(strict_types=1);

/*
 * Email service (external actor). Every email is written to email_log so admins
 * can audit it. With MAIL_TRANSPORT=mail in .env it is also sent via PHP mail()
 * (configure sendmail/SMTP on the server). Default "log" is safe for local dev.
 */
function send_mail(string $to, string $subject, string $body, string $type): void
{
    $subject = str_replace(["\r", "\n"], ' ', $subject);   // prevent header injection
    $status = 'logged';
    if (cfg('MAIL_TRANSPORT', 'log') === 'mail' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $from = str_replace(["\r", "\n"], '', (string) cfg('MAIL_FROM', CAFE_EMAIL));
        $headers = "From: Hello Brew! <$from>\r\nContent-Type: text/plain; charset=UTF-8";
        $status = @mail($to, $subject, $body, $headers) ? 'sent' : 'failed';
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

function email_order_event(int $orderId, string $event, string $extra = ''): void
{
    $o = q_one('SELECT id, order_no, billing_name, billing_email, status FROM orders WHERE id = ?', [$orderId]);
    if (!$o) {
        return;
    }
    $hi = "Hi {$o['billing_name']},\n\n";
    $sign = "\n\nTrack your order any time from your Hello Brew! account.\n— The Hello Brew! team";
    switch ($event) {
        case 'confirmation':
            $subject = "Order {$o['order_no']} received — thank you!";
            $body = $hi . "We've received your order and the baristas are on it.\n\n" . order_summary_text($orderId);
            break;
        case 'status':
            $subject = "Order {$o['order_no']} is now " . strtolower(status_label($o['status']));
            $body = $hi . 'Your order status changed to: ' . status_label($o['status']) . '.' . ($extra ? "\n\n$extra" : '');
            break;
        case 'cancelled':
            $subject = "Order {$o['order_no']} has been cancelled";
            $body = $hi . "Your order has been cancelled.\n" . $extra;
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
