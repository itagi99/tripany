<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = array_merge($_GET, json_decode(file_get_contents('php://input'), true) ?? $_POST);

function jsonResponse($data, $status = 200) { http_response_code($status); echo json_encode($data); exit; }
function jsonError($msg, $status = 400) { jsonResponse(['success' => false, 'error' => $msg], $status); }

$userId = $_SESSION['user_id'] ?? null;

try {
    switch ($action) {

        case 'auth/login':
            $phone = $input['phone'] ?? '';
            if (strlen($phone) < 10) jsonError('Invalid phone number');
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_phone'] = $phone;
            jsonResponse(['success' => true, 'message' => 'OTP sent', 'otp' => $otp]);

        case 'auth/verify':
            $otp = $input['otp'] ?? '';
            $phone = $input['phone'] ?? $_SESSION['otp_phone'] ?? '';
            if ($otp !== $_SESSION['otp']) jsonError('Invalid OTP', 401);
            unset($_SESSION['otp']);
            $stmt = $pdo->prepare("SELECT * FROM users WHERE phone=?");
            $stmt->execute([$phone]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                $pdo->prepare("INSERT INTO users (name, phone, password_hash, is_verified) VALUES (?, ?, ?, 1)")
                    ->execute(['User', $phone, password_hash('user123', PASSWORD_DEFAULT)]);
                $user = $pdo->query("SELECT * FROM users WHERE phone='$phone'")->fetch(PDO::FETCH_ASSOC);
            }
            $_SESSION['user_id'] = $user['id'];
            jsonResponse(['success' => true, 'user' => $user]);

        case 'vehicles/list':
            $type = $_GET['type'] ?? '';
            $query = "SELECT v.*, vc.name as category_name FROM vehicles v LEFT JOIN vehicle_categories vc ON v.category_id = vc.id WHERE v.is_active=1";
            $params = [];
            if ($type) { $query .= " AND v.type=?"; $params[] = $type; }
            $query .= " ORDER BY v.is_featured DESC, v.rating DESC LIMIT 20";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));

        case 'vehicles/detail':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT v.*, vc.name as category_name FROM vehicles v LEFT JOIN vehicle_categories vc ON v.category_id = vc.id WHERE v.id=?");
            $stmt->execute([$id]);
            $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$vehicle) jsonError('Vehicle not found', 404);
            $reviews = $pdo->prepare("SELECT r.*, u.name as user_name FROM reviews r LEFT JOIN users u ON r.user_id = u.id WHERE r.vehicle_id=? ORDER BY r.created_at DESC LIMIT 5");
            $reviews->execute([$id]);
            $vehicle['reviews'] = $reviews->fetchAll(PDO::FETCH_ASSOC);
            $vp = $pdo->prepare("SELECT * FROM vehicle_pricing WHERE vehicle_id=?");
            $vp->execute([$id]);
            $vehicle['pricing'] = $vp->fetch(PDO::FETCH_ASSOC);
            $pkgs = $pdo->prepare("SELECT * FROM pricing_packages WHERE vehicle_id=? AND is_active=1 ORDER BY hours ASC");
            $pkgs->execute([$id]);
            $vehicle['packages'] = $pkgs->fetchAll(PDO::FETCH_ASSOC);
            $gal = $pdo->prepare("SELECT * FROM vehicle_gallery WHERE vehicle_id=? ORDER BY sort_order ASC");
            $gal->execute([$id]);
            $vehicle['gallery'] = $gal->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse($vehicle);

        case 'vehicles/gallery':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM vehicle_gallery WHERE vehicle_id=? ORDER BY sort_order ASC");
            $stmt->execute([$id]);
            jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));

        case 'hourly/packages':
            $vehicle_id = $_GET['vehicle_id'] ?? 0;
            $pkgs = $pdo->prepare("SELECT * FROM pricing_packages WHERE vehicle_id=? AND is_active=1 ORDER BY hours ASC");
            $pkgs->execute([$vehicle_id]);
            jsonResponse($pkgs->fetchAll(PDO::FETCH_ASSOC));

        case 'bookings/create':
            if (!$userId) jsonError('Not logged in', 401);
            $vehicle_id = $input['vehicle_id'] ?? 0;
            $pickup = $input['pickup_location'] ?? '';
            $pickup_lat = $input['pickup_lat'] ?? null;
            $pickup_lng = $input['pickup_lng'] ?? null;
            $drop = $input['drop_location'] ?? '';
            $drop_lat = $input['drop_lat'] ?? null;
            $drop_lng = $input['drop_lng'] ?? null;
            $distance = $input['distance_km'] ?? 0;
            $base_fare = $input['base_fare'] ?? 0;
            $tax = $input['tax'] ?? 0;
            $discount = $input['discount'] ?? 0;
            $total = $input['total_fare'] ?? 0;
            $date = $input['pickup_date'] ?? date('Y-m-d');
            $time = $input['pickup_time'] ?? '09:00';
            $pickup_date = $date . ' ' . $time . ':00';
            $return_date_raw = $input['return_date'] ?? $date;
            $return_time = $input['return_time'] ?? $time;
            $return_date = $return_date_raw . ' ' . $return_time . ':00';
            $duration_days = $input['duration_days'] ?? 1;
            $notes = $input['booking_notes'] ?? '';
            $pricing_type = $input['pricing_type'] ?? 'per_km';
            $package_id = $input['package_id'] ?? null;
            $trip_type = $input['trip_type'] ?? 'one_way';
            $stops = $input['stops'] ?? null;
            if (is_array($stops)) $stops = json_encode($stops);
            $pickup_city = $input['pickup_city'] ?? $pickup;
            $drop_city = $input['drop_city'] ?? $drop;
            $route_distance = $input['route_distance'] ?? $distance;
            $ref = 'TRP' . strtoupper(substr(uniqid(), -6));
            $stmt = $pdo->prepare("INSERT INTO bookings (user_id, vehicle_id, booking_ref, pickup_date, return_date, pickup_location, pickup_lat, pickup_lng, drop_location, drop_lat, drop_lng, distance_km, route_distance, duration_days, base_fare, tax, discount, total_fare, booking_notes, pricing_type, package_id, trip_type, stops, pickup_city, drop_city, pickup_time, return_time, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending')");
            $stmt->execute([$userId, $vehicle_id, $ref, $pickup_date, $return_date, $pickup, $pickup_lat, $pickup_lng, $drop, $drop_lat, $drop_lng, $distance, $route_distance, $duration_days, $base_fare, $tax, $discount, $total, $notes, $pricing_type, $package_id, $trip_type, $stops, $pickup_city, $drop_city, $time, $return_time]);
            $bookingId = $pdo->lastInsertId();
            $pdo->prepare("UPDATE vehicles SET total_bookings = total_bookings + 1 WHERE id=?")->execute([$vehicle_id]);
            jsonResponse(['success' => true, 'booking_ref' => $ref, 'id' => $bookingId]);

        case 'bookings/list':
            if (!$userId) jsonError('Not logged in', 401);
            $status = $_GET['status'] ?? '';
            $query = "SELECT b.*, v.name as vehicle_name, v.type as vehicle_type, v.image FROM bookings b LEFT JOIN vehicles v ON b.vehicle_id = v.id WHERE b.user_id=?";
            $params = [$userId];
            if ($status && $status !== 'all') { $query .= " AND b.status=?"; $params[] = $status; }
            $query .= " ORDER BY b.created_at DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($bookings as &$b) {
                $b['driver_assigned'] = !empty($b['driver_id']);
                if ($b['driver_assigned'] && $b['status'] === 'confirmed') {
                } else {
                    unset($b['driver_name'], $b['driver_phone']);
                }
                if ($b['pickup_lat'] && $b['drop_lat']) {
                    $b['navigation_url'] = 'https://www.google.com/maps/dir/?api=1&origin=' . $b['pickup_lat'] . ',' . $b['pickup_lng'] . '&destination=' . $b['drop_lat'] . ',' . $b['drop_lng'] . '&travelmode=driving';
                } else {
                    $b['navigation_url'] = null;
                }
            }
            jsonResponse($bookings);

        case 'bookings/detail':
            if (!$userId) jsonError('Not logged in', 401);
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT b.*, v.name as vehicle_name, v.type as vehicle_type, v.image, v.price_per_km, v.price_per_day FROM bookings b LEFT JOIN vehicles v ON b.vehicle_id = v.id WHERE b.id=? AND b.user_id=?");
            $stmt->execute([$id, $userId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$booking) jsonError('Booking not found', 404);
            $booking['driver_assigned'] = !empty($booking['driver_id']) && in_array($booking['status'], ['confirmed','ongoing','completed']);
            if ($booking['driver_assigned']) {
                $dSt = $pdo->prepare("SELECT vehicle_model, vehicle_number, rating FROM drivers WHERE id=?");
                $dSt->execute([$booking['driver_id']]);
                $dInfo = $dSt->fetch(PDO::FETCH_ASSOC);
                $booking['driver_vehicle'] = ($dInfo['vehicle_model'] ?? '') . ' · ' . ($dInfo['vehicle_number'] ?? '');
                $booking['driver_rating'] = $dInfo['rating'] ?? 5.0;
            } else {
                unset($booking['driver_name'], $booking['driver_phone'], $booking['driver_id']);
            }
            if ($booking['pickup_lat'] && $booking['drop_lat']) {
                $booking['navigation_url'] = 'https://www.google.com/maps/dir/?api=1&origin=' . $booking['pickup_lat'] . ',' . $booking['pickup_lng'] . '&destination=' . $booking['drop_lat'] . ',' . $booking['drop_lng'] . '&travelmode=driving';
            } else {
                $booking['navigation_url'] = null;
            }
            jsonResponse($booking);

        case 'sos/create':
            if (!$userId) jsonError('Not logged in', 401);
            $pdo->prepare("INSERT INTO sos_alerts (booking_id, user_id, alert_type, message, lat, lng) VALUES (?,?,?,?,?,?)")
                ->execute([$input['booking_id'] ?? null, $userId, $input['alert_type'] ?? 'emergency', $input['message'] ?? '', $input['lat'] ?? null, $input['lng'] ?? null]);
            jsonResponse(['success' => true, 'message' => 'SOS alert sent to admin']);

        case 'addons/list':
            $addons = $pdo->query("SELECT * FROM tour_addons WHERE is_active=1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse($addons);

        case 'upload':
            if (empty($_FILES['file'])) jsonError('No file uploaded');
            $file = $_FILES['file'];
            $type = $_GET['type'] ?? 'vehicles';
            $allowed = ['jpg','jpeg','png','gif','webp','pdf','doc','docx'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) jsonError('File type not allowed: ' . $ext);
            $subdirs = ['vehicles'=>'vehicles','tours'=>'tours','banners'=>'banners','drivers'=>'drivers','avatars'=>'avatars','gallery'=>'vehicles/gallery'];
            $subdir = $subdirs[$type] ?? 'vehicles';
            $targetDir = __DIR__ . '/../uploads/' . $subdir . '/';
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destPath = $targetDir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $destPath)) jsonError('Failed to save file', 500);
            $url = '/uploads/' . $subdir . '/' . $filename;
            jsonResponse(['success' => true, 'url' => $url, 'filename' => $filename]);

        case 'tours/list':
            $tours = $pdo->query("SELECT * FROM tour_packages WHERE is_active=1 AND tour_date >= date('now') ORDER BY tour_date ASC")->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse($tours);

        case 'tours/book':
            if (!$userId) jsonError('Not logged in', 401);
            $tour_id = $input['tour_id'] ?? 0;
            $persons = $input['persons'] ?? 1;
            $tour = $pdo->prepare("SELECT * FROM tour_packages WHERE id=? AND is_active=1");
            $tour->execute([$tour_id]);
            $t = $tour->fetch(PDO::FETCH_ASSOC);
            if (!$t) jsonError('Tour not found', 404);
            $total_amount = $t['price_per_person'] * $persons;
            $pdo->prepare("INSERT INTO tour_bookings (tour_id, user_id, persons, total_amount, payment_status, status) VALUES (?,?,?,?,'paid','confirmed')")
                ->execute([$tour_id, $userId, $persons, $total_amount]);
            $pdo->prepare("UPDATE tour_packages SET current_participants = current_participants + ? WHERE id=?")
                ->execute([$persons, $tour_id]);
            jsonResponse(['success' => true, 'message' => 'Tour booked successfully', 'total' => $total_amount]);

        case 'packages/list':
            $vehicle_id = $_GET['vehicle_id'] ?? 0;
            $pkgs = $pdo->prepare("SELECT * FROM pricing_packages WHERE vehicle_id=? AND is_active=1 ORDER BY hours ASC");
            $pkgs->execute([$vehicle_id]);
            jsonResponse($pkgs->fetchAll(PDO::FETCH_ASSOC));

        case 'categories':
            $cats = $pdo->query("SELECT * FROM vehicle_categories WHERE active=1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse($cats);

        case 'banners':
            $banners = $pdo->query("SELECT * FROM banners WHERE active=1 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse($banners);

        case 'profile':
            if (!$userId) jsonError('Not logged in', 401);
            $user = $pdo->prepare("SELECT * FROM users WHERE id=?");
            $user->execute([$userId]);
            jsonResponse($user->fetch(PDO::FETCH_ASSOC));

        case 'notifications/list':
            if (!$userId) jsonError('Not logged in', 401);
            $notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
            $notifs->execute([$userId]);
            jsonResponse($notifs->fetchAll(PDO::FETCH_ASSOC));

        case 'notifications/unread_count':
            if (!$userId) jsonError('Not logged in', 401);
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
            $cnt->execute([$userId]);
            jsonResponse(['count' => (int)$cnt->fetchColumn()]);

        case 'notifications/mark_read':
            if (!$userId) jsonError('Not logged in', 401);
            $id = $input['id'] ?? 0;
            if ($id) {
                $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$id, $userId]);
            } else {
                $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$userId]);
            }
            jsonResponse(['success' => true]);

        case 'notifications/create':
            $targetUserId = $input['user_id'] ?? 0;
            $title = $input['title'] ?? '';
            $message = $input['message'] ?? '';
            $type = $input['type'] ?? 'info';
            if (!$targetUserId || !$title) jsonError('Missing user_id or title');
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)")
                ->execute([$targetUserId, $title, $message, $type]);
            jsonResponse(['success' => true]);

        case 'pricing/calculate':
            $vehicle_id = $input['vehicle_id'] ?? 0;
            $distance = $input['distance_km'] ?? 0;
            $pricing_type = $input['pricing_type'] ?? 'per_km';
            $package_id = $input['package_id'] ?? null;
            $trip_type = $input['trip_type'] ?? 'one_way';
            $stmt = $pdo->prepare("SELECT v.id, v.name, v.price_per_km, v.price_per_day, vp.base_rate, vp.min_km, vp.extra_km_rate, vp.min_km_charge, vp.security_deposit, vp.cancellation_fee FROM vehicles v LEFT JOIN vehicle_pricing vp ON v.id = vp.vehicle_id WHERE v.id=?");
            $stmt->execute([$vehicle_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) jsonError('Vehicle not found', 404);
            $base_rate = $data['base_rate'] ?? 0;
            $min_km = $data['min_km'] ?? 300;
            $extra_km_rate = $data['extra_km_rate'] ?? $data['price_per_km'];
            $min_km_charge = $data['min_km_charge'] ?? 0;
            $result = [];
            if (($trip_type === 'hourly' && $package_id) || $pricing_type === 'package') {
                $pkg = $pdo->prepare("SELECT * FROM pricing_packages WHERE id=? AND vehicle_id=?");
                $pkg->execute([$package_id, $vehicle_id]);
                $p = $pkg->fetch(PDO::FETCH_ASSOC);
                if ($p) {
                    $extraKm = max(0, $distance - $p['km_limit']);
                    $extraFare = $extraKm * $extra_km_rate;
                    $fare = $p['price'] + $extraFare;
                    $result = [
                        'pricing_type' => 'package',
                        'trip_type' => 'hourly',
                        'package_name' => $p['name'],
                        'hours' => $p['hours'],
                        'km_limit' => $p['km_limit'],
                        'extra_km' => $extraKm,
                        'extra_km_rate' => $extra_km_rate,
                        'extra_fare' => round($extraFare, 2),
                        'package_price' => $p['price'],
                        'fare' => round($fare, 2),
                    ];
                }
            }
            if (!$result && $pricing_type === 'per_km') {
                if ($distance > 0) {
                    if ($distance < $min_km) {
                        $fare = max($base_rate, $min_km * $extra_km_rate);
                    } else {
                        $fare = $base_rate + ($distance - $min_km) * $extra_km_rate;
                    }
                } else {
                    $fare = $base_rate;
                }
                $result = [
                    'pricing_type' => 'per_km',
                    'trip_type' => $trip_type,
                    'base_rate' => $base_rate,
                    'min_km' => $min_km,
                    'extra_km_rate' => $extra_km_rate,
                    'distance_km' => $distance,
                    'fare' => round($fare, 2),
                    'tax' => round($fare * 0.05, 2),
                    'total' => round($fare * 1.05, 2),
                ];
            }
            if ($result) {
                $result['tax'] = round(($result['fare'] ?? 0) * 0.05, 2);
                $result['total'] = round(($result['fare'] ?? 0) * 1.05, 2);
            }
            jsonResponse($result);

        default:
            jsonError('Unknown action', 404);
    }
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
