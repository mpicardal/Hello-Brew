<?php
/**
 * Hello Brew! one-time setup (command line only).
 *
 *   php database/setup.php [--db-host=127.0.0.1] [--db-port=3306] [--db-name=hello_brew]
 *                          [--db-user=root] [--db-pass=] [--fresh] [--demo]
 *
 *  1. Creates .env from .env.example with a random APP_SECRET (if missing).
 *  2. Creates the database, tables and the café menu.
 *  3. Creates one account per role with a random strong password (printed once).
 *  4. --demo adds 45 days of sample customers, orders, reviews and analytics.
 *  --fresh is required to wipe an existing install.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('ROOT', dirname(__DIR__));

foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'gd'] as $ext) {
    if (!extension_loaded($ext)) {
        fwrite(STDERR, "Missing PHP extension: $ext — enable it in php.ini and run again.\n");
        exit(1);
    }
}

$opts = getopt('', ['db-host:', 'db-port:', 'db-name:', 'db-user:', 'db-pass::', 'fresh', 'demo', 'help']);
if (isset($opts['help'])) {
    echo file_get_contents(__FILE__, false, null, 0, 900);
    exit;
}

/* ---------- 1. .env ---------- */
$envFile = ROOT . '/.env';
if (!is_file($envFile)) {
    $tpl = file_get_contents(ROOT . '/.env.example');
    $tpl = preg_replace('/^APP_SECRET=.*$/m', 'APP_SECRET=' . bin2hex(random_bytes(32)), $tpl);
    file_put_contents($envFile, $tpl);
    @chmod($envFile, 0600);
    echo "✔ Created .env with a random APP_SECRET\n";
}
$env = file_get_contents($envFile);
$map = ['db-host' => 'DB_HOST', 'db-port' => 'DB_PORT', 'db-name' => 'DB_NAME', 'db-user' => 'DB_USER', 'db-pass' => 'DB_PASS'];
foreach ($map as $opt => $key) {
    if (array_key_exists($opt, $opts)) {
        $val = $opts[$opt] === false ? '' : (string) $opts[$opt];
        if (preg_match('/[\r\n]/', $val)) {
            exit("Invalid value for --$opt\n");
        }
        $env = preg_match("/^$key=.*$/m", $env)
            ? preg_replace("/^$key=.*$/m", $key . '=' . addcslashes($val, '\\$'), $env)
            : $env . "\n$key=$val";
    }
}
if (!preg_match('/^APP_SECRET=[0-9a-f]{32,}$/m', $env)) {
    $env = preg_replace('/^APP_SECRET=.*$/m', 'APP_SECRET=' . bin2hex(random_bytes(32)), $env);
}
file_put_contents($envFile, $env);

require ROOT . '/config/config.php';

/* ---------- 2. Database ---------- */
$dbName = (string) cfg('DB_NAME', 'hello_brew');
if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbName)) {
    exit("DB_NAME may only contain letters, numbers and underscores.\n");
}
try {
    $pdo = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', cfg('DB_HOST', '127.0.0.1'), cfg('DB_PORT', '3306')),
        cfg('DB_USER', 'root'), cfg('DB_PASS', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (PDOException $e) {
    fwrite(STDERR, "✘ Could not connect to MySQL: " . $e->getMessage() . "\n  Is MySQL running (XAMPP → Start MySQL)? Check DB_USER / DB_PASS in .env\n");
    exit(1);
}
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$dbName`");

$exists = (bool) $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
if ($exists && !isset($opts['fresh'])) {
    fwrite(STDERR, "✘ Hello Brew! is already installed in `$dbName`. Re-run with --fresh to WIPE it and start over.\n");
    exit(1);
}

function run_sql_file(PDO $pdo, string $file): void
{
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));
    foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
        if (trim($stmt) !== '') {
            $pdo->exec($stmt);
        }
    }
}
run_sql_file($pdo, __DIR__ . '/schema.sql');
run_sql_file($pdo, __DIR__ . '/seed.sql');
echo "✔ Database `$dbName` created with " . $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() . " menu items\n";

/* ---------- 3. Accounts (random passwords, shown once) ---------- */
function strong_password(): string
{
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $pw = '';
    for ($i = 0; $i < 12; $i++) {
        $pw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $pw . random_int(10, 99);
}
$accounts = [
    ['Café Admin',        'admin@hellobrew.local',    'admin'],
    ['Sam Staff',         'staff@hellobrew.local',    'staff'],
    ['Faye Fulfillment',  'fulfill@hellobrew.local',  'fulfillment'],
    ['Rico Rider',        'courier@hellobrew.local',  'courier'],
    ['Juan Dela Cruz',    'customer@hellobrew.local', 'customer'],
];
$ins = $pdo->prepare('INSERT INTO users (name, email, phone, password_hash, role) VALUES (?,?,?,?,?)');
$creds = [];
foreach ($accounts as [$name, $email, $role]) {
    $pw = strong_password();
    $ins->execute([$name, $email, '0917' . random_int(1000000, 9999999), password_hash($pw, PASSWORD_DEFAULT), $role]);
    $creds[] = [$role, $email, $pw];
}
$custId = (int) $pdo->query("SELECT id FROM users WHERE role='customer' LIMIT 1")->fetchColumn();
$pdo->prepare('INSERT INTO addresses (user_id, label, recipient, phone, line1, line2, city, province, postal_code, is_default) VALUES (?,?,?,?,?,?,?,?,?,1)')
    ->execute([$custId, 'Home', 'Juan Dela Cruz', '09171234567', '12 Mabini Street', 'Brgy. San Antonio', 'Pasig City', 'Metro Manila', '1600']);

/* ---------- 4. Optional demo data ---------- */
if (isset($opts['demo'])) {
    $first = ['Maria', 'Jose', 'Ana', 'Mark', 'Bea', 'Paolo', 'Kim', 'Carlo', 'Mika', 'Liza', 'Rafa', 'Joy', 'Nico', 'Trish', 'Miguel'];
    $last = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Torres', 'Flores', 'Ramos', 'Aquino'];
    $cities = [['Pasig City', 'Metro Manila', '1600'], ['Quezon City', 'Metro Manila', '1100'], ['Makati City', 'Metro Manila', '1200'], ['Taguig City', 'Metro Manila', '1630'], ['Mandaluyong', 'Metro Manila', '1550']];
    $lockedHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);   // demo shoppers can't log in
    $customers = [$custId];
    for ($i = 0; $i < 24; $i++) {
        $n = $first[array_rand($first)] . ' ' . $last[array_rand($last)];
        $ins->execute([$n, 'demo' . $i . '@example.com', '0918' . random_int(1000000, 9999999), $lockedHash, 'customer']);
        $customers[] = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE users SET created_at = NOW() - INTERVAL ? DAY WHERE id = ?')->execute([random_int(0, 45), $pdo->lastInsertId()]);
    }
    $courierId = (int) $pdo->query("SELECT id FROM users WHERE role='courier' LIMIT 1")->fetchColumn();
    $products = $pdo->query("SELECT p.id, p.name, p.price, c.type FROM products p JOIN categories c ON c.id=p.category_id")->fetchAll();
    $weights = [];
    foreach ($products as $p) {   // popular drinks get ordered more often
        $w = in_array($p['name'], ['Spanish Latte', 'Caramel Macchiato', 'Americano', 'Tapa', 'Cappuccino'], true) ? 6 : ($p['type'] === 'addon' ? 1 : 3);
        for ($k = 0; $k < $w; $k++) $weights[] = $p;
    }
    $order = $pdo->prepare('INSERT INTO orders (order_no, user_id, status, subtotal, discount, delivery_fee, total, coupon_code, billing_name, billing_email, billing_phone,
        ship_recipient, ship_phone, ship_line1, ship_line2, ship_city, ship_province, ship_postal, payment_method, payment_status, card_last4, courier_id, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $item = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, qty, line_total) VALUES (?,?,?,?,?,?)');
    $hist = $pdo->prepare('INSERT INTO order_status_history (order_id, status, note, created_at) VALUES (?,?,?,?)');
    $pay = $pdo->prepare('INSERT INTO payments (order_id, kind, method, amount, status, reference, created_at) VALUES (?,?,?,?,?,?,?)');
    $ev = $pdo->prepare('INSERT INTO analytics_events (session_hash, user_id, event, product_id, meta, created_at) VALUES (?,?,?,?,?,?)');
    $flow = ['pending', 'confirmed', 'preparing', 'packed', 'out_for_delivery', 'delivered'];
    $orderCount = 0;

    for ($day = 44; $day >= 0; $day--) {
        $visits = random_int(18, 40) + (int) ((44 - $day) / 3);
        for ($v = 0; $v < $visits; $v++) {
            $at = date('Y-m-d H:i:s', strtotime("-$day days") - random_int(0, 50000));
            if ($day === 0 && strtotime($at) > time()) $at = date('Y-m-d H:i:s', time() - random_int(60, 7200));
            $sess = hash('sha256', random_bytes(16));
            $ev->execute([$sess, null, 'page_view', null, 'home', $at]);
            if (random_int(1, 100) > 70) continue;
            $p = $weights[array_rand($weights)];
            $ev->execute([$sess, null, 'product_view', $p['id'], null, $at]);
            if (random_int(1, 100) > 55) continue;
            $ev->execute([$sess, null, 'add_to_cart', $p['id'], null, $at]);
            if (random_int(1, 100) > 75) continue;
            $ev->execute([$sess, null, 'begin_checkout', null, null, $at]);
            if (random_int(1, 100) > 72) continue;

            // Purchase
            $uid = $customers[array_rand($customers)];
            $u = $pdo->query('SELECT name, email, phone FROM users WHERE id = ' . (int) $uid)->fetch();
            $lines = [];
            foreach ((array) array_rand($weights, random_int(1, 4)) as $idx) {
                $pp = $weights[$idx];
                $lines[$pp['id']] = $lines[$pp['id']] ?? ['p' => $pp, 'qty' => 0];
                $lines[$pp['id']]['qty'] += random_int(1, 2);
            }
            $sub = array_sum(array_map(fn($l) => $l['qty'] * $l['p']['price'], $lines));
            $coupon = random_int(1, 100) <= 15 ? 'HELLOBREW' : null;
            $disc = $coupon ? round($sub * 0.10, 2) : 0;
            $fee = $sub - $disc >= 800 ? 0 : 50;
            $total = $sub - $disc + $fee;
            $method = ['card', 'card', 'ewallet', 'cod'][random_int(0, 3)];
            $status = $day > 1 ? (random_int(1, 100) <= 6 ? 'cancelled' : 'delivered') : $flow[random_int(0, 5)];
            $paid = $method !== 'cod' || $status === 'delivered';
            $payStatus = $status === 'cancelled' && $paid ? 'refunded' : ($paid ? 'paid' : 'unpaid');
            $c = $cities[array_rand($cities)];
            $no = 'HB' . date('ymd', strtotime($at)) . '-' . strtoupper(bin2hex(random_bytes(3)));
            $order->execute([$no, $uid, $status, $sub, $disc, $fee, $total, $coupon, $u['name'], $u['email'], $u['phone'],
                $u['name'], $u['phone'], random_int(1, 300) . ' Rizal Avenue', null, $c[0], $c[1], $c[2], $method, $payStatus,
                $method === 'card' ? '4242' : null, in_array($status, ['out_for_delivery', 'delivered'], true) ? $courierId : null, $at]);
            $oid = (int) $pdo->lastInsertId();
            foreach ($lines as $l) {
                $item->execute([$oid, $l['p']['id'], $l['p']['name'], $l['p']['price'], $l['qty'], $l['qty'] * $l['p']['price']]);
            }
            $t = strtotime($at);
            foreach ($status === 'cancelled' ? ['pending', 'cancelled'] : array_slice($flow, 0, array_search($status, $flow, true) + 1) as $k => $s) {
                $hist->execute([$oid, $s, $k === 0 ? 'Order placed' : null, date('Y-m-d H:i:s', $t + $k * 600)]);
            }
            if ($paid) {
                $pay->execute([$oid, 'charge', $method, $total, 'success', 'MOCK-' . strtoupper(bin2hex(random_bytes(5))), $at]);
            }
            if ($payStatus === 'refunded') {
                $pay->execute([$oid, 'refund', $method, $total, 'success', 'MOCKRF-' . strtoupper(bin2hex(random_bytes(5))), $at]);
            }
            $ev->execute([$sess, $uid, 'purchase', null, $no, $at]);
            $orderCount++;
        }
    }
    // A few searches, approved reviews and one open return request
    foreach (['spanish latte', 'matcha', 'cake', 'tapa', 'iced', 'latte', 'sandwich', 'carbonara'] as $term) {
        for ($k = random_int(2, 9); $k > 0; $k--) {
            $ev->execute([hash('sha256', random_bytes(8)), null, 'search', null, 'q:' . $term, date('Y-m-d H:i:s', strtotime('-' . random_int(0, 29) . ' days'))]);
        }
    }
    $quotes = [
        ['Spanish Latte', 5, 'The Spanish latte is perfectly sweet and creamy. It arrived still cold, ice and all!'],
        ['Tapa', 5, 'Tapa tastes homemade and the garlic rice is so good. My go-to weekday breakfast.'],
        ['Chocolate Cake', 4, 'Rich, moist and not too sweet. The slice is huge — great with a brewed coffee.'],
    ];
    $rev = $pdo->prepare('INSERT IGNORE INTO reviews (user_id, product_id, rating, comment, is_approved) VALUES (?,?,?,?,1)');
    foreach ($quotes as $k => [$pname, $rating, $text]) {
        $pid = $pdo->query('SELECT id FROM products WHERE name = ' . $pdo->quote($pname))->fetchColumn();
        $rev->execute([$customers[$k + 1], $pid, $rating, $text]);
    }
    $recent = $pdo->query("SELECT id, user_id FROM orders WHERE status='delivered' ORDER BY created_at DESC LIMIT 1")->fetch();
    if ($recent) {
        $pdo->prepare("INSERT INTO return_requests (order_id, user_id, type, reason) VALUES (?,?,'return','The iced latte was spilled when it arrived.')")
            ->execute([$recent['id'], $recent['user_id']]);
    }
    echo "✔ Demo data: 24 shoppers, $orderCount orders over 45 days, analytics, reviews and a return request\n";
}

/* ---------- Done ---------- */
@mkdir(ROOT . '/public/uploads/products', 0755, true);
echo "\nAccounts (passwords are shown ONCE — save them now):\n";
printf("  %-12s %-28s %s\n", 'ROLE', 'EMAIL', 'PASSWORD');
foreach ($creds as [$role, $email, $pw]) {
    printf("  %-12s %-28s %s\n", $role, $email, $pw);
}
echo "\nForgot one later? Run: php database/reset_password.php admin@hellobrew.local\n";
echo "Start the site:      php -S localhost:8000 -t public public/router.php\n";
echo "Then open:           http://localhost:8000\n";
