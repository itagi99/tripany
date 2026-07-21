CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT,
    phone TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    avatar TEXT,
    otp_code TEXT,
    otp_expires DATETIME,
    is_verified INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vehicle_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    icon TEXT,
    image TEXT,
    active INTEGER DEFAULT 1,
    sort_order INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS vehicles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER,
    name TEXT NOT NULL,
    brand TEXT,
    model TEXT NOT NULL,
    year INTEGER,
    type TEXT NOT NULL,
    fuel_type TEXT DEFAULT 'Petrol',
    transmission TEXT DEFAULT 'Manual',
    seats INTEGER DEFAULT 4,
    bags INTEGER DEFAULT 2,
    price_per_day REAL NOT NULL,
    price_per_km REAL DEFAULT 0,
    image TEXT,
    description TEXT,
    features TEXT,
    is_active INTEGER DEFAULT 1,
    is_featured INTEGER DEFAULT 0,
    rating REAL DEFAULT 0,
    total_reviews INTEGER DEFAULT 0,
    total_bookings INTEGER DEFAULT 0,
    inclusions TEXT,
    exclusions TEXT,
    terms TEXT,
    cancellation_policy TEXT DEFAULT 'Free cancellation within 30 minutes of booking',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vehicle_gallery (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER NOT NULL,
    image_url TEXT NOT NULL,
    sort_order INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS vehicle_features (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER NOT NULL,
    feature_name TEXT NOT NULL,
    feature_icon TEXT
);

CREATE TABLE IF NOT EXISTS bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    vehicle_id INTEGER NOT NULL,
    driver_id INTEGER,
    booking_ref TEXT UNIQUE NOT NULL,
    pickup_date DATETIME NOT NULL,
    return_date DATETIME NOT NULL,
    pickup_location TEXT,
    pickup_lat REAL,
    pickup_lng REAL,
    drop_location TEXT,
    drop_lat REAL,
    drop_lng REAL,
    distance_km REAL DEFAULT 0,
    duration_days INTEGER DEFAULT 1,
    base_fare REAL NOT NULL,
    tax REAL DEFAULT 0,
    discount REAL DEFAULT 0,
    total_fare REAL NOT NULL,
    payment_method TEXT DEFAULT 'UPI',
    payment_status TEXT DEFAULT 'pending',
    status TEXT DEFAULT 'pending',
    coupon_id INTEGER,
    driver_name TEXT,
    driver_phone TEXT,
    rating INTEGER,
    review TEXT,
    booking_notes TEXT,
    pricing_type TEXT DEFAULT 'per_km',
    package_id INTEGER,
    trip_type TEXT DEFAULT 'one_way',
    stops TEXT,
    pickup_city TEXT,
    drop_city TEXT,
    route_distance REAL DEFAULT 0,
    pickup_time TEXT,
    return_time TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME
);

CREATE TABLE IF NOT EXISTS pickup_locations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    address TEXT,
    city TEXT,
    lat REAL,
    lng REAL,
    is_active INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS coupons (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    description TEXT,
    discount_percent REAL DEFAULT 0,
    discount_amount REAL DEFAULT 0,
    min_booking_amount REAL DEFAULT 0,
    max_uses INTEGER DEFAULT 100,
    used_count INTEGER DEFAULT 0,
    valid_from DATETIME,
    valid_to DATETIME,
    active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS banners (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    subtitle TEXT,
    image_url TEXT NOT NULL,
    link TEXT,
    active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    booking_id INTEGER,
    rating INTEGER NOT NULL,
    comment TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wishlist (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    vehicle_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, vehicle_id)
);

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    message TEXT,
    type TEXT DEFAULT 'info',
    is_read INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS drivers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT,
    phone TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    vehicle_type TEXT,
    vehicle_number TEXT,
    vehicle_model TEXT,
    license_number TEXT,
    avatar TEXT,
    status TEXT DEFAULT 'offline',
    rating REAL DEFAULT 5.0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key TEXT UNIQUE NOT NULL,
    setting_value TEXT
);

CREATE TABLE IF NOT EXISTS offers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    discount_percent REAL DEFAULT 0,
    valid_from DATE,
    valid_to DATE,
    active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS driver_documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    driver_id INTEGER NOT NULL,
    doc_type TEXT NOT NULL,
    doc_number TEXT,
    doc_file TEXT,
    is_verified INTEGER DEFAULT 0,
    expiry_date DATE,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS vehicle_pricing (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER NOT NULL,
    base_rate REAL NOT NULL DEFAULT 0,
    peak_hour_rate REAL DEFAULT 0,
    weekend_rate REAL DEFAULT 0,
    holiday_rate REAL DEFAULT 0,
    night_rate REAL DEFAULT 0,
    min_km REAL DEFAULT 0,
    free_km_per_day REAL DEFAULT 0,
    extra_km_rate REAL DEFAULT 0,
    security_deposit REAL DEFAULT 0,
    cancellation_fee REAL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pricing_packages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    hours REAL NOT NULL DEFAULT 0,
    km_limit REAL NOT NULL DEFAULT 0,
    price REAL NOT NULL DEFAULT 0,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sos_alerts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    booking_id INTEGER,
    user_id INTEGER,
    driver_id INTEGER,
    alert_type TEXT NOT NULL DEFAULT 'emergency',
    message TEXT,
    lat REAL,
    lng REAL,
    status TEXT DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tour_packages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    destination TEXT,
    tour_date DATE NOT NULL,
    price_per_person REAL NOT NULL DEFAULT 0,
    max_participants INTEGER DEFAULT 0,
    current_participants INTEGER DEFAULT 0,
    vehicle_type TEXT,
    includes TEXT,
    image TEXT,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tour_bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tour_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    persons INTEGER NOT NULL DEFAULT 1,
    total_amount REAL NOT NULL DEFAULT 0,
    payment_status TEXT DEFAULT 'pending',
    status TEXT DEFAULT 'confirmed',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tour_id) REFERENCES tour_packages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
