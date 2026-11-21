<?php
/**
 * Menu update for an existing install (keeps all orders, customers and settings):
 *   php database/update_menu.php
 *  - Splits "Pasta & Rice Meals" into "Pasta" and "Rice Meals"
 *  - Cinnamon Slice → Cinnamon Roll
 *  - Replaces Ham & Egg Sandwich, Ham Egg & Cheese and Chocolate Cake Petite with
 *    Croissant, French Fries and Tiramisu (the old items are archived so past orders stay correct)
 *  - Gives menu items their photos
 * Safe to run more than once.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('ROOT', dirname(__DIR__));
require ROOT . '/config/config.php';
require ROOT . '/includes/db.php';

$pdo = db();
$pdo->beginTransaction();
try {
    // 1. Categories: Pasta and Rice Meals
    $pasta = q_val("SELECT id FROM categories WHERE slug IN ('pasta-rice-meals','pasta') ORDER BY slug = 'pasta' DESC LIMIT 1");
    if (!$pasta) {
        q("INSERT INTO categories (name, slug, type, icon, sort_order) VALUES ('Pasta','pasta','food','fork',4)");
        $pasta = (int) db()->lastInsertId();
    } else {
        q("UPDATE categories SET name='Pasta', slug='pasta', icon='fork', sort_order=4 WHERE id=?", [$pasta]);
    }
    $rice = q_val("SELECT id FROM categories WHERE slug='rice-meals'");
    if (!$rice) {
        q("INSERT INTO categories (name, slug, type, icon, sort_order) VALUES ('Rice Meals','rice-meals','food','bowl',5)");
        $rice = (int) db()->lastInsertId();
    }
    q("UPDATE categories SET sort_order=6 WHERE slug='add-ons'");
    q("UPDATE products SET category_id=? WHERE slug IN ('carbonara','spaghetti')", [$pasta]);
    q("UPDATE products SET category_id=? WHERE slug IN ('tapa','tocino')", [$rice]);

    // 2. Cinnamon Slice becomes Cinnamon Roll
    q("UPDATE products SET name='Cinnamon Roll', slug='cinnamon-roll',
         description='A soft, swirly cinnamon roll topped with sweet cream cheese glaze.' WHERE slug='cinnamon-slice'");

    // 3. Retire the repeated sandwiches and the petite cake, add the new items
    q("UPDATE products SET is_archived=1, is_featured=0 WHERE slug IN ('ham-egg-sandwich','ham-egg-cheese-sandwich','chocolate-cake-petite')");
    $bakery = (int) q_val("SELECT id FROM categories WHERE slug='bakery-snacks'");
    $new = [
        ['Croissant', 'croissant', 'Flaky, golden butter croissant baked fresh every morning.', 95, 20, 'new'],
        ['French Fries', 'french-fries', 'Crispy oven-baked fries served with our house dipping sauce.', 90, 30, ''],
        ['Tiramisu', 'tiramisu', 'Layers of coffee-soaked sponge and mascarpone cream, dusted with cocoa.', 160, 12, 'new'],
    ];
    foreach ($new as [$name, $slug, $desc, $price, $stock, $badge]) {
        if (!q_val('SELECT id FROM products WHERE slug=?', [$slug])) {
            q('INSERT INTO products (category_id, name, slug, description, price, stock, image, badge) VALUES (?,?,?,?,?,?,?,?)',
                [$bakery, $name, $slug, $desc, $price, $stock, "assets/img/menu/$slug.jpg", $badge]);
        }
    }

    // 4. Photos
    $photos = ['brewed-coffee', 'brewed-tea', 'carbonara', 'cheese-bread', 'chocolate-cake', 'tiramisu', 'cinnamon-roll',
        'espresso-shot', 'tocino', 'ham-cheese-sandwich', 'croissant', 'french-fries', 'hot-chocolate', 'iced-tea',
        'matcha-latte', 'oat-milk', 'pan-de-coco', 'soy-milk', 'spaghetti', 'spanish-latte', 'tapa', 'white-mocha'];
    foreach ($photos as $slug) {
        q('UPDATE products SET image=? WHERE slug=?', ["assets/img/menu/$slug.jpg", $slug]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "✘ Menu update failed, nothing was changed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "✔ Menu updated:\n";
foreach (q_all("SELECT c.name cat, GROUP_CONCAT(p.name ORDER BY p.id SEPARATOR ', ') items
                FROM categories c JOIN products p ON p.category_id=c.id AND p.is_archived=0
                GROUP BY c.id ORDER BY c.sort_order") as $r) {
    echo "  {$r['cat']}: {$r['items']}\n";
}
