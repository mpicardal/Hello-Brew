-- Hello Brew! e-commerce schema (MySQL 8+ / MariaDB 10.4+)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_log, login_attempts, newsletter_subscribers, analytics_events,
  email_log, payments, return_requests, order_status_history, order_items, orders,
  reviews, coupons, product_variants, products, categories, addresses, users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL UNIQUE,
  phone         VARCHAR(30)  NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('customer','admin','staff','fulfillment','courier') NOT NULL DEFAULT 'customer',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE addresses (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  label       VARCHAR(40)  NOT NULL DEFAULT 'Home',
  recipient   VARCHAR(120) NOT NULL,
  phone       VARCHAR(30)  NOT NULL,
  line1       VARCHAR(190) NOT NULL,
  line2       VARCHAR(190) NULL,
  city        VARCHAR(100) NOT NULL,
  province    VARCHAR(100) NOT NULL,
  postal_code VARCHAR(10)  NOT NULL,
  is_default  TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80) NOT NULL,
  slug        VARCHAR(90) NOT NULL UNIQUE,
  type        ENUM('drink','food','addon') NOT NULL DEFAULT 'drink',
  icon        VARCHAR(30) NOT NULL DEFAULT 'cup',
  sort_order  INT NOT NULL DEFAULT 0,
  is_archived TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  name        VARCHAR(120) NOT NULL,
  slug        VARCHAR(140) NOT NULL UNIQUE,
  description TEXT NULL,
  price       DECIMAL(10,2) NOT NULL,
  stock       INT NOT NULL DEFAULT 0,
  image       VARCHAR(255) NULL,
  badge       ENUM('','best','new','sale') NOT NULL DEFAULT '',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  INDEX idx_products_cat (category_id, is_archived),
  FULLTEXT INDEX ft_products (name, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_variants (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED NOT NULL,
  name        VARCHAR(60) NOT NULL,
  price_delta DECIMAL(10,2) NOT NULL DEFAULT 0,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE coupons (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code         VARCHAR(30) NOT NULL UNIQUE,
  description  VARCHAR(160) NULL,
  type         ENUM('percent','fixed') NOT NULL,
  value        DECIMAL(10,2) NOT NULL,
  min_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  usage_limit  INT NULL,
  used_count   INT NOT NULL DEFAULT 0,
  expires_at   DATETIME NULL,
  is_active    TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_no       VARCHAR(20) NOT NULL UNIQUE,
  user_id        INT UNSIGNED NOT NULL,
  status         ENUM('pending','confirmed','preparing','packed','out_for_delivery','delivered','cancelled','returned') NOT NULL DEFAULT 'pending',
  subtotal       DECIMAL(10,2) NOT NULL,
  discount       DECIMAL(10,2) NOT NULL DEFAULT 0,
  delivery_fee   DECIMAL(10,2) NOT NULL DEFAULT 0,
  total          DECIMAL(10,2) NOT NULL,
  coupon_code    VARCHAR(30) NULL,
  billing_name   VARCHAR(120) NOT NULL,
  billing_email  VARCHAR(190) NOT NULL,
  billing_phone  VARCHAR(30)  NOT NULL,
  ship_recipient VARCHAR(120) NOT NULL,
  ship_phone     VARCHAR(30)  NOT NULL,
  ship_line1     VARCHAR(190) NOT NULL,
  ship_line2     VARCHAR(190) NULL,
  ship_city      VARCHAR(100) NOT NULL,
  ship_province  VARCHAR(100) NOT NULL,
  ship_postal    VARCHAR(10)  NOT NULL,
  notes          VARCHAR(255) NULL,
  payment_method ENUM('card','ewallet','cod') NOT NULL,
  payment_status ENUM('unpaid','paid','refunded','partially_refunded','failed') NOT NULL DEFAULT 'unpaid',
  card_last4     CHAR(4) NULL,
  courier_id     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (courier_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_orders_status (status),
  INDEX idx_orders_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id     INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NULL,
  variant_id   INT UNSIGNED NULL,
  product_name VARCHAR(120) NOT NULL,
  variant_name VARCHAR(60) NULL,
  unit_price   DECIMAL(10,2) NOT NULL,
  qty          INT NOT NULL,
  line_total   DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_status_history (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id   INT UNSIGNED NOT NULL,
  status     VARCHAR(30) NOT NULL,
  note       VARCHAR(255) NULL,
  changed_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE return_requests (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id      INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  type          ENUM('cancellation','return') NOT NULL,
  reason        VARCHAR(500) NOT NULL,
  status        ENUM('pending','approved','rejected','refunded') NOT NULL DEFAULT 'pending',
  admin_note    VARCHAR(500) NULL,
  refund_amount DECIMAL(10,2) NULL,
  restocked     TINYINT(1) NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id   INT UNSIGNED NOT NULL,
  kind       ENUM('charge','refund') NOT NULL,
  method     VARCHAR(20) NOT NULL,
  amount     DECIMAL(10,2) NOT NULL,
  status     ENUM('success','failed') NOT NULL,
  reference  VARCHAR(40) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reviews (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  rating      TINYINT NOT NULL,
  comment     VARCHAR(600) NOT NULL,
  is_approved TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review (user_id, product_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE email_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  to_email   VARCHAR(190) NOT NULL,
  subject    VARCHAR(190) NOT NULL,
  body       TEXT NOT NULL,
  type       VARCHAR(40) NOT NULL,
  status     ENUM('queued','sent','failed','logged') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Privacy-friendly analytics: no IPs, session ids are salted hashes.
CREATE TABLE analytics_events (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_hash CHAR(64) NOT NULL,
  user_id      INT UNSIGNED NULL,
  event        ENUM('page_view','product_view','add_to_cart','begin_checkout','purchase','search') NOT NULL,
  product_id   INT UNSIGNED NULL,
  meta         VARCHAR(120) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ae_event_date (event, created_at),
  INDEX idx_ae_session (session_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE newsletter_subscribers (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(190) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE login_attempts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  key_hash   CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_la (key_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  action     VARCHAR(60) NOT NULL,
  details    VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
