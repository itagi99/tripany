<?php
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isDriverLoggedIn() {
    return isset($_SESSION['driver_id']);
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function requireLogin() {
    if (!isLoggedIn()) { header("Location: login.php"); exit; }
}

function requireDriverLogin() {
    if (!isDriverLoggedIn()) { header("Location: login.php"); exit; }
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) { header("Location: login.php"); exit; }
}

function vehicleIcon($type) {
    return match($type) { 'car' => '🚗', 'bike' => '🏍️', 'auto' => '🛺', default => '🚗' };
}

function statusBadge($status) {
    $classes = [
        'pending' => 'warning', 'accepted' => 'info', 'ongoing' => 'info',
        'completed' => 'success', 'cancelled' => 'danger',
        'online' => 'success', 'offline' => 'danger', 'busy' => 'warning',
    ];
    $cls = $classes[$status] ?? 'secondary';
    return '<span class="badge badge-' . $cls . '">' . ucfirst($status) . '</span>';
}

function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'Just now';
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
