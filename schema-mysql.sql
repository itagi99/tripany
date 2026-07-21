-- ============================================================
-- TripAny White-Label — MySQL Schema (for Hostinger/cPanel)
-- ============================================================

CREATE TABLE IF NOT EXISTS `vehicle_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) DEFAULT NULL,
  `icon` VARCHAR(50) DEFAULT 'bi bi-car-front',
  `image` VARCHAR(500) DEFAULT NULL,
  `sort_order` INT DEFAULT 0,
  `active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vehicles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT DEFAULT NULL,
  `name` VARCHAR(200) NOT NULL,
  `brand` VARCHAR(100) DEFAULT '',
  `model` VARCHAR(100) DEFAULT '',
  `year` INT DEFAULT 2024,
  `type` VARCHAR(50) DEFAULT 'Sedan',
  `fuel_type` VARCHAR(50) DEFAULT 'Petrol',
  `transmission` VARCHAR(50) DEFAULT 'Manual',
  `seats` INT DEFAULT 5,
  `bags` INT DEFAULT 2,
  `price_per_day` DECIMAL(10,2) DEFAULT 0,
  `price_per_km` DECIMAL(10,2) DEFAULT 0,
  `image` VARCHAR(500) DEFAULT '',
  `description` TEXT DEFAULT NULL,
  `features` TEXT DEFAULT NULL,
  `inclusions` TEXT DEFAULT NULL,
  `exclusions` TEXT DEFAULT NULL,
  `facilities` TEXT DEFAULT NULL,
  `terms` TEXT DEFAULT NULL,
  `cancellation_policy` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `is_featured` TINYINT(1) DEFAULT 0,
  `rating` DECIMAL(2,1) DEFAULT 0,
  `total_reviews` INT DEFAULT 0,
  `total_bookings` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `vehicle_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vehicle_pricing` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vehicle_id` INT DEFAULT NULL,
  `base_rate` DECIMAL(10,2) DEFAULT 0,
  `min_km` INT DEFAULT 300,
  `extra_km_rate` DECIMAL(10,2) DEFAULT 0,
  `min_km_charge` DECIMAL(10,2) DEFAULT 0,
  `security_deposit` DECIMAL(10,2) DEFAULT 0,
  `cancellation_fee` DECIMAL(10,2) DEFAULT 0,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vehicle_gallery` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vehicle_id` INT DEFAULT NULL,
  `image_url` VARCHAR(500) DEFAULT NULL,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pricing_packages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vehicle_id` INT DEFAULT NULL,
  `name` VARCHAR(200) DEFAULT '',
  `hours` INT DEFAULT 8,
  `km_limit` INT DEFAULT 80,
  `price` DECIMAL(10,2) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) DEFAULT 'User',
  `phone` VARCHAR(20) NOT NULL UNIQUE,
  `email` VARCHAR(200) DEFAULT NULL,
  `password_hash` VARCHAR(255) DEFAULT NULL,
  `otp_code` VARCHAR(10) DEFAULT NULL,
  `otp_expires` DATETIME DEFAULT NULL,
  `is_verified` TINYINT(1) DEFAULT 0,
  `avatar` VARCHAR(500) DEFAULT NULL,
  `wallet` DECIMAL(10,2) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `drivers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(200) DEFAULT NULL,
  `password_hash` VARCHAR(255) DEFAULT NULL,
  `license_number` VARCHAR(100) DEFAULT '',
  `vehicle_model` VARCHAR(200) DEFAULT '',
  `vehicle_number` VARCHAR(50) DEFAULT '',
  `status` VARCHAR(20) DEFAULT 'offline',
  `rating` DECIMAL(2,1) DEFAULT 5.0,
  `total_trips` INT DEFAULT 0,
  `lat` DECIMAL(10,7) DEFAULT NULL,
  `lng` DECIMAL(10,7) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `vehicle_id` INT DEFAULT NULL,
  `driver_id` INT DEFAULT NULL,
  `booking_ref` VARCHAR(20) DEFAULT NULL,
  `pickup_date` DATETIME DEFAULT NULL,
  `return_date` DATETIME DEFAULT NULL,
  `pickup_location` VARCHAR(500) DEFAULT '',
  `pickup_lat` DECIMAL(10,7) DEFAULT NULL,
  `pickup_lng` DECIMAL(10,7) DEFAULT NULL,
  `drop_location` VARCHAR(500) DEFAULT '',
  `drop_lat` DECIMAL(10,7) DEFAULT NULL,
  `drop_lng` DECIMAL(10,7) DEFAULT NULL,
  `distance_km` DECIMAL(10,2) DEFAULT 0,
  `route_distance` DECIMAL(10,2) DEFAULT NULL,
  `duration_days` INT DEFAULT 1,
  `base_fare` DECIMAL(10,2) DEFAULT 0,
  `tax` DECIMAL(10,2) DEFAULT 0,
  `discount` DECIMAL(10,2) DEFAULT 0,
  `total_fare` DECIMAL(10,2) DEFAULT 0,
  `status` VARCHAR(20) DEFAULT 'pending',
  `payment_status` VARCHAR(20) DEFAULT 'unpaid',
  `payment_method` VARCHAR(20) DEFAULT NULL,
  `coupon_code` VARCHAR(50) DEFAULT NULL,
  `pricing_type` VARCHAR(20) DEFAULT 'per_km',
  `package_id` INT DEFAULT NULL,
  `trip_type` VARCHAR(20) DEFAULT 'one_way',
  `stops` TEXT DEFAULT NULL,
  `pickup_city` VARCHAR(100) DEFAULT NULL,
  `drop_city` VARCHAR(100) DEFAULT NULL,
  `pickup_time` VARCHAR(10) DEFAULT NULL,
  `return_time` VARCHAR(10) DEFAULT NULL,
  `booking_notes` TEXT DEFAULT NULL,
  `rating` INT DEFAULT NULL,
  `review` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`driver_id`) REFERENCES `drivers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tour_packages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `destination` VARCHAR(200) DEFAULT '',
  `description` TEXT DEFAULT NULL,
  `image` VARCHAR(500) DEFAULT '',
  `tour_date` DATE DEFAULT NULL,
  `price_per_person` DECIMAL(10,2) DEFAULT 0,
  `total_participants` INT DEFAULT 20,
  `current_participants` INT DEFAULT 0,
  `vehicle_id` INT DEFAULT NULL,
  `whats_included` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tour_bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tour_id` INT DEFAULT NULL,
  `user_id` INT DEFAULT NULL,
  `persons` INT DEFAULT 1,
  `total_amount` DECIMAL(10,2) DEFAULT 0,
  `payment_status` VARCHAR(20) DEFAULT 'pending',
  `status` VARCHAR(20) DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`tour_id`) REFERENCES `tour_packages`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tour_addons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `icon` VARCHAR(10) DEFAULT '📦',
  `price` DECIMAL(10,2) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `coupons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `discount_type` VARCHAR(20) DEFAULT 'percentage',
  `discount_value` DECIMAL(10,2) DEFAULT 0,
  `min_fare` DECIMAL(10,2) DEFAULT 0,
  `max_discount` DECIMAL(10,2) DEFAULT 0,
  `usage_limit` INT DEFAULT 100,
  `used_count` INT DEFAULT 0,
  `valid_from` DATETIME DEFAULT NULL,
  `valid_until` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `banners` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) DEFAULT '',
  `image_url` VARCHAR(500) DEFAULT '',
  `link_url` VARCHAR(500) DEFAULT '',
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `offers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `image` VARCHAR(500) DEFAULT '',
  `discount_percent` INT DEFAULT 0,
  `code` VARCHAR(50) DEFAULT NULL,
  `valid_until` DATE DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sos_alerts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT DEFAULT NULL,
  `user_id` INT DEFAULT NULL,
  `driver_id` INT DEFAULT NULL,
  `alert_type` VARCHAR(50) DEFAULT 'emergency',
  `message` TEXT DEFAULT NULL,
  `lat` DECIMAL(10,7) DEFAULT NULL,
  `lng` DECIMAL(10,7) DEFAULT NULL,
  `status` VARCHAR(20) DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `title` VARCHAR(200) DEFAULT '',
  `message` TEXT DEFAULT NULL,
  `type` VARCHAR(20) DEFAULT 'info',
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vehicle_id` INT DEFAULT NULL,
  `user_id` INT DEFAULT NULL,
  `rating` INT DEFAULT 5,
  `comment` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `key_name` VARCHAR(100) NOT NULL UNIQUE,
  `value` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pickup_locations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `pincode` VARCHAR(10) DEFAULT NULL,
  `lat` DECIMAL(10,7) DEFAULT NULL,
  `lng` DECIMAL(10,7) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wishlist` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `vehicle_id` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `driver_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `driver_id` INT DEFAULT NULL,
  `document_type` VARCHAR(50) DEFAULT '',
  `document_url` VARCHAR(500) DEFAULT '',
  `verified` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`driver_id`) REFERENCES `drivers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
