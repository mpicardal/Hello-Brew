<?php
declare(strict_types=1);

/*
 * Mock payment gateway (external system actor). It never stores card numbers
 * or CVV — only the last 4 digits are kept on the order.
 *   Test card that succeeds: 4242 4242 4242 4242
 *   Test card that is declined: 4000 0000 0000 0002
 * Replace MockGateway with a real provider SDK (PayMongo, Stripe, etc.) in production.
 */
final class MockGateway
{
    public static function luhn(string $digits): bool
    {
        $sum = 0; $alt = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $n = (int) $digits[$i];
            if ($alt) { $n *= 2; if ($n > 9) { $n -= 9; } }
            $sum += $n; $alt = !$alt;
        }
        return $sum % 10 === 0;
    }

    /** @return array{ok:bool, ref?:string, last4?:string, error?:string, status:string} */
    public static function charge(string $method, float $amount, array $input): array
    {
        $ref = 'MOCK-' . strtoupper(bin2hex(random_bytes(5)));
        if ($method === 'cod') {
            return ['ok' => true, 'ref' => $ref, 'status' => 'unpaid'];
        }
        if ($method === 'ewallet') {
            if (!preg_match('/^09\d{9}$/', preg_replace('/\D/', '', $input['wallet_number'] ?? ''))) {
                return ['ok' => false, 'error' => 'Enter an 11-digit mobile number starting with 09.', 'status' => 'failed'];
            }
            return ['ok' => true, 'ref' => $ref, 'status' => 'paid'];
        }
        if ($method === 'card') {
            $num = preg_replace('/\D/', '', $input['card_number'] ?? '');
            $exp = $input['card_exp'] ?? '';
            $cvc = $input['card_cvc'] ?? '';
            if (strlen($num) < 13 || strlen($num) > 19 || !self::luhn($num)) {
                return ['ok' => false, 'error' => 'The card number is not valid.', 'status' => 'failed'];
            }
            if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $exp, $m)
                || mktime(0, 0, 0, (int) $m[1] + 1, 1, 2000 + (int) $m[2]) <= time()) {
                return ['ok' => false, 'error' => 'The expiry date is invalid or in the past (use MM/YY).', 'status' => 'failed'];
            }
            if (!preg_match('/^\d{3,4}$/', $cvc)) {
                return ['ok' => false, 'error' => 'The security code must be 3 or 4 digits.', 'status' => 'failed'];
            }
            if ($num === '4000000000000002') {
                return ['ok' => false, 'error' => 'Your card was declined by the bank (test decline card).', 'status' => 'failed'];
            }
            return ['ok' => true, 'ref' => $ref, 'last4' => substr($num, -4), 'status' => 'paid'];
        }
        return ['ok' => false, 'error' => 'Choose a payment method.', 'status' => 'failed'];
    }

    public static function refund(int $orderId, string $method, float $amount): string
    {
        $ref = 'MOCKRF-' . strtoupper(bin2hex(random_bytes(5)));
        q('INSERT INTO payments (order_id, kind, method, amount, status, reference) VALUES (?,?,?,?,?,?)',
            [$orderId, 'refund', $method, $amount, 'success', $ref]);
        return $ref;
    }
}
