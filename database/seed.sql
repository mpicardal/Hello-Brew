-- Hello Brew! menu (prices in PHP, matching the café menu board)
INSERT INTO categories (id, name, slug, type, icon, sort_order) VALUES
 (1, 'Hot & Iced Coffee',  'hot-iced-coffee',  'drink', 'cup',   1),
 (2, 'Tea & Non-Coffee',   'tea-non-coffee',   'drink', 'leaf',  2),
 (3, 'Bakery & Snacks',    'bakery-snacks',    'food',  'bread', 3),
 (4, 'Pasta & Rice Meals', 'pasta-rice-meals', 'food',  'bowl',  4),
 (5, 'Add-ons',            'add-ons',          'addon', 'plus',  5);

INSERT INTO products (id, category_id, name, slug, description, price, stock, image, badge, is_featured) VALUES
 -- Coffee (hot / iced)
 (1,  1, 'Brewed Coffee',      'brewed-coffee',      'Our house blend, slow-brewed every hour for a clean, chocolatey cup.', 50.00, 80, 'assets/img/products/brewed-coffee.svg', '', 0),
 (2,  1, 'Americano',          'americano',          'Two shots of espresso topped with hot water. Bold, smooth and simple.', 100.00, 60, 'assets/img/americano.jpg', 'best', 0),
 (3,  1, 'Cappuccino',         'cappuccino',         'Espresso with equal parts steamed milk and thick, velvety foam.', 130.00, 55, 'assets/img/cappuccino.jpg', 'best', 0),
 (4,  1, 'Latte',              'latte',              'Espresso and silky steamed milk finished with latte art by our baristas.', 130.00, 55, 'assets/img/latte-art.jpg', '', 0),
 (5,  1, 'Dark Mocha',         'dark-mocha',         'Espresso, dark chocolate and steamed milk dusted with cocoa.', 150.00, 40, 'assets/img/espresso.jpg', '', 0),
 (6,  1, 'White Mocha',        'white-mocha',        'Espresso with white chocolate sauce, steamed milk and a sweet drizzle.', 150.00, 40, 'assets/img/products/white-mocha.svg', '', 0),
 (7,  1, 'Caramel Macchiato',  'caramel-macchiato',  'Vanilla milk marked with espresso and finished with buttery caramel.', 150.00, 45, 'assets/img/iced-coffee.jpg', 'best', 1),
 (8,  1, 'Spanish Latte',      'spanish-latte',      'Our bestseller: espresso, condensed milk and fresh milk, sweet and creamy.', 160.00, 50, 'assets/img/products/spanish-latte.svg', 'best', 1),
 -- Tea & non-coffee
 (9,  2, 'Brewed Tea',         'brewed-tea',         'A pot-brewed black tea, served hot with a slice of lemon on the side.', 60.00, 50, 'assets/img/products/brewed-tea.svg', '', 0),
 (10, 2, 'Iced Tea',           'iced-tea',           'House-brewed black tea, lightly sweetened and poured over ice.', 80.00, 50, 'assets/img/products/iced-tea.svg', '', 0),
 (11, 2, 'Hot Chocolate',      'hot-chocolate',      'Rich Batangas tablea chocolate whisked with steamed milk and marshmallows.', 120.00, 35, 'assets/img/products/hot-chocolate.svg', '', 0),
 (12, 2, 'Matcha Latte',       'matcha-latte',       'Ceremonial-grade matcha whisked with milk. Earthy, creamy and caffeinated.', 150.00, 30, 'assets/img/products/matcha-latte.svg', 'new', 0),
 -- Bakery & snacks
 (13, 3, 'Pan de Coco',        'pan-de-coco',        'Soft milk bread filled with sweet coconut, baked fresh every morning.', 50.00, 30, 'assets/img/products/pan-de-coco.svg', '', 0),
 (14, 3, 'Cheese Bread',       'cheese-bread',       'Fluffy, buttery bread rolls topped with grated cheese.', 45.00, 30, 'assets/img/products/cheese-bread.svg', '', 0),
 (15, 3, 'Cinnamon Slice',     'cinnamon-slice',     'A thick slice of cinnamon swirl loaf with a light sugar glaze.', 50.00, 8, 'assets/img/products/cinnamon-slice.svg', '', 0),
 (16, 3, 'Chocolate Cake',     'chocolate-cake',     'A generous slice of moist, three-layer chocolate cake with fudge frosting.', 175.00, 6, 'assets/img/products/chocolate-cake.svg', 'best', 0),
 (17, 3, 'Chocolate Cake Petite', 'chocolate-cake-petite', 'A single-serve chocolate cake, perfect with a cup of brewed coffee.', 120.00, 15, 'assets/img/products/chocolate-cake-petite.svg', 'new', 0),
 (18, 3, 'Ham & Cheese Sandwich', 'ham-cheese-sandwich', 'Toasted white bread, sweet ham and melted cheese.', 90.00, 25, 'assets/img/products/ham-cheese-sandwich.svg', '', 0),
 (19, 3, 'Ham & Egg Sandwich',    'ham-egg-sandwich',    'Toasted white bread with ham and a soft fried egg.', 90.00, 25, 'assets/img/products/ham-egg-sandwich.svg', '', 0),
 (20, 3, 'Ham, Egg & Cheese',     'ham-egg-cheese-sandwich', 'Our loaded breakfast sandwich: ham, egg, cheese and lettuce.', 120.00, 20, 'assets/img/products/ham-egg-cheese-sandwich.svg', '', 0),
 -- Pasta & rice meals
 (21, 4, 'Carbonara',          'carbonara',          'Creamy white-sauce pasta with bacon bits, parmesan and an egg yolk.', 170.00, 20, 'assets/img/products/carbonara.svg', '', 0),
 (22, 4, 'Spaghetti',          'spaghetti',          'Filipino-style sweet spaghetti with meat sauce and hotdog slices.', 150.00, 20, 'assets/img/products/spaghetti.svg', '', 0),
 (23, 4, 'Tapa',               'tapa',               'Garlic-cured beef tapa with garlic rice and a sunny-side-up egg.', 160.00, 25, 'assets/img/products/tapa.svg', 'best', 1),
 (24, 4, 'Tocino',             'tocino',             'Sweet cured pork tocino with garlic rice and a sunny-side-up egg.', 140.00, 25, 'assets/img/products/tocino.svg', '', 0),
 -- Add-ons
 (25, 5, 'Oat Milk',           'oat-milk',           'Swap to oat milk in any drink.', 30.00, 100, 'assets/img/products/oat-milk.svg', '', 0),
 (26, 5, 'Soy Milk',           'soy-milk',           'Swap to soy milk in any drink.', 30.00, 100, 'assets/img/products/soy-milk.svg', '', 0),
 (27, 5, 'Espresso Shot',      'espresso-shot',      'An extra shot of espresso for any drink.', 45.00, 100, 'assets/img/products/espresso-shot.svg', '', 0);

-- Hot / iced options for the coffee menu (same price, large iced costs more)
INSERT INTO product_variants (product_id, name, price_delta)
SELECT id, 'Hot · 12 oz', 0 FROM products WHERE category_id = 1;
INSERT INTO product_variants (product_id, name, price_delta)
SELECT id, 'Iced · 16 oz', 0 FROM products WHERE category_id = 1;
INSERT INTO product_variants (product_id, name, price_delta)
SELECT id, 'Iced · 22 oz', 20 FROM products WHERE category_id = 1;
INSERT INTO product_variants (product_id, name, price_delta) VALUES
 (11, 'Hot', 0), (11, 'Iced', 10), (12, 'Hot', 0), (12, 'Iced', 10);

INSERT INTO coupons (code, description, type, value, min_subtotal, usage_limit, expires_at) VALUES
 ('HELLOBREW', '10% off your order', 'percent', 10, 0, NULL, NULL),
 ('BREWBREAK', 'Get ₱50 off orders of ₱400 or more', 'fixed', 50, 400, 500, DATE_ADD(NOW(), INTERVAL 14 DAY));
