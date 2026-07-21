<?php
date_default_timezone_set('Asia/Kolkata');

$dbPath = __DIR__ . '/vehigo.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA journal_mode=WAL");
    $pdo->exec("PRAGMA foreign_keys=ON");
} catch (PDOException $e) {
    die("Database connection error.");
}

function initDB($pdo) {
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schema);
}

function migrateDB($pdo) {
    $cols = [
        "ALTER TABLE bookings ADD COLUMN booking_notes TEXT",
        "ALTER TABLE bookings ADD COLUMN pricing_type TEXT DEFAULT 'per_km'",
        "ALTER TABLE bookings ADD COLUMN package_id INTEGER",
        "ALTER TABLE bookings ADD COLUMN trip_type TEXT DEFAULT 'one_way'",
        "ALTER TABLE bookings ADD COLUMN stops TEXT",
        "ALTER TABLE bookings ADD COLUMN pickup_city TEXT",
        "ALTER TABLE bookings ADD COLUMN drop_city TEXT",
        "ALTER TABLE bookings ADD COLUMN route_distance REAL DEFAULT 0",
        "ALTER TABLE bookings ADD COLUMN pickup_time TEXT",
        "ALTER TABLE bookings ADD COLUMN return_time TEXT",
        "ALTER TABLE vehicle_pricing ADD COLUMN min_km_charge REAL DEFAULT 0",
        "ALTER TABLE vehicle_pricing ADD COLUMN name TEXT",
        "ALTER TABLE vehicles ADD COLUMN inclusions TEXT",
        "ALTER TABLE vehicles ADD COLUMN exclusions TEXT",
        "ALTER TABLE vehicles ADD COLUMN terms TEXT",
        "ALTER TABLE vehicles ADD COLUMN cancellation_policy TEXT DEFAULT 'Free cancellation within 30 minutes of booking'",
        "ALTER TABLE users ADD COLUMN api_token TEXT",
        "ALTER TABLE drivers ADD COLUMN api_token TEXT",
        "ALTER TABLE vehicles ADD COLUMN facilities TEXT",
        "CREATE TABLE IF NOT EXISTS tour_addons (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, description TEXT, icon TEXT DEFAULT '📦', price REAL NOT NULL DEFAULT 0, is_active INTEGER DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    ];
    foreach ($cols as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) {}
    }
    // Seed default addons if table is empty
    $cnt = $pdo->query("SELECT COUNT(*) FROM tour_addons")->fetchColumn();
    if ($cnt == 0) {
        $defaultAddons = [
            ['Water Bottle', 'Packaged drinking water (1L)', '💧', 20],
            ['Travel Kit', 'Neck pillow, eye mask, ear plugs', '🧳', 99],
            ['Bed Kit', 'Blanket, pillow, sheet set', '🛏️', 149],
            ['First Aid Kit', 'Basic medical supplies', '🩹', 49],
            ['Snack Pack', 'Chips, biscuits, chocolates', '🍿', 75],
            ['Camera Mount', 'GoPro/phone mount', '📸', 59],
        ];
        $stmt = $pdo->prepare("INSERT INTO tour_addons (name, description, icon, price) VALUES (?,?,?,?)");
        foreach ($defaultAddons as $a) $stmt->execute($a);
    }
}

function seedDB($pdo) {
    require_once __DIR__ . '/seed.php';
    seed($pdo);
}

try {
    $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' LIMIT 1");
    $count = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn();
    if ($count < 5) {
        initDB($pdo);
    }
    migrateDB($pdo);
    if ($count < 5) {
        seedDB($pdo);
    }
} catch (Exception $e) {
    initDB($pdo);
    migrateDB($pdo);
    seedDB($pdo);
}

$pdo->exec("PRAGMA foreign_keys=ON");
