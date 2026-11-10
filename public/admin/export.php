<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_perm('analytics');

$range = in_array(get('range'), ['7', '30', '90'], true) ? (int) get('range') : 30;
$from = date('Y-m-d 00:00:00', strtotime('-' . ($range - 1) . ' days'));
$rows = q_all('SELECT order_no, created_at, status, payment_method, payment_status, subtotal, discount, delivery_fee, total, coupon_code, ship_city
               FROM orders WHERE created_at >= ? ORDER BY created_at', [$from]);
audit('export_orders', "range $range days");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="hello-brew-orders-' . date('Ymd') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Order', 'Date', 'Status', 'Payment method', 'Payment status', 'Subtotal', 'Discount', 'Delivery', 'Total', 'Coupon', 'City']);
foreach ($rows as $r) {
    // Neutralise spreadsheet formula injection.
    fputcsv($out, array_map(fn($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : $v, array_values($r)));
}
fclose($out);
