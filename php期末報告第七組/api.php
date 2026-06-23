<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db.php';
require __DIR__ . '/mailer.php';

function safe_exec_sql(string $sql, array $params = []): void
{
    try {
        exec_sql($sql, $params);
    } catch (Throwable $e) {
        // Keep the site usable on hosts that block optional charset/table changes.
    }
}

safe_exec_sql('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

function json_ok(array $data = []): void
{
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_fail(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function input(): array
{
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

function column_exists(string $table, string $column): bool
{
    return (bool) one(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    );
}

function ensure_moderation_columns(): void
{
    $columns = [
        ['products', 'removed_reason', 'ALTER TABLE products ADD COLUMN removed_reason TEXT NULL'],
        ['products', 'removed_at', 'ALTER TABLE products ADD COLUMN removed_at DATETIME NULL'],
        ['products', 'removed_by_report_id', 'ALTER TABLE products ADD COLUMN removed_by_report_id INT NULL'],
        ['products', 'locked_by_report', 'ALTER TABLE products ADD COLUMN locked_by_report TINYINT(1) NOT NULL DEFAULT 0'],
        ['products', 'report_cleared', 'ALTER TABLE products ADD COLUMN report_cleared TINYINT(1) NOT NULL DEFAULT 0'],
        ['products', 'report_cleared_comment', 'ALTER TABLE products ADD COLUMN report_cleared_comment TEXT NULL'],
        ['product_reports', 'admin_comment', 'ALTER TABLE product_reports ADD COLUMN admin_comment TEXT NULL'],
        ['users', 'suspended_until', 'ALTER TABLE users ADD COLUMN suspended_until DATETIME NULL'],
        ['users', 'suspension_reason', 'ALTER TABLE users ADD COLUMN suspension_reason TEXT NULL'],
        ['users', 'moderation_warning_count', 'ALTER TABLE users ADD COLUMN moderation_warning_count INT NOT NULL DEFAULT 0'],
        ['users', 'moderation_warning_month', 'ALTER TABLE users ADD COLUMN moderation_warning_month VARCHAR(7) NULL'],
        ['orders', 'buyer_completed_at', 'ALTER TABLE orders ADD COLUMN buyer_completed_at DATETIME NULL'],
        ['orders', 'seller_completed_at', 'ALTER TABLE orders ADD COLUMN seller_completed_at DATETIME NULL'],
        ['orders', 'accepted_at', 'ALTER TABLE orders ADD COLUMN accepted_at DATETIME NULL'],
        ['orders', 'updated_at', 'ALTER TABLE orders ADD COLUMN updated_at DATETIME NULL'],
    ];
    foreach ($columns as [$table, $column, $sql]) {
        if (!column_exists($table, $column)) {
            exec_sql($sql);
        }
    }
    exec_sql(
        'CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(64) PRIMARY KEY,
            setting_value TEXT NOT NULL,
            updated_by INT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    if (!one('SELECT setting_key FROM site_settings WHERE setting_key = "site_rules"')) {
        exec_sql('INSERT INTO site_settings (setting_key, setting_value) VALUES ("site_rules", ?)', [default_site_rules()]);
    }
}

function default_site_rules(): string
{
    return json_decode('"1. \u5546\u54c1\u8cc7\u8a0a\u3001\u7167\u7247\u3001\u50f9\u683c\u8207\u65b0\u820a\u7a0b\u5ea6\u9700\u5982\u5be6\u63cf\u8ff0\uff0c\u4e0d\u5f97\u523b\u610f\u96b1\u779e\u7455\u75b5\u3002\n2. \u7981\u6b62\u520a\u767b\u9055\u6cd5\u3001\u5371\u96aa\u3001\u4fb5\u6b0a\u3001\u83f8\u9152\u3001\u85e5\u54c1\u3001\u4eff\u5192\u54c1\u6216\u4e0d\u9069\u5408\u6821\u5712\u4ea4\u6613\u7684\u5546\u54c1\u3002\n3. \u4ea4\u6613\u96d9\u65b9\u61c9\u4ee5\u79ae\u8c8c\u3001\u8aa0\u4fe1\u65b9\u5f0f\u6e9d\u901a\uff0c\u7d04\u5b9a\u9762\u4ea4\u6642\u9593\u5730\u9ede\u5f8c\u8acb\u6e96\u6642\u8d74\u7d04\u3002\n4. \u5efa\u8b70\u9078\u64c7\u6821\u5712\u5167\u516c\u958b\u5834\u6240\u9762\u4ea4\uff0c\u4ea4\u4ed8\u524d\u8acb\u96d9\u65b9\u78ba\u8a8d\u5546\u54c1\u72c0\u614b\u8207\u91d1\u984d\u3002\n5. \u4e0d\u5f97\u60e1\u610f\u68c4\u55ae\u3001\u9a37\u64fe\u3001\u8a50\u9a19\u3001\u5192\u7528\u4ed6\u4eba\u8cc7\u6599\u6216\u5229\u7528\u5e73\u53f0\u5f9e\u4e8b\u975e\u4ea4\u6613\u7528\u9014\u3002\n6. \u5546\u54c1\u906d\u6aa2\u8209\u4e14\u5e73\u53f0\u5be9\u6838\u6210\u7acb\u6642\uff0c\u5546\u54c1\u6703\u88ab\u5f37\u5236\u4e0b\u67b6\u4e26\u8a18\u9304\u4e0b\u67b6\u539f\u56e0\uff0c\u8a72\u5546\u54c1\u4e0d\u5f97\u518d\u6b21\u4e0a\u67b6\u3002\n7. \u540c\u4e00\u5e33\u865f\u5728\u540c\u4e00\u500b\u6708\u5167\u88ab\u6aa2\u8209\u6210\u7acb\u4e26\u4e0b\u67b6\u5546\u54c1\u9054 3 \u4ef6\u4ee5\u4e0a\uff0c\u5e33\u865f\u5c07\u505c\u6b0a 1 \u500b\u6708\u3002\n8. \u5e73\u53f0\u65b9\u5be9\u6838\u6aa2\u8209\u6642\u5fc5\u9808\u586b\u5beb\u5be9\u6838\u8a55\u8a9e\uff0c\u4e26\u63d0\u4f9b\u7d66\u8ce3\u65b9\u8207\u6aa2\u8209\u7533\u8acb\u4eba\u67e5\u770b\u3002"', true);
}

function default_product_image(): string
{
    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="900" height="675" viewBox="0 0 900 675"><rect width="900" height="675" fill="#eef5f2"/><rect x="190" y="150" width="520" height="330" rx="28" fill="#fff" stroke="#c8d8d3" stroke-width="8"/><path d="M260 420h380l-110-135-80 92-62-70z" fill="#9fcfbf"/><circle cx="610" cy="235" r="38" fill="#f4ca64"/><text x="450" y="555" text-anchor="middle" font-family="Arial,sans-serif" font-size="44" font-weight="700" fill="#24745f">校園二手交易平台</text></svg>');
}

function safe_image_url(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '' || $url === 'data:,' || strlen($url) < 40) {
        return default_product_image();
    }
    if (str_starts_with($url, 'data:image/') || preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }
    return default_product_image();
}

function send_platform_notice(int $productId, int $recipientId, string $message): void
{
    $product = one('SELECT seller_id FROM products WHERE id = ?', [$productId]);
    if (!$product) {
        return;
    }
    $admin = one('SELECT id FROM users WHERE role = "admin" ORDER BY id LIMIT 1');
    $senderId = (int)($admin['id'] ?? $product['seller_id']);
    $sellerId = (int)$product['seller_id'];
    $buyerId = $recipientId === $sellerId ? $senderId : $recipientId;
    if ($buyerId === $sellerId) {
        $buyerId = $senderId;
    }
    $thread = one(
        'SELECT * FROM private_message_threads WHERE product_id = ? AND buyer_id = ? AND seller_id = ?',
        [$productId, $buyerId, $sellerId]
    );
    if (!$thread) {
        exec_sql('INSERT INTO private_message_threads (product_id, buyer_id, seller_id) VALUES (?, ?, ?)', [$productId, $buyerId, $sellerId]);
        $thread = one('SELECT * FROM private_message_threads WHERE id = ?', [last_id()]);
    }
    exec_sql(
        'INSERT INTO private_messages (thread_id, sender_id, receiver_id, message_text) VALUES (?, ?, ?, ?)',
        [$thread['id'], $senderId, $recipientId, $message]
    );
    exec_sql('UPDATE private_message_threads SET updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$thread['id']]);
}

function notify_product_users(int $productId, int $recipientId, string $message): void
{
    send_platform_notice($productId, $recipientId, $message);
}

function current_user(): array
{
    if (empty($_SESSION['user_id'])) {
        json_fail('請先登入', 401);
    }
    $user = one(
        'SELECT u.id, u.username, u.role, u.email, u.display_name, u.school_id, u.avatar_url, u.bio, u.suspended_until, u.suspension_reason, s.name school_name
         FROM users u
         LEFT JOIN schools s ON s.id = u.school_id
         WHERE u.id = ?',
        [$_SESSION['user_id']]
    );
    if (!$user) {
        json_fail('登入狀態已失效', 401);
    }
    if ($user['role'] !== 'admin' && !empty($user['suspended_until']) && strtotime($user['suspended_until']) > time()) {
        session_destroy();
        json_fail('帳號已停權至 ' . $user['suspended_until'] . '。原因：' . ($user['suspension_reason'] ?: '違反網站規範'), 403);
    }
    return $user;
}

function require_admin(): array
{
    $user = current_user();
    if ($user['role'] !== 'admin') {
        json_fail('需要平台方權限', 403);
    }
    return $user;
}

function find_id(string $table, string $name): int
{
    $row = one("SELECT id FROM $table WHERE name = ?", [$name]);
    if (!$row) {
        json_fail('找不到資料：' . $name);
    }
    return (int) $row['id'];
}

try {
    $action = $_GET['action'] ?? '';
    ensure_moderation_columns();

    if ($action === 'me') {
        json_ok(['user' => !empty($_SESSION['user_id']) ? current_user() : null]);
    }

    if ($action === 'login') {
        $data = input();
        $user = one('SELECT * FROM users WHERE username = ? AND is_active = 1', [$data['username'] ?? '']);
        if (!$user) {
            json_fail('帳號或密碼錯誤', 401);
        }
        $password = $data['password'] ?? '';
        $stored = $user['password_hash'] ?? '';
        $valid = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2b$')
            ? password_verify($password, $stored)
            : hash_equals($stored, $password);
        if (!$valid) {
            json_fail('帳號或密碼錯誤', 401);
        }
        if (($user['role'] ?? '') !== 'admin' && !empty($user['suspended_until']) && strtotime($user['suspended_until']) > time()) {
            json_fail('帳號已停權至 ' . $user['suspended_until'] . '。原因：' . ($user['suspension_reason'] ?: '違反網站規範'), 403);
        }
        $warning = null;
        if (($user['role'] ?? '') !== 'admin') {
            $month = date('Y-m');
            $removedCount = one(
                'SELECT COUNT(*) count_value FROM products WHERE seller_id = ? AND locked_by_report = 1 AND removed_at >= DATE_FORMAT(CURRENT_DATE(), "%Y-%m-01")',
                [$user['id']]
            );
            $count = (int)($removedCount['count_value'] ?? 0);
            if ($count >= 1 && $count <= 2 && (($user['moderation_warning_month'] ?? '') !== $month || (int)($user['moderation_warning_count'] ?? 0) < $count)) {
                $warning = '提醒：你本月已有 ' . $count . ' 件商品因檢舉成立被下架。達 3 件將停權 1 個月，請確認商品資訊符合網站規範。';
                exec_sql('UPDATE users SET moderation_warning_count = ?, moderation_warning_month = ? WHERE id = ?', [$count, $month, $user['id']]);
            }
        }
        $_SESSION['user_id'] = (int) $user['id'];
        unset($user['password_hash']);
        json_ok(['user' => $user, 'warning' => $warning]);
    }

    if ($action === 'logout') {
        session_destroy();
        json_ok();
    }

    if ($action === 'register') {
        $data = input();
        if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            json_fail('請輸入有效的 Email');
        }
        $schoolId = find_id('schools', $data['school'] ?? '');
        exec_sql(
            'INSERT INTO users (username, password_hash, role, email, display_name, school_id, avatar_url, bio) VALUES (?, ?, "student", ?, ?, ?, ?, ?)',
            [
                $data['username'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['email'],
                $data['name'],
                $schoolId,
                $data['avatar_url'] ?? '',
                $data['bio'] ?? '',
            ]
        );
        $_SESSION['user_id'] = last_id();
        json_ok(['user' => current_user()]);
    }

    if ($action === 'options') {
        json_ok([
            'schools' => all_rows('SELECT id, name FROM schools WHERE is_active = 1 ORDER BY name'),
            'categories' => all_rows('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    if ($action === 'site_rules') {
        current_user();
        $row = one('SELECT setting_value, updated_at FROM site_settings WHERE setting_key = "site_rules" ORDER BY updated_at DESC LIMIT 1');
        $rules = $row['setting_value'] ?? default_site_rules();
        if (preg_match('/\?{5,}/', $rules)) {
            $rules = default_site_rules();
            safe_exec_sql('ALTER TABLE site_settings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            exec_sql('DELETE FROM site_settings WHERE setting_key = "site_rules"');
            exec_sql(
                'INSERT INTO site_settings (setting_key, setting_value) VALUES ("site_rules", ?)',
                [$rules]
            );
        }
        json_ok(['rules' => $rules, 'updated_at' => $row['updated_at'] ?? null]);
    }

    if ($action === 'update_site_rules') {
        $admin = require_admin();
        $data = input();
        $rules = trim($data['rules'] ?? '');
        if ($rules === '') {
            json_fail('網站規範不能空白');
        }
        safe_exec_sql('ALTER TABLE site_settings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        exec_sql('DELETE FROM site_settings WHERE setting_key = "site_rules"');
        exec_sql(
            'INSERT INTO site_settings (setting_key, setting_value, updated_by) VALUES ("site_rules", ?, ?)',
            [$rules, $admin['id']]
        );
        $saved = one('SELECT setting_value FROM site_settings WHERE setting_key = "site_rules" ORDER BY updated_at DESC LIMIT 1');
        if (($saved['setting_value'] ?? '') !== $rules) {
            json_fail('網站規範沒有成功寫入資料庫，請確認資料庫編碼是否為 utf8mb4');
        }
        json_ok(['rules' => $rules]);
    }

    if ($action === 'update_site_rules_legacy') {
        $admin = require_admin();
        $data = input();
        $rules = trim($data['rules'] ?? '');
        if ($rules === '') {
            json_fail('網站規範不能空白');
        }
        exec_sql('DELETE FROM site_settings WHERE setting_key = "site_rules"');
        exec_sql(
            'INSERT INTO site_settings (setting_key, setting_value, updated_by) VALUES ("site_rules", ?, ?)',
            [$rules, $admin['id']]
        );
        json_ok();
    }

    if ($action === 'profile') {
        $user = current_user();
        $buyerOrders = all_rows(
            'SELECT o.*, p.title product_title, p.price, p.status product_status, s.display_name seller_name, sc.name school_name, c.name category_name
             FROM orders o
             JOIN products p ON p.id = o.product_id
             JOIN users s ON s.id = o.seller_id
             JOIN schools sc ON sc.id = p.school_id
             JOIN categories c ON c.id = p.category_id
             WHERE o.buyer_id = ?
             ORDER BY o.created_at DESC',
            [$user['id']]
        );
        $reports = all_rows(
            'SELECT r.*, p.title product_title, p.status product_status, p.removed_reason, p.locked_by_report
             FROM product_reports r
             JOIN products p ON p.id = r.product_id
             WHERE r.reporter_id = ?
             ORDER BY r.created_at DESC',
            [$user['id']]
        );
        json_ok(['profile' => $user, 'buyer_orders' => $buyerOrders, 'reports' => $reports]);
    }

    if ($action === 'update_profile') {
        $user = current_user();
        $data = input();
        if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            json_fail('請輸入有效的 Email');
        }
        exec_sql(
            'UPDATE users SET email = ?, display_name = ?, school_id = ?, avatar_url = ?, bio = ? WHERE id = ?',
            [$data['email'], $data['display_name'], $data['school_id'], $data['avatar_url'] ?? '', $data['bio'] ?? '', $user['id']]
        );
        json_ok(['user' => current_user()]);
    }

    if ($action === 'public_profile') {
        $id = (int) ($_GET['id'] ?? 0);
        $role = ($_GET['role'] ?? 'seller') === 'buyer' ? 'buyer' : 'seller';
        $profile = one(
            'SELECT u.id, u.display_name, u.avatar_url, u.bio, s.name school_name,
             ROUND(AVG(r.score), 1) rating_avg, COUNT(r.id) rating_count
             FROM users u
             LEFT JOIN schools s ON s.id = u.school_id
             LEFT JOIN ratings r ON r.reviewee_id = u.id AND r.reviewee_role = ?
             WHERE u.id = ?
             GROUP BY u.id, u.display_name, u.avatar_url, u.bio, s.name',
            [$role, $id]
        );
        if (!$profile) {
            json_fail('找不到使用者', 404);
        }
        $reviews = all_rows(
            'SELECT r.score, r.review_text, r.created_at, u.display_name reviewer_name
             FROM ratings r
             JOIN users u ON u.id = r.reviewer_id
             WHERE r.reviewee_id = ? AND r.reviewee_role = ?
             ORDER BY r.created_at DESC',
            [$id, $role]
        );
        json_ok(['profile' => $profile, 'role' => $role, 'reviews' => $reviews]);
    }

    if ($action === 'products') {
        $where = ['p.status = "listed"'];
        $params = [];
        if (!empty($_GET['q'])) {
            $where[] = '(p.title LIKE ? OR p.description LIKE ? OR c.name LIKE ? OR s.name LIKE ?)';
            $q = '%' . $_GET['q'] . '%';
            array_push($params, $q, $q, $q, $q);
        }
        if (!empty($_GET['school_id'])) {
            $where[] = 'p.school_id = ?';
            $params[] = $_GET['school_id'];
        }
        if (!empty($_GET['category_id'])) {
            $where[] = 'p.category_id = ?';
            $params[] = $_GET['category_id'];
        }
        $sort = $_GET['sort'] ?? '';
        if ($sort === 'cheap') {
            $order = 'p.price ASC';
        } elseif ($sort === 'popular') {
            $order = 'popularity_score DESC, p.created_at DESC';
        } else {
            $order = 'p.created_at DESC';
        }
        $rows = all_rows(
            'SELECT p.*, u.display_name seller_name, u.email seller_email, u.avatar_url seller_avatar_url, us.name seller_school_name,
             (SELECT ROUND(AVG(score), 1) FROM ratings WHERE reviewee_id = u.id AND reviewee_role = "seller") seller_rating,
             (SELECT COUNT(*) FROM ratings WHERE reviewee_id = u.id AND reviewee_role = "seller") seller_rating_count,
             s.name school_name, c.name category_name,
             (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order, id LIMIT 1) image_url,
             ((SELECT COUNT(*) FROM orders WHERE product_id = p.id) * 3
              + (SELECT COUNT(*) FROM product_comments WHERE product_id = p.id)
              + (SELECT COUNT(*) FROM product_reports WHERE product_id = p.id)) popularity_score
             FROM products p
             JOIN users u ON u.id = p.seller_id
             LEFT JOIN schools us ON us.id = u.school_id
             JOIN schools s ON s.id = p.school_id
             JOIN categories c ON c.id = p.category_id
             WHERE ' . implode(' AND ', $where) . " ORDER BY $order",
            $params
        );
        foreach ($rows as &$row) {
            $row['image_url'] = safe_image_url($row['image_url'] ?? '');
        }
        unset($row);
        json_ok(['products' => $rows]);
    }

    if ($action === 'seller_products') {
        $user = current_user();
        $rows = all_rows(
            'SELECT p.*, s.name school_name, c.name category_name,
             (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order, id LIMIT 1) image_url,
             (SELECT COUNT(*) FROM orders WHERE product_id = p.id) order_count
             FROM products p
             JOIN schools s ON s.id = p.school_id
             JOIN categories c ON c.id = p.category_id
             WHERE p.seller_id = ?
             ORDER BY p.updated_at DESC, p.created_at DESC',
            [$user['id']]
        );
        foreach ($rows as &$row) {
            $row['image_url'] = safe_image_url($row['image_url'] ?? '');
        }
        unset($row);
        json_ok(['products' => $rows]);
    }

    if ($action === 'seller_stats') {
        $user = current_user();
        $statusRows = all_rows(
            'SELECT status, COUNT(*) count_value FROM products WHERE seller_id = ? GROUP BY status',
            [$user['id']]
        );
        $sales = one(
            'SELECT COUNT(*) sold_count, COALESCE(SUM(p.price), 0) revenue
             FROM orders o
             JOIN products p ON p.id = o.product_id
             WHERE o.seller_id = ? AND o.status = "completed"',
            [$user['id']]
        );
        $monthSales = one(
            'SELECT COUNT(*) sold_count, COALESCE(SUM(p.price), 0) revenue
             FROM orders o
             JOIN products p ON p.id = o.product_id
             WHERE o.seller_id = ? AND o.status = "completed"
             AND YEAR(o.completed_at) = YEAR(CURRENT_DATE())
             AND MONTH(o.completed_at) = MONTH(CURRENT_DATE())',
            [$user['id']]
        );
        $monthly = all_rows(
            'SELECT DATE_FORMAT(o.completed_at, "%Y-%m") month_label, COUNT(*) sold_count, COALESCE(SUM(p.price), 0) revenue
             FROM orders o
             JOIN products p ON p.id = o.product_id
             WHERE o.seller_id = ? AND o.status = "completed" AND o.completed_at IS NOT NULL
             GROUP BY DATE_FORMAT(o.completed_at, "%Y-%m")
             ORDER BY month_label DESC
             LIMIT 6',
            [$user['id']]
        );
        $soldItems = all_rows(
            'SELECT o.id order_id, o.completed_at, p.title, p.price, b.display_name buyer_name, s.name school_name, c.name category_name
             FROM orders o
             JOIN products p ON p.id = o.product_id
             JOIN users b ON b.id = o.buyer_id
             JOIN schools s ON s.id = p.school_id
             JOIN categories c ON c.id = p.category_id
             WHERE o.seller_id = ? AND o.status = "completed"
             ORDER BY o.completed_at DESC',
            [$user['id']]
        );
        json_ok([
            'status_counts' => $statusRows,
            'sales' => $sales,
            'month_sales' => $monthSales,
            'monthly' => $monthly,
            'sold_items' => $soldItems,
        ]);
    }

    if ($action === 'update_product_status') {
        $user = current_user();
        $data = input();
        $allowed = ['listed', 'pausing', 'removed', 'sold'];
        if (!in_array($data['status'] ?? '', $allowed, true)) {
            json_fail('商品狀態不正確');
        }
        $product = one('SELECT * FROM products WHERE id = ? AND seller_id = ?', [$data['product_id'] ?? 0, $user['id']]);
        if (!$product) {
            json_fail('找不到你的商品', 404);
        }
        if (!empty($product['locked_by_report'])) {
            json_fail('此商品因檢舉成立已被下架，不能再次上架或更改狀態');
        }
        exec_sql('UPDATE products SET status = ? WHERE id = ?', [$data['status'], $product['id']]);
        json_ok();
    }

    if ($action === 'product_detail') {
        $id = (int) ($_GET['id'] ?? 0);
        $product = one(
            'SELECT p.*, u.display_name seller_name, u.email seller_email, u.avatar_url seller_avatar_url, us.name seller_school_name,
             (SELECT ROUND(AVG(score), 1) FROM ratings WHERE reviewee_id = u.id AND reviewee_role = "seller") seller_rating,
             (SELECT COUNT(*) FROM ratings WHERE reviewee_id = u.id AND reviewee_role = "seller") seller_rating_count,
             s.name school_name, c.name category_name,
             (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order, id LIMIT 1) image_url
             FROM products p
             JOIN users u ON u.id = p.seller_id
             LEFT JOIN schools us ON us.id = u.school_id
             JOIN schools s ON s.id = p.school_id
             JOIN categories c ON c.id = p.category_id
             WHERE p.id = ?',
            [$id]
        );
        if (!$product) {
            json_fail('找不到商品', 404);
        }
        $product['image_url'] = safe_image_url($product['image_url'] ?? '');
        $comments = all_rows(
            'SELECT pc.*, u.display_name user_name FROM product_comments pc JOIN users u ON u.id = pc.user_id WHERE pc.product_id = ? ORDER BY pc.created_at',
            [$id]
        );
        json_ok(['product' => $product, 'comments' => $comments]);
    }

    if ($action === 'create_product') {
        $user = current_user();
        $data = input();
        exec_sql(
            'INSERT INTO products (seller_id, school_id, category_id, title, description, condition_label, price, status) VALUES (?, ?, ?, ?, ?, ?, ?, "listed")',
            [$user['id'], $data['school_id'], $data['category_id'], $data['title'], $data['description'], $data['condition_label'], $data['price']]
        );
        $productId = last_id();
        exec_sql('INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?, ?, 0)', [$productId, safe_image_url($data['image_url'] ?? '')]);
        json_ok(['id' => $productId]);
    }

    if ($action === 'update_product') {
        $user = current_user();
        $data = input();
        $product = one('SELECT * FROM products WHERE id = ? AND seller_id = ?', [$data['product_id'] ?? 0, $user['id']]);
        if (!$product) {
            json_fail('找不到你的商品', 404);
        }
        if (!empty($product['locked_by_report'])) {
            json_fail('此商品因檢舉成立已被下架，不能修改或再次上架');
        }
        if (in_array($product['status'], ['reserved', 'sold'], true)) {
            json_fail('商品已有交易或已售出，不能再修改');
        }
        exec_sql(
            'UPDATE products SET school_id = ?, category_id = ?, title = ?, description = ?, condition_label = ?, price = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$data['school_id'], $data['category_id'], $data['title'], $data['description'], $data['condition_label'], $data['price'], $product['id']]
        );
        if (!empty($data['image_url'])) {
            $nextImage = safe_image_url($data['image_url'] ?? '');
            $image = one('SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order, id LIMIT 1', [$product['id']]);
            if ($image) {
                exec_sql('UPDATE product_images SET image_url = ? WHERE id = ?', [$nextImage, $image['id']]);
            } else {
                exec_sql('INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?, ?, 0)', [$product['id'], $nextImage]);
            }
        }
        json_ok();
    }

    if ($action === 'comment') {
        $user = current_user();
        $data = input();
        exec_sql('INSERT INTO product_comments (product_id, user_id, comment_text) VALUES (?, ?, ?)', [$data['product_id'], $user['id'], $data['text']]);
        json_ok();
    }

    if ($action === 'report') {
        $user = current_user();
        $data = input();
        $product = one('SELECT * FROM products WHERE id = ?', [$data['product_id'] ?? 0]);
        if (!$product) {
            json_fail('找不到商品', 404);
        }
        if (!empty($product['locked_by_report'])) {
            json_fail('此商品已因檢舉成立被下架，無需再次檢舉');
        }
        if (!empty($product['report_cleared'])) {
            json_fail('此商品已由平台審核為符合規定，不能再次檢舉');
        }
        if (one('SELECT id FROM product_reports WHERE product_id = ? AND reporter_id = ?', [$product['id'], $user['id']])) {
            json_fail('你已檢舉過此商品，不能重複檢舉');
        }
        exec_sql('INSERT INTO product_reports (product_id, reporter_id, reason) VALUES (?, ?, ?)', [$data['product_id'], $user['id'], $data['reason']]);
        json_ok();
    }

    if ($action === 'request_category') {
        $user = current_user();
        $data = input();
        exec_sql('INSERT INTO category_requests (requester_id, requested_name) VALUES (?, ?)', [$user['id'], $data['name']]);
        json_ok();
    }

    if ($action === 'request_school') {
        $user = current_user();
        $data = input();
        exec_sql('INSERT INTO school_requests (requester_id, requested_name) VALUES (?, ?)', [$user['id'], $data['name']]);
        json_ok();
    }

    if ($action === 'messages') {
        $user = current_user();
        $threads = all_rows(
            'SELECT t.*, p.title product_title, p.price,
             b.display_name buyer_name, b.avatar_url buyer_avatar_url,
             s.display_name seller_name, s.avatar_url seller_avatar_url,
             (SELECT ROUND(AVG(score), 1) FROM ratings WHERE reviewee_id = b.id AND reviewee_role = "buyer") buyer_rating,
             (SELECT COUNT(*) FROM ratings WHERE reviewee_id = b.id AND reviewee_role = "buyer") buyer_rating_count,
             (SELECT ROUND(AVG(score), 1) FROM ratings WHERE reviewee_id = s.id AND reviewee_role = "seller") seller_rating,
             (SELECT COUNT(*) FROM ratings WHERE reviewee_id = s.id AND reviewee_role = "seller") seller_rating_count
             FROM private_message_threads t
             JOIN products p ON p.id = t.product_id
             JOIN users b ON b.id = t.buyer_id
             JOIN users s ON s.id = t.seller_id
             WHERE t.buyer_id = ? OR t.seller_id = ?
             ORDER BY t.updated_at DESC',
            [$user['id'], $user['id']]
        );
        $messages = [];
        foreach ($threads as $thread) {
            $messages[$thread['id']] = all_rows(
                'SELECT m.*, u.display_name sender_name, u.avatar_url sender_avatar_url FROM private_messages m JOIN users u ON u.id = m.sender_id WHERE m.thread_id = ? ORDER BY m.created_at',
                [$thread['id']]
            );
        }
        json_ok(['threads' => $threads, 'messages' => $messages]);
    }

    if ($action === 'notifications') {
        $user = current_user();
        $orders = all_rows(
            'SELECT o.*, p.title product_title, p.price, b.display_name buyer_name, s.display_name seller_name
             FROM orders o
             JOIN products p ON p.id = o.product_id
             JOIN users b ON b.id = o.buyer_id
             JOIN users s ON s.id = o.seller_id
             WHERE o.buyer_id = ? OR o.seller_id = ?
             ORDER BY o.updated_at DESC, o.created_at DESC
             LIMIT 20',
            [$user['id'], $user['id']]
        );
        $messages = all_rows(
            'SELECT m.*, p.title product_title
             FROM private_messages m
             JOIN private_message_threads t ON t.id = m.thread_id
             JOIN products p ON p.id = t.product_id
             WHERE m.receiver_id = ?
             ORDER BY m.created_at DESC
             LIMIT 5',
            [$user['id']]
        );
        $ratings = all_rows(
            'SELECT o.id, p.title product_title
             FROM orders o
             JOIN products p ON p.id = o.product_id
             WHERE (o.buyer_id = ? OR o.seller_id = ?) AND o.status = "completed"
             AND NOT EXISTS (
                SELECT 1 FROM ratings r
                WHERE r.order_id = o.id AND r.reviewer_id = ?
             )
             ORDER BY o.completed_at DESC
             LIMIT 5',
            [$user['id'], $user['id'], $user['id']]
        );
        json_ok(['orders' => $orders, 'messages' => $messages, 'ratings' => $ratings]);
    }

    if ($action === 'send_message') {
        $user = current_user();
        $data = input();
        if (!empty($data['thread_id'])) {
            $thread = one('SELECT * FROM private_message_threads WHERE id = ?', [$data['thread_id']]);
        } else {
            $product = one('SELECT * FROM products WHERE id = ?', [$data['product_id']]);
            $buyerId = $product['seller_id'] == $user['id'] ? (int) $data['receiver_id'] : (int) $user['id'];
            $sellerId = (int) $product['seller_id'];
            $thread = one('SELECT * FROM private_message_threads WHERE product_id = ? AND buyer_id = ?', [$product['id'], $buyerId]);
            if (!$thread) {
                exec_sql('INSERT INTO private_message_threads (product_id, buyer_id, seller_id) VALUES (?, ?, ?)', [$product['id'], $buyerId, $sellerId]);
                $thread = one('SELECT * FROM private_message_threads WHERE id = ?', [last_id()]);
            }
        }
        $receiverId = $thread['buyer_id'] == $user['id'] ? $thread['seller_id'] : $thread['buyer_id'];
        exec_sql('INSERT INTO private_messages (thread_id, sender_id, receiver_id, message_text) VALUES (?, ?, ?, ?)', [$thread['id'], $user['id'], $receiverId, $data['text']]);
        exec_sql('UPDATE private_message_threads SET updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$thread['id']]);
        json_ok(['thread_id' => $thread['id']]);
    }

    if ($action === 'orders') {
        $user = current_user();
        $orders = all_rows(
            'SELECT o.*, p.title product_title, p.price, b.display_name buyer_name, s.display_name seller_name,
             (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order, id LIMIT 1) image_url
             FROM orders o
             JOIN products p ON p.id = o.product_id
             JOIN users b ON b.id = o.buyer_id
             JOIN users s ON s.id = o.seller_id
             WHERE o.buyer_id = ? OR o.seller_id = ?
             ORDER BY o.created_at DESC',
            [$user['id'], $user['id']]
        );
        foreach ($orders as &$order) {
            $order['image_url'] = safe_image_url($order['image_url'] ?? '');
        }
        unset($order);
        json_ok(['orders' => $orders]);
    }

    if ($action === 'create_order') {
        $user = current_user();
        $data = input();
        $product = one('SELECT * FROM products WHERE id = ?', [$data['product_id']]);
        if (!$product || $product['seller_id'] == $user['id']) {
            json_fail('無法建立訂單');
        }
        if (($product['status'] ?? '') !== 'listed') {
            json_fail('商品目前不能下訂');
        }
        $exists = one('SELECT id FROM orders WHERE product_id = ? AND buyer_id = ? AND status IN ("pending", "in_progress")', [$product['id'], $user['id']]);
        if ($exists) {
            json_fail('你已經對此商品提出購買意願');
        }
        exec_sql('INSERT INTO orders (product_id, buyer_id, seller_id, status, updated_at) VALUES (?, ?, ?, "pending", CURRENT_TIMESTAMP)', [$product['id'], $user['id'], $product['seller_id']]);
        notify_product_users((int)$product['id'], (int)$product['seller_id'], '買家已提出購買意願，請到訂單中心同意或處理。');
        json_ok(['message' => '已送出購買意願，等待賣家同意']);
    }

    if ($action === 'accept_order') {
        $user = current_user();
        $data = input();
        $order = one('SELECT o.*, p.status product_status FROM orders o JOIN products p ON p.id = o.product_id WHERE o.id = ?', [$data['order_id'] ?? 0]);
        if (!$order || $order['seller_id'] != $user['id'] || $order['status'] !== 'pending') {
            json_fail('無法同意此訂單');
        }
        if ($order['product_status'] !== 'listed') {
            json_fail('商品目前不能下訂');
        }
        exec_sql('UPDATE orders SET status = "in_progress", accepted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$order['id']]);
        exec_sql('UPDATE orders SET status = "cancelled", updated_at = CURRENT_TIMESTAMP WHERE product_id = ? AND id <> ? AND status = "pending"', [$order['product_id'], $order['id']]);
        exec_sql('UPDATE products SET status = "reserved", updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$order['product_id']]);
        notify_product_users((int)$order['product_id'], (int)$order['buyer_id'], '賣家已同意你的購買意願，訂單已進入處理中。');
        json_ok();
    }

    if ($action === 'cancel_order') {
        $user = current_user();
        $data = input();
        $order = one('SELECT o.*, p.status product_status, p.title product_title FROM orders o JOIN products p ON p.id = o.product_id WHERE o.id = ?', [$data['order_id'] ?? 0]);
        if (!$order || $order['seller_id'] != $user['id']) {
            json_fail('只有賣家可以取消此訂單');
        }
        if (!in_array($order['status'], ['pending', 'in_progress'], true)) {
            json_fail('此訂單目前不能取消');
        }
        exec_sql('UPDATE orders SET status = "cancelled", updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$order['id']]);
        if ($order['product_status'] === 'reserved') {
            exec_sql('UPDATE products SET status = "listed", updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$order['product_id']]);
        }
        notify_product_users((int)$order['product_id'], (int)$order['buyer_id'], '賣家已取消「' . $order['product_title'] . '」這筆訂單，商品已恢復可交易狀態。');
        json_ok(['message' => '訂單已取消，商品已恢復上架']);
    }

    if ($action === 'complete_order') {
        $user = current_user();
        $data = input();
        $order = one(
            'SELECT o.*, p.title product_title, p.price, b.email buyer_email, b.display_name buyer_name, s.email seller_email, s.display_name seller_name
             FROM orders o
             JOIN products p ON p.id = o.product_id
             JOIN users b ON b.id = o.buyer_id
             JOIN users s ON s.id = o.seller_id
             WHERE o.id = ?',
            [$data['order_id']]
        );
        if (!$order || ($order['buyer_id'] != $user['id'] && $order['seller_id'] != $user['id'])) {
            json_fail('找不到訂單');
        }
        if ($order['status'] !== 'in_progress') {
            json_fail('訂單目前不能完成');
        }
        $field = $order['buyer_id'] == $user['id'] ? 'buyer_completed_at' : 'seller_completed_at';
        exec_sql("UPDATE orders SET $field = COALESCE($field, CURRENT_TIMESTAMP), updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$order['id']]);
        $freshOrder = one('SELECT buyer_completed_at, seller_completed_at FROM orders WHERE id = ?', [$order['id']]);
        $otherId = $order['buyer_id'] == $user['id'] ? $order['seller_id'] : $order['buyer_id'];
        if (empty($freshOrder['buyer_completed_at']) || empty($freshOrder['seller_completed_at'])) {
            notify_product_users((int)$order['product_id'], (int)$otherId, '對方已按下完成訂單，請到訂單中心確認是否完成這筆交易。');
            json_ok(['waiting' => true]);
        }
        exec_sql('UPDATE orders SET status = "completed", completed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$order['id']]);
        exec_sql('UPDATE products SET status = "sold", updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$order['product_id']]);
        $body = "你的校園二手交易訂單已完成。\n\n商品：{$order['product_title']}\n金額：NT$ {$order['price']}\n買家：{$order['buyer_name']}\n賣家：{$order['seller_name']}\n\n請回到平台為交易對象留下評價。";
        send_platform_mail([$order['buyer_email'], $order['seller_email']], '訂單已完成：' . $order['product_title'], $body);
        exec_sql('INSERT INTO email_logs (receiver_email, subject, body, event_type, status, sent_at) VALUES (?, ?, ?, "order_completed", "sent", CURRENT_TIMESTAMP)', [$order['buyer_email'] . ',' . $order['seller_email'], '訂單已完成：' . $order['product_title'], $body]);
        notify_product_users((int)$order['product_id'], (int)$order['buyer_id'], '訂單已完成，請到訂單中心留下評價。');
        notify_product_users((int)$order['product_id'], (int)$order['seller_id'], '訂單已完成，請到訂單中心留下評價。');
        json_ok(['completed' => true]);
    }

    if ($action === 'rate') {
        $user = current_user();
        $data = input();
        $order = one('SELECT * FROM orders WHERE id = ? AND status = "completed"', [$data['order_id']]);
        if (!$order) {
            json_fail('訂單尚未完成');
        }
        $score = max(1, min(5, (int)($data['score'] ?? 5)));
        $reviewee = $order['buyer_id'] == $user['id'] ? $order['seller_id'] : $order['buyer_id'];
        $revieweeRole = $order['buyer_id'] == $user['id'] ? 'seller' : 'buyer';
        $existing = one('SELECT id FROM ratings WHERE order_id = ? AND reviewer_id = ?', [$order['id'], $user['id']]);
        if ($existing) {
            exec_sql('UPDATE ratings SET score = ?, review_text = ? WHERE id = ?', [$score, $data['text'] ?? '', $existing['id']]);
        } else {
            exec_sql('INSERT INTO ratings (order_id, reviewer_id, reviewee_id, reviewee_role, score, review_text) VALUES (?, ?, ?, ?, ?, ?)', [$order['id'], $user['id'], $reviewee, $revieweeRole, $score, $data['text'] ?? '']);
        }
        json_ok();
    }

    if ($action === 'admin_dashboard') {
        require_admin();
        json_ok([
            'reports' => all_rows('SELECT r.*, p.title product_title, u.display_name reporter_name FROM product_reports r JOIN products p ON p.id = r.product_id JOIN users u ON u.id = r.reporter_id ORDER BY r.created_at DESC'),
            'category_requests' => all_rows('SELECT cr.*, u.display_name requester_name FROM category_requests cr JOIN users u ON u.id = cr.requester_id ORDER BY cr.created_at DESC'),
            'school_requests' => all_rows('SELECT sr.*, u.display_name requester_name FROM school_requests sr JOIN users u ON u.id = sr.requester_id ORDER BY sr.created_at DESC'),
            'orders' => all_rows('SELECT o.*, p.title product_title FROM orders o JOIN products p ON p.id = o.product_id ORDER BY o.created_at DESC'),
            'sales_summary' => one('SELECT COUNT(*) sold_count, COALESCE(SUM(p.price), 0) revenue FROM orders o JOIN products p ON p.id = o.product_id WHERE o.status = "completed"'),
            'month_summary' => one('SELECT COUNT(*) sold_count, COALESCE(SUM(p.price), 0) revenue FROM orders o JOIN products p ON p.id = o.product_id WHERE o.status = "completed" AND YEAR(o.completed_at) = YEAR(CURRENT_DATE()) AND MONTH(o.completed_at) = MONTH(CURRENT_DATE())'),
            'monthly_sales' => all_rows('SELECT DATE_FORMAT(o.completed_at, "%Y-%m") month_label, COUNT(*) sold_count, COALESCE(SUM(p.price), 0) revenue FROM orders o JOIN products p ON p.id = o.product_id WHERE o.status = "completed" AND o.completed_at IS NOT NULL GROUP BY DATE_FORMAT(o.completed_at, "%Y-%m") ORDER BY month_label DESC LIMIT 6'),
            'sold_items' => all_rows(
                'SELECT o.id order_id, o.completed_at, p.title product_title, p.price, b.display_name buyer_name, s.display_name seller_name, sc.name school_name, c.name category_name
                 FROM orders o
                 JOIN products p ON p.id = o.product_id
                 JOIN users b ON b.id = o.buyer_id
                 JOIN users s ON s.id = o.seller_id
                 JOIN schools sc ON sc.id = p.school_id
                 JOIN categories c ON c.id = p.category_id
                 WHERE o.status = "completed"
                 ORDER BY o.completed_at DESC'
            ),
        ]);
    }

    if ($action === 'reset_moderation_locks') {
        require_admin();
        exec_sql('UPDATE users SET suspended_until = NULL, suspension_reason = NULL, moderation_warning_count = 0, moderation_warning_month = NULL WHERE role <> "admin"');
        exec_sql('UPDATE products SET locked_by_report = 0, removed_by_report_id = NULL, report_cleared = 0, report_cleared_comment = NULL WHERE locked_by_report = 1 OR report_cleared = 1');
        json_ok();
    }

    if ($action === 'review_request') {
        $admin = require_admin();
        $data = input();
        $table = $data['type'] === 'school' ? 'school_requests' : 'category_requests';
        $target = $data['type'] === 'school' ? 'schools' : 'categories';
        $request = one("SELECT * FROM $table WHERE id = ?", [$data['id']]);
        if (!$request) {
            json_fail('找不到申請');
        }
        $status = $data['approved'] ? 'approved' : 'rejected';
        exec_sql("UPDATE $table SET status = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?", [$status, $admin['id'], $request['id']]);
        if ($data['approved'] && !one("SELECT id FROM $target WHERE name = ?", [$request['requested_name']])) {
            exec_sql("INSERT INTO $target (name) VALUES (?)", [$request['requested_name']]);
        }
        json_ok();
    }

    if ($action === 'review_report') {
        $admin = require_admin();
        $data = input();
        $report = one(
            'SELECT r.*, p.seller_id, p.title product_title
             FROM product_reports r
             JOIN products p ON p.id = r.product_id
             WHERE r.id = ? OR (p.id = ? AND r.status = "pending")
             ORDER BY r.status = "pending" DESC, r.created_at DESC
             LIMIT 1',
            [$data['id'] ?? 0, $data['product_id'] ?? 0]
        );
        if (!$report) {
            json_fail('找不到檢舉');
        }
        $productId = (int)$report['product_id'];
        $pendingReports = all_rows(
            'SELECT r.*, p.seller_id, p.title product_title
             FROM product_reports r
             JOIN products p ON p.id = r.product_id
             WHERE r.product_id = ? AND r.status = "pending"',
            [$productId]
        );
        if (!$pendingReports) {
            $pendingReports = [$report];
        }
        $adminComment = trim($data['comment'] ?? '');
        if ($adminComment === '') {
            json_fail('請輸入審核評語，讓賣方與檢舉申請人了解原因');
        }
        exec_sql('UPDATE product_reports SET status = ?, admin_comment = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE product_id = ? AND status = "pending"', [$data['approved'] ? 'approved' : 'rejected', $adminComment, $admin['id'], $productId]);
        if ($data['approved']) {
            $reason = '檢舉成立：' . $adminComment;
            exec_sql(
                'UPDATE products SET status = "removed", removed_reason = ?, removed_at = CURRENT_TIMESTAMP, removed_by_report_id = ?, locked_by_report = 1, report_cleared = 0 WHERE id = ?',
                [$reason, $report['id'], $productId]
            );
            $monthlyRemoved = one(
                'SELECT COUNT(*) count_value
                 FROM products
                 WHERE seller_id = ?
                 AND locked_by_report = 1
                 AND removed_at >= DATE_FORMAT(CURRENT_DATE(), "%Y-%m-01")',
                [$report['seller_id']]
            );
            if ((int)($monthlyRemoved['count_value'] ?? 0) >= 3) {
                exec_sql(
                    'UPDATE users SET suspended_until = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 MONTH), suspension_reason = ? WHERE id = ? AND role <> "admin"',
                    ['一個月內被檢舉成立下架商品達 3 件，依網站規範停權 1 個月', $report['seller_id']]
                );
            }
            $notified = [];
            foreach ($pendingReports as $pendingReport) {
                $reporterId = (int)$pendingReport['reporter_id'];
                if (isset($notified[$reporterId])) {
                    continue;
                }
                $notified[$reporterId] = true;
                send_platform_notice(
                    $productId,
                    $reporterId,
                    '平台通知：你檢舉的商品「' . $report['product_title'] . '」已審核成立並下架。審核評語：' . $adminComment
                );
            }
            send_platform_notice(
                $productId,
                (int)$report['seller_id'],
                '平台通知：你的商品「' . $report['product_title'] . '」經平台審核後已下架。審核評語：' . $adminComment . '。此通知不提供檢舉人身分。'
            );
        } else {
            exec_sql(
                'UPDATE products SET report_cleared = 1, report_cleared_comment = ? WHERE id = ?',
                ['平台審核符合規定：' . $adminComment, $productId]
            );
            exec_sql(
                'UPDATE product_reports SET status = "rejected", admin_comment = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE product_id = ? AND status = "pending"',
                [$adminComment, $admin['id'], $productId]
            );
            $notified = [];
            foreach ($pendingReports as $pendingReport) {
                $reporterId = (int)$pendingReport['reporter_id'];
                if (isset($notified[$reporterId])) {
                    continue;
                }
                $notified[$reporterId] = true;
                send_platform_notice(
                    $productId,
                    $reporterId,
                    '平台通知：你檢舉的商品「' . $report['product_title'] . '」已審核為符合規定，商品保留。審核評語：' . $adminComment
                );
            }
            send_platform_notice(
                $productId,
                (int)$report['seller_id'],
                '平台通知：你的商品「' . $report['product_title'] . '」曾收到檢舉，平台已審核為符合規定，商品保留。審核評語：' . $adminComment . '。此通知不提供檢舉人身分。'
            );
        }
        json_ok();
    }

    json_fail('未知的 API 動作', 404);
} catch (Throwable $e) {
    json_fail($e->getMessage(), 500);
}
