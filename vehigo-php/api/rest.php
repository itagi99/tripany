<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');
$body = json_decode(file_get_contents('php://input'), true) ?? [];

function jsonOut($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }
function jsonErr($msg, $code = 400) { jsonOut(['error' => $msg], $code); }

function getAuthUser() {
    global $pdo;
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) jsonErr('Unauthorized', 401);
    $token = $m[1];
    $st = $pdo->prepare("SELECT id, name, phone, email, 'user' as role FROM users WHERE api_token=? AND is_verified=1");
    $st->execute([$token]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if ($u) return $u;
    $st = $pdo->prepare("SELECT id, name, phone, email, 'driver' as role FROM drivers WHERE api_token=?");
    $st->execute([$token]);
    $d = $st->fetch(PDO::FETCH_ASSOC);
    if ($d) return $d;
    jsonErr('Unauthorized', 401);
}

try {
    // ─── AUTH ──────────────────────────────────────────────────────────
    if ($uri === '/api/auth/register' && $method === 'POST') {
        $name = $body['name'] ?? ''; $phone = $body['phone'] ?? '';
        $password = $body['password'] ?? ''; $type = $body['type'] ?? 'user';
        $email = $body['email'] ?? '';
        if (!$name || !$phone || !$password) jsonErr('name, phone, password required');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));
        if ($type === 'driver') {
            $st = $pdo->prepare("INSERT INTO drivers (name, phone, email, password_hash, vehicle_type, vehicle_number, vehicle_model, license_number, api_token, status) VALUES (?,?,?,?,?,?,?,?,?,'offline')");
            $st->execute([$name, $phone, $email, $hash, $body['vehicle_type']??'', $body['vehicle_number']??'', $body['vehicle_model']??'', $body['license_number']??'', $token]);
            $id = $pdo->lastInsertId();
            jsonOut(['token' => $token, 'user' => ['id' => $id, 'name' => $name, 'phone' => $phone, 'email' => $email, 'role' => 'driver']]);
        } else {
            $st = $pdo->prepare("INSERT INTO users (name, phone, email, password_hash, api_token, is_verified) VALUES (?,?,?,?,?,1)");
            $st->execute([$name, $phone, $email, $hash, $token]);
            $id = $pdo->lastInsertId();
            jsonOut(['token' => $token, 'user' => ['id' => $id, 'name' => $name, 'phone' => $phone, 'email' => $email, 'role' => 'user']]);
        }
    }

    if ($uri === '/api/auth/login' && $method === 'POST') {
        $phone = $body['phone'] ?? ''; $password = $body['password'] ?? '';
        $type = $body['type'] ?? 'user';
        if (!$phone || !$password) jsonErr('phone and password required');
        if ($type === 'driver') {
            $st = $pdo->prepare("SELECT * FROM drivers WHERE phone=?");
        } else {
            $st = $pdo->prepare("SELECT * FROM users WHERE phone=?");
        }
        $st->execute([$phone]); $u = $st->fetch(PDO::FETCH_ASSOC);
        if (!$u || !password_verify($password, $u['password_hash'])) jsonErr('Invalid credentials', 401);
        $token = $u['api_token'];
        if (!$token) { $token = bin2hex(random_bytes(32)); $pdo->prepare("UPDATE {$type}s SET api_token=? WHERE id=?")->execute([$token, $u['id']]); }
        $role = $type === 'driver' ? 'driver' : 'user';
        jsonOut(['token' => $token, 'user' => ['id' => $u['id'], 'name' => $u['name'], 'phone' => $u['phone'], 'email' => $u['email']??'', 'role' => $role]]);
    }

    if ($uri === '/api/auth/me' && $method === 'GET') {
        $u = getAuthUser();
        if ($u['role'] === 'driver') {
            $st = $pdo->prepare("SELECT * FROM drivers WHERE id=?");
            $st->execute([$u['id']]); $d = $st->fetch(PDO::FETCH_ASSOC);
            jsonOut(['id' => $d['id'], 'name' => $d['name'], 'phone' => $d['phone'], 'email' => $d['email'], 'role' => 'driver', 'status' => $d['status'], 'rating' => $d['rating'], 'vehicle_type' => $d['vehicle_type'], 'vehicle_number' => $d['vehicle_number'], 'vehicle_model' => $d['vehicle_model']]);
        }
        jsonOut($u);
    }

    // ─── VEHICLES ──────────────────────────────────────────────────────
    if ($uri === '/api/vehicles' && $method === 'GET') {
        $st = $pdo->query("SELECT v.*, c.name as category_name FROM vehicles v LEFT JOIN vehicle_categories c ON v.category_id = c.id WHERE v.is_active=1 ORDER BY v.is_featured DESC, v.rating DESC");
        jsonOut($st->fetchAll(PDO::FETCH_ASSOC));
    }

    // ─── BOOKINGS (customer) ───────────────────────────────────────────
    if ($uri === '/api/bookings' && $method === 'POST') {
        $u = getAuthUser();
        if ($u['role'] !== 'user') jsonErr('Only customers can book', 403);
        $ref = 'VH' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $vid = $body['vehicle_id'] ?? 0;
        $st = $pdo->prepare("INSERT INTO bookings (user_id, vehicle_id, booking_ref, pickup_date, return_date, pickup_location, pickup_lat, pickup_lng, drop_location, drop_lat, drop_lng, distance_km, base_fare, tax, discount, total_fare, payment_method, payment_status, status, coupon_id, booking_notes, pickup_time, trip_type, pickup_city, drop_city, stops) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $fare = $body['fare'] ?? 0;
        $tax = round($fare * 0.05, 2);
        $pickupDate = $body['pickup_date'] ?? date('Y-m-d');
        $returnDate = $body['return_date'] ?? date('Y-m-d', strtotime('+1 day'));
        $stopsVal = is_array($body['stops'] ?? null) ? json_encode($body['stops']) : ($body['stops'] ?? '');
        $st->execute([$u['id'], $vid, $ref, $pickupDate, $returnDate, $body['pickup_location']??'', $body['pickup_lat']??null, $body['pickup_lng']??null, $body['drop_location']??'', $body['drop_lat']??null, $body['drop_lng']??null, $body['distance_km']??0, $fare, $tax, $body['discount']??0, $body['total_fare']??$fare, $body['payment_method']??'UPI', 'pending', 'pending', $body['coupon_id']??null, $body['booking_notes']??'', $body['pickup_time']??'', $body['trip_type']??'one_way', $body['pickup_city']??'', $body['drop_city']??'', $stopsVal]);
        $id = $pdo->lastInsertId();
        jsonOut(['id' => $id, 'booking_ref' => $ref, 'status' => 'pending'], 201);
    }

    if ($uri === '/api/bookings/my' && $method === 'GET') {
        $u = getAuthUser();
        if ($u['role'] !== 'user') jsonErr('Only customers', 403);
        $st = $pdo->prepare("SELECT b.*, v.name as vehicle_name, v.type as vehicle_type, v.image FROM bookings b LEFT JOIN vehicles v ON b.vehicle_id = v.id WHERE b.user_id=? ORDER BY b.created_at DESC");
        $st->execute([$u['id']]);
        jsonOut($st->fetchAll(PDO::FETCH_ASSOC));
    }

    if (preg_match('#^/api/bookings/(\d+)$#', $uri, $m) && $method === 'GET') {
        $u = getAuthUser();
        $st = $pdo->prepare("SELECT b.*, v.name as vehicle_name, v.type as vehicle_type, v.image FROM bookings b LEFT JOIN vehicles v ON b.vehicle_id = v.id WHERE b.id=?");
        $st->execute([$m[1]]); $b = $st->fetch(PDO::FETCH_ASSOC);
        if (!$b) jsonErr('Not found', 404);
        if ($u['role'] === 'user' && $b['user_id'] != $u['id']) jsonErr('Forbidden', 403);
        jsonOut($b);
    }

    if (preg_match('#^/api/bookings/(\d+)/cancel$#', $uri, $m) && $method === 'PUT') {
        $u = getAuthUser();
        $st = $pdo->prepare("SELECT * FROM bookings WHERE id=?");
        $st->execute([$m[1]]); $b = $st->fetch(PDO::FETCH_ASSOC);
        if (!$b) jsonErr('Not found', 404);
        if ($u['role'] === 'user' && $b['user_id'] != $u['id']) jsonErr('Forbidden', 403);
        $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=?")->execute([$m[1]]);
        jsonOut(['success' => true, 'status' => 'cancelled']);
    }

    if (preg_match('#^/api/bookings/(\d+)/rate$#', $uri, $m) && $method === 'PUT') {
        $u = getAuthUser();
        $st = $pdo->prepare("SELECT * FROM bookings WHERE id=?");
        $st->execute([$m[1]]); $b = $st->fetch(PDO::FETCH_ASSOC);
        if (!$b || $b['user_id'] != $u['id']) jsonErr('Forbidden', 403);
        $rating = $body['rating'] ?? 5; $review = $body['review'] ?? '';
        $pdo->prepare("UPDATE bookings SET rating=?, review=? WHERE id=?")->execute([$rating, $review, $m[1]]);
        $pdo->prepare("INSERT INTO reviews (vehicle_id, user_id, booking_id, rating, comment) VALUES (?,?,?,?,?)")->execute([$b['vehicle_id'], $u['id'], $m[1], $rating, $review]);
        $pdo->query("UPDATE vehicles SET total_reviews = (SELECT COUNT(*) FROM reviews WHERE vehicle_id={$b['vehicle_id']}), rating = (SELECT AVG(rating) FROM reviews WHERE vehicle_id={$b['vehicle_id']}) WHERE id={$b['vehicle_id']}");
        jsonOut(['success' => true]);
    }

    // ─── COUPONS ───────────────────────────────────────────────────────
    if ($uri === '/api/coupons/validate' && $method === 'POST') {
        $code = $body['code'] ?? ''; $fare = $body['fare'] ?? 0;
        if (!$code) jsonErr('Code required');
        $st = $pdo->prepare("SELECT * FROM coupons WHERE code=? AND active=1 AND (valid_to IS NULL OR valid_to >= date('now')) AND (max_uses IS NULL OR used_count < max_uses)");
        $st->execute([$code]); $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) jsonErr('Invalid or expired coupon');
        if ($fare < ($c['min_booking_amount'] ?? 0)) jsonErr('Minimum booking amount not met');
        $discount = $c['discount_amount'] > 0 ? $c['discount_amount'] : round($fare * ($c['discount_percent'] / 100), 2);
        $totalFare = max(0, $fare - $discount);
        $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id=?")->execute([$c['id']]);
        jsonOut(['coupon_id' => $c['id'], 'code' => $c['code'], 'discount' => $discount, 'total_fare' => $totalFare]);
    }

    // ─── PUBLIC ────────────────────────────────────────────────────────
    if ($uri === '/api/public/banners' && $method === 'GET') {
        $st = $pdo->query("SELECT * FROM banners WHERE active=1 ORDER BY id DESC");
        jsonOut($st->fetchAll(PDO::FETCH_ASSOC));
    }
    if ($uri === '/api/public/offers' && $method === 'GET') {
        $st = $pdo->query("SELECT * FROM offers WHERE active=1 AND (valid_to IS NULL OR valid_to >= date('now')) ORDER BY id DESC");
        jsonOut($st->fetchAll(PDO::FETCH_ASSOC));
    }
    if ($uri === '/api/public/coupons/validate' && $method === 'POST') {
        $code = $body['code'] ?? ''; $fare = $body['fare'] ?? 0;
        if (!$code) jsonErr('Code required');
        $st = $pdo->prepare("SELECT * FROM coupons WHERE code=? AND active=1 AND (valid_to IS NULL OR valid_to >= date('now')) AND (max_uses IS NULL OR used_count < max_uses)");
        $st->execute([$code]); $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) jsonErr('Invalid or expired coupon');
        if ($fare < ($c['min_booking_amount'] ?? 0)) jsonErr('Min booking ₹' . $c['min_booking_amount'] . ' required');
        $discount = $c['discount_amount'] > 0 ? $c['discount_amount'] : round($fare * ($c['discount_percent'] / 100), 2);
        $totalFare = max(0, $fare - $discount);
        jsonOut(['coupon_id' => $c['id'], 'code' => $c['code'], 'discount' => $discount, 'total_fare' => $totalFare]);
    }

    // ─── DRIVER ────────────────────────────────────────────────────────
    if ($uri === '/api/driver/status' && $method === 'PUT') {
        $u = getAuthUser();
        if ($u['role'] !== 'driver') jsonErr('Only drivers', 403);
        $status = $body['status'] ?? 'offline';
        $pdo->prepare("UPDATE drivers SET status=? WHERE id=?")->execute([$status, $u['id']]);
        jsonOut(['success' => true, 'status' => $status]);
    }

    if ($uri === '/api/driver/bookings/pending' && $method === 'GET') {
        getAuthUser();
        $st = $pdo->query("SELECT b.*, v.name as vehicle_name, v.type as vehicle_type, u.name as user_name, u.phone as user_phone FROM bookings b LEFT JOIN vehicles v ON b.vehicle_id = v.id LEFT JOIN users u ON b.user_id = u.id WHERE b.status='pending' AND b.driver_id IS NULL ORDER BY b.created_at DESC");
        jsonOut($st->fetchAll(PDO::FETCH_ASSOC));
    }

    if (preg_match('#^/api/driver/bookings/(\d+)/accept$#', $uri, $m) && $method === 'PUT') {
        $u = getAuthUser();
        if ($u['role'] !== 'driver') jsonErr('Only drivers', 403);
        $pdo->prepare("UPDATE bookings SET driver_id=?, driver_name=?, driver_phone=?, status='confirmed' WHERE id=? AND driver_id IS NULL")->execute([$u['id'], $u['name'], $u['phone'], $m[1]]);
        jsonOut(['success' => true, 'status' => 'confirmed']);
    }

    if (preg_match('#^/api/driver/bookings/(\d+)/reject$#', $uri, $m) && $method === 'PUT') {
        getAuthUser();
        $pdo->prepare("UPDATE bookings SET status='pending' WHERE id=?")->execute([$m[1]]);
        jsonOut(['success' => true]);
    }

    if (preg_match('#^/api/driver/bookings/(\d+)/complete$#', $uri, $m) && $method === 'PUT') {
        $u = getAuthUser();
        if ($u['role'] !== 'driver') jsonErr('Only drivers', 403);
        $pdo->prepare("UPDATE bookings SET status='completed', completed_at=CURRENT_TIMESTAMP WHERE id=? AND driver_id=?")->execute([$m[1], $u['id']]);
        jsonOut(['success' => true, 'status' => 'completed']);
    }

    if ($uri === '/api/driver/bookings' && $method === 'GET') {
        $u = getAuthUser();
        if ($u['role'] !== 'driver') jsonErr('Only drivers', 403);
        $st = $pdo->prepare("SELECT b.*, u.name as user_name, u.phone as user_phone FROM bookings b LEFT JOIN users u ON b.user_id = u.id WHERE b.driver_id=? ORDER BY b.created_at DESC");
        $st->execute([$u['id']]);
        jsonOut($st->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($uri === '/api/driver/stats' && $method === 'GET') {
        $u = getAuthUser();
        if ($u['role'] !== 'driver') jsonErr('Only drivers', 403);
        $today = $pdo->prepare("SELECT COALESCE(SUM(total_fare),0) as earnings, COUNT(*) as rides FROM bookings WHERE driver_id=? AND status='completed' AND date(completed_at)=date('now')");
        $today->execute([$u['id']]); $t = $today->fetch(PDO::FETCH_ASSOC);
        jsonOut(['today_earnings' => $t['earnings'], 'total_rides' => $t['rides']]);
    }

    // ─── ADMIN ─────────────────────────────────────────────────────────
    if (str_starts_with($uri, '/api/admin/')) {
        $u = getAuthUser();
        if ($u['role'] !== 'driver' && $u['role'] !== 'user') jsonErr('Admin access denied', 403);
        // Simple admin check: any logged-in user with phone starting with "999" is admin
        if (!str_starts_with($u['phone'], '999')) jsonErr('Admin access denied', 403);
    }

    if ($uri === '/api/admin/stats' && $method === 'GET') {
        jsonOut([
            'total_vehicles' => $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn(),
            'total_bookings' => $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
            'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'total_drivers' => $pdo->query("SELECT COUNT(*) FROM drivers")->fetchColumn(),
            'active_drivers' => $pdo->query("SELECT COUNT(*) FROM drivers WHERE status='online'")->fetchColumn(),
            'pending_bookings' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn(),
            'revenue' => $pdo->query("SELECT COALESCE(SUM(total_fare),0) FROM bookings WHERE status='completed'")->fetchColumn(),
        ]);
    }

    if ($uri === '/api/admin/bookings' && $method === 'GET') {
        $st = $pdo->query("SELECT b.*, u.name as user_name, u.phone as user_phone, v.name as vehicle_name FROM bookings b LEFT JOIN users u ON b.user_id = u.id LEFT JOIN vehicles v ON b.vehicle_id = v.id ORDER BY b.created_at DESC");
        jsonOut($st->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($uri === '/api/admin/drivers' && $method === 'GET') {
        jsonOut($pdo->query("SELECT * FROM drivers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($uri === '/api/admin/vehicles' && $method === 'POST') {
        $st = $pdo->prepare("INSERT INTO vehicles (category_id, name, brand, model, year, type, fuel_type, transmission, seats, bags, price_per_day, price_per_km, image, description, features, inclusions, exclusions, facilities, terms, cancellation_policy, is_active, is_featured) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,0)");
        $st->execute([$body['category_id']??null, $body['name']??'', $body['brand']??'', $body['model']??'', $body['year']??date('Y'), $body['type']??'Sedan', $body['fuel_type']??'Petrol', $body['transmission']??'Manual', $body['seats']??4, $body['bags']??2, $body['price_per_day']??0, $body['price_per_km']??0, $body['image']??'', $body['description']??'', $body['features']??'', $body['inclusions']??'', $body['exclusions']??'', $body['facilities']??'', $body['terms']??'', $body['cancellation_policy']??'Free cancellation within 30 minutes']);
        jsonOut(['id' => $pdo->lastInsertId()], 201);
    }

    if (preg_match('#^/api/admin/vehicles/(\d+)$#', $uri, $m) && $method === 'PUT') {
        $st = $pdo->prepare("UPDATE vehicles SET category_id=?, name=?, brand=?, model=?, year=?, type=?, fuel_type=?, transmission=?, seats=?, bags=?, price_per_day=?, price_per_km=?, image=?, description=?, features=?, inclusions=?, exclusions=?, facilities=?, terms=?, cancellation_policy=?, is_active=?, is_featured=? WHERE id=?");
        $st->execute([$body['category_id']??null, $body['name']??'', $body['brand']??'', $body['model']??'', $body['year']??date('Y'), $body['type']??'Sedan', $body['fuel_type']??'Petrol', $body['transmission']??'Manual', $body['seats']??4, $body['bags']??2, $body['price_per_day']??0, $body['price_per_km']??0, $body['image']??'', $body['description']??'', $body['features']??'', $body['inclusions']??'', $body['exclusions']??'', $body['facilities']??'', $body['terms']??'', $body['cancellation_policy']??'', $body['is_active']??1, $body['is_featured']??0, $m[1]]);
        jsonOut(['success' => true]);
    }

    if (preg_match('#^/api/admin/vehicles/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        $pdo->prepare("DELETE FROM vehicles WHERE id=?")->execute([$m[1]]);
        jsonOut(['success' => true]);
    }

    if ($uri === '/api/admin/coupons' && $method === 'GET') {
        jsonOut($pdo->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC));
    }
    if ($uri === '/api/admin/coupons' && $method === 'POST') {
        $st = $pdo->prepare("INSERT INTO coupons (code, description, discount_percent, discount_amount, min_booking_amount, max_uses, valid_from, valid_to) VALUES (?,?,?,?,?,?,?,?)");
        $st->execute([$body['code']??'', $body['description']??'', $body['discount_percent']??0, $body['discount_amount']??0, $body['min_booking_amount']??0, $body['max_uses']??100, $body['valid_from']??null, $body['valid_to']??null]);
        jsonOut(['id' => $pdo->lastInsertId()], 201);
    }
    if (preg_match('#^/api/admin/coupons/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        $pdo->prepare("DELETE FROM coupons WHERE id=?")->execute([$m[1]]);
        jsonOut(['success' => true]);
    }

    if ($uri === '/api/admin/banners' && $method === 'GET') {
        jsonOut($pdo->query("SELECT * FROM banners ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC));
    }
    if ($uri === '/api/admin/banners' && $method === 'POST') {
        $st = $pdo->prepare("INSERT INTO banners (title, subtitle, image_url, link) VALUES (?,?,?,?)");
        $st->execute([$body['title']??'', $body['subtitle']??'', $body['image_url']??'', $body['link']??'']);
        jsonOut(['id' => $pdo->lastInsertId()], 201);
    }
    if (preg_match('#^/api/admin/banners/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        $pdo->prepare("DELETE FROM banners WHERE id=?")->execute([$m[1]]);
        jsonOut(['success' => true]);
    }

    if ($uri === '/api/admin/offers' && $method === 'GET') {
        jsonOut($pdo->query("SELECT * FROM offers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC));
    }
    if ($uri === '/api/admin/offers' && $method === 'POST') {
        $st = $pdo->prepare("INSERT INTO offers (title, description, discount_percent, valid_from, valid_to) VALUES (?,?,?,?,?)");
        $st->execute([$body['title']??'', $body['description']??'', $body['discount_percent']??0, $body['valid_from']??null, $body['valid_to']??null]);
        jsonOut(['id' => $pdo->lastInsertId()], 201);
    }
    if (preg_match('#^/api/admin/offers/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        $pdo->prepare("DELETE FROM offers WHERE id=?")->execute([$m[1]]);
        jsonOut(['success' => true]);
    }

    jsonErr('Not found', 404);

} catch (Exception $e) {
    jsonErr($e->getMessage(), 500);
}
