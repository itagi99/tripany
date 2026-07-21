<?php
function seed($pdo) {
    $existing = $pdo->query("SELECT COUNT(*) FROM vehicle_categories")->fetchColumn();
    if ($existing > 0) return;

    $userHash = password_hash('user123', PASSWORD_DEFAULT);
    $adminHash = password_hash('admin123', PASSWORD_DEFAULT);

    $pdo->exec("INSERT INTO users (name, email, phone, password_hash, is_verified) VALUES ('Admin', 'admin@tripany.com', '9999999999', '$adminHash', 1)");
    $pdo->exec("INSERT INTO users (name, email, phone, password_hash, is_verified) VALUES ('John Doe', 'john@test.com', '9876543210', '$userHash', 1)");
    $pdo->exec("INSERT INTO users (name, email, phone, password_hash, is_verified) VALUES ('Rahul Kumar', 'rahul@test.com', '9876543211', '$userHash', 1)");
    $pdo->exec("INSERT INTO users (name, email, phone, password_hash, is_verified) VALUES ('Priya Singh', 'priya@test.com', '9876543212', '$userHash', 1)");

    $cats = [
        ['Hatchback', '<i class="bi bi-car-front-fill"></i>', 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?w=200&h=200&fit=crop'],
        ['Sedan', '<i class="bi bi-car-front"></i>', 'https://images.unsplash.com/photo-1549317661-bd32c8ce0abb?w=200&h=200&fit=crop'],
        ['SUV', '<i class="bi bi-truck"></i>', 'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?w=200&h=200&fit=crop'],
        ['Bikes', '<i class="bi bi-bicycle"></i>', 'https://images.unsplash.com/photo-1558618666-fcd25c85f82e?w=200&h=200&fit=crop'],
        ['Scooter', '<i class="bi bi-motorcycle"></i>', 'https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=200&h=200&fit=crop'],
        ['Auto Rickshaw', '<i class="bi bi-person-standing"></i>', 'https://images.unsplash.com/photo-1570125909232-eb263c188f7e?w=200&h=200&fit=crop'],
        ['Mini Truck', '<i class="bi bi-truck"></i>', 'https://images.unsplash.com/photo-1601584115197-04ecc0da31d7?w=200&h=200&fit=crop'],
        ['Truck', '<i class="bi bi-truck-front"></i>', 'https://images.unsplash.com/photo-1605618826452-f8ced5c50aaa?w=200&h=200&fit=crop'],
        ['Luxury Cars', '<i class="bi bi-gem"></i>', 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=200&h=200&fit=crop'],
        ['Electric Cars', '<i class="bi bi-lightning-charge"></i>', 'https://images.unsplash.com/photo-1593941707882-a5bba14938c7?w=200&h=200&fit=crop'],
        ['Tempo Traveller', '<i class="bi bi-bus-front"></i>', 'https://images.unsplash.com/photo-1570125909232-eb263c188f7e?w=200&h=200&fit=crop'],
        ['Bus', '<i class="bi bi-bus-front-fill"></i>', 'https://images.unsplash.com/photo-1557223562-6c77ef16210f?w=200&h=200&fit=crop'],
    ];
    $stmt = $pdo->prepare("INSERT INTO vehicle_categories (name, icon, image, sort_order) VALUES (?,?,?,?)");
    foreach ($cats as $i => $c) {
        $stmt->execute([$c[0], $c[1], $c[2], $i]);
    }

    $vehicles = [
        [1, 'Maruti Swift', 'Maruti', 'Swift', 2023, 'Hatchback', 'Petrol', 'Manual', 5, 2, 899, 12,
         'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?w=600&h=400&fit=crop',
         'Maruti Swift is one of India\'s most popular hatchbacks. Known for its smooth ride, excellent fuel efficiency, and compact design, it\'s perfect for city commuting.',
         'AC|Power Windows|Bluetooth|USB|Airbags|ABS|Central Locking|Power Steering', 1, 1, 4.5, 120],

        [2, 'Hyundai Verna', 'Hyundai', 'Verna 2024', 2024, 'Sedan', 'Petrol', 'Automatic', 5, 3, 1299, 14,
         'https://images.unsplash.com/photo-1549317661-bd32c8ce0abb?w=600&h=400&fit=crop',
         'The new Hyundai Verna is a premium sedan packed with advanced features, a refined engine, and a luxurious interior.',
         'AC|Power Windows|Sunroof|Bluetooth|Cruise Control|Airbags|ABS|Camera|Wireless Charging', 1, 1, 4.6, 89],

        [3, 'Tata Nexon', 'Tata', 'Nexon 2024', 2024, 'SUV', 'Diesel', 'Automatic', 5, 3, 1599, 16,
         'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?w=600&h=400&fit=crop',
         'Tata Nexon is a compact SUV with a 5-star Global NCAP safety rating. Features a powerful diesel engine with smooth automatic transmission.',
         'AC|Power Windows|Sunroof|Bluetooth|Cruise Control|Airbags|ABS|Camera|Hill Assist|Ventilated Seats', 1, 1, 4.7, 156],

        [4, 'Toyota Innova Crysta', 'Toyota', 'Innova Crysta', 2023, 'SUV', 'Diesel', 'Automatic', 7, 4, 2499, 18,
         'https://images.unsplash.com/photo-1619767886558-efdc259cde1a?w=600&h=400&fit=crop',
         'The Toyota Innova Crysta is the ultimate family MPV. Spacious, comfortable, and reliable for long road trips.',
         'AC|Power Windows|Sunroof|Bluetooth|Cruise Control|Airbags|ABS|Camera|Captain Seats|Alloy Wheels', 1, 1, 4.8, 203],

        [5, 'Honda Activa 6G', 'Honda', 'Activa 6G', 2024, 'Scooter', 'Petrol', 'Automatic', 2, 1, 499, 8,
         'https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=600&h=400&fit=crop',
         'Honda Activa 6G is India\'s best-selling scooter. Smooth ride, great mileage, and easy to maneuver in traffic.',
         'Digital Meter|USB Charging|LED Headlamp|Combi Brake|Engine Start-Stop', 1, 1, 4.3, 312],

        [6, 'Royal Enfield Classic 350', 'Royal Enfield', 'Classic 350', 2024, 'Bike', 'Petrol', 'Manual', 2, 1, 799, 10,
         'https://images.unsplash.com/photo-1558618666-fcd25c85f82e?w=600&h=400&fit=crop',
         'Royal Enfield Classic 350 — an iconic motorcycle with timeless design. Perfect for weekend rides and long highway trips.',
         'ABS|Disc Brake|Electric Start|USB|Dual Channel ABS|Halogen Headlamp', 1, 1, 4.7, 187],

        [7, 'Bajaj RE Auto', 'Bajaj', 'RE Auto', 2023, 'Auto Rickshaw', 'CNG', 'Manual', 3, 2, 699, 10,
         'https://images.unsplash.com/photo-1570125909232-eb263c188f7e?w=600&h=400&fit=crop',
         'Bajaj RE Auto — India\'s most trusted three-wheeler. Ideal for short city commutes and goods transport.',
         'CNG|Good Mileage|Spacious Cabin|Easy Maintenance', 1, 0, 4.1, 98],

        [8, 'Tata Ace Gold', 'Tata', 'Ace Gold', 2023, 'Mini Truck', 'Diesel', 'Manual', 2, 5, 899, 12,
         'https://images.unsplash.com/photo-1601584115197-04ecc0da31d7?w=600&h=400&fit=crop',
         'Tata Ace Gold — India\'s chhota commercial vehicle. Perfect for intra-city goods delivery with excellent payload.',
         'Diesel|Good Payload|AC Cabin|Power Steering|Music System', 1, 1, 4.4, 67],

        [9, 'Mahindra Bolero Pickup', 'Mahindra', 'Bolero Pickup', 2023, 'Mini Truck', 'Diesel', 'Manual', 2, 6, 1299, 14,
         'https://images.unsplash.com/photo-1605618826452-f8ced5c50aaa?w=600&h=400&fit=crop',
         'Mahindra Bolero Pickup — a robust and reliable pickup truck built for heavy-duty commercial use.',
         'Diesel|4WD Available|Power Steering|High Ground Clearance|Steel Bumper', 1, 0, 4.5, 45],

        [10, 'Mercedes-Benz E-Class', 'Mercedes', 'E-Class 2024', 2024, 'Luxury Cars', 'Petrol', 'Automatic', 5, 3, 4999, 25,
         'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=600&h=400&fit=crop',
         'Mercedes-Benz E-Class — the epitome of luxury. Enjoy premium comfort, cutting-edge tech, and an unparalleled driving experience.',
         'AC|Leather Seats|Sunroof|Bluetooth|Cruise Control|Airbags|ABS|Camera|Massage Seats|Ambient Lighting|Burmester Sound', 1, 1, 4.9, 34],

        [11, 'Tata Nexon EV', 'Tata', 'Nexon EV Max', 2024, 'Electric Cars', 'Electric', 'Automatic', 5, 3, 1899, 0,
         'https://images.unsplash.com/photo-1593941707882-a5bba14938c7?w=600&h=400&fit=crop',
         'Tata Nexon EV — India\'s best-selling electric SUV. 300km range, zero emissions, and loaded with features.',
         'AC|Power Windows|Sunroof|Bluetooth|Cruise Control|Airbags|ABS|Camera|Fast Charging|i-Pedal', 1, 1, 4.6, 78],

        [12, 'Force Traveller 26', 'Force', 'Traveller 26', 2023, 'Tempo Traveller', 'Diesel', 'Manual', 12, 6, 3499, 20,
         'https://images.unsplash.com/photo-1570125909232-eb263c188f7e?w=600&h=400&fit=crop',
         'Force Traveller — the perfect tempo for group trips and family outings. Spacious seating for up to 12 passengers.',
         'AC|Push Back Seats|Music System|First Aid|GPS|Curtains|Charging Points', 1, 0, 4.3, 56],

        [13, 'Maruti Dzire Tour', 'Maruti', 'Dzire Tour S', 2023, 'Sedan', 'CNG', 'Manual', 5, 3, 999, 12,
         'https://images.unsplash.com/photo-1549317661-bd32c8ce0abb?w=600&h=400&fit=crop',
         'Maruti Dzire Tour — an economical CNG sedan ideal for daily commutes and fleet operations.',
         'AC|Power Windows|Bluetooth|USB|Central Locking|CNG Certified', 1, 0, 4.2, 145],

        [14, 'Kia Seltos', 'Kia', 'Seltos 2024', 2024, 'SUV', 'Diesel', 'Automatic', 5, 3, 1799, 16,
         'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?w=600&h=400&fit=crop',
         'Kia Seltos — a feature-loaded compact SUV with a premium cabin and a powerful diesel engine.',
         'AC|Panoramic Sunroof|Bluetooth|Cruise Control|Airbags|ABS|Camera|Ventilated Seats|BOSE Sound', 1, 1, 4.6, 112],

        [15, 'Hero Splendor Plus', 'Hero', 'Splendor Plus', 2024, 'Bike', 'Petrol', 'Manual', 2, 1, 399, 6,
         'https://images.unsplash.com/photo-1558618666-fcd25c85f82e?w=600&h=400&fit=crop',
         'Hero Splendor Plus — India\'s bestselling motorcycle. Reliable, fuel-efficient, and built to last.',
         'ABS|Electric Start|USB|i3S Technology|Tubeless Tyres|Side Stand Engine Cut-off', 1, 0, 4.4, 234],

        [16, 'Ford EcoSport', 'Ford', 'EcoSport', 2023, 'SUV', 'Petrol', 'Automatic', 5, 3, 1399, 14,
         'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?w=600&h=400&fit=crop',
         'Ford EcoSport — a compact SUV with sporty handling and a peppy turbo engine.',
         'AC|Power Windows|Sunroof|Bluetooth|Cruise Control|Airbags|ABS|Camera|SYNC3', 1, 0, 4.4, 78],

        [17, 'Mahindra Thar', 'Mahindra', 'Thar 4x4', 2024, 'SUV', 'Diesel', 'Manual', 4, 2, 2199, 18,
         'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?w=600&h=400&fit=crop',
         'Mahindra Thar — the iconic off-roader. Unmatched capability on any terrain with a bold design.',
         '4WD|AC|Bluetooth|Airbags|ABS|Camera|Hard Top|Convertible|Touchscreen', 1, 1, 4.8, 92],

        [18, 'TVS Jupiter', 'TVS', 'Jupiter 125', 2024, 'Scooter', 'Petrol', 'Automatic', 2, 1, 449, 7,
         'https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=600&h=400&fit=crop',
         'TVS Jupiter 125 — the scooter with best-in-class storage and a smooth CVT transmission.',
         'Digital Meter|USB Charging|LED Headlamp|Under Seat Storage|Eco Mode', 1, 0, 4.2, 189],
    ];
    $stmt = $pdo->prepare("INSERT INTO vehicles (category_id, name, brand, model, year, type, fuel_type, transmission, seats, bags, price_per_day, price_per_km, image, description, features, is_active, is_featured, rating, total_bookings) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($vehicles as $v) {
        $stmt->execute($v);
    }

    $banners = [
        ['Weekend Special Offer', 'Get 20% off on all SUV bookings this weekend!', 'https://images.unsplash.com/photo-1449965408869-ebd3fee1f5be?w=800&h=400&fit=crop'],
        ['Premium Cars Collection', 'Rent luxury cars at the best prices in Delhi NCR', 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800&h=400&fit=crop'],
        ['Bikes Starting ₹399/day', 'Explore the city on two wheels — hassle free', 'https://images.unsplash.com/photo-1558618666-fcd25c85f82e?w=800&h=400&fit=crop'],
    ];
    $stmt = $pdo->prepare("INSERT INTO banners (title, subtitle, image_url, active) VALUES (?,?,?,1)");
    foreach ($banners as $b) {
        $stmt->execute([$b[0], $b[1], $b[2]]);
    }

    $coupons = [
        ['WELCOME20', 'Get 20% off on your first booking', 20, 0, 500, 500, "datetime('now')", "datetime('now', '+30 days')"],
        ['WEEKEND50', 'Flat ₹50 off on weekend bookings', 0, 50, 1000, 200, "datetime('now')", "datetime('now', '+30 days')"],
        ['TRIP30', '30% off on bookings above ₹2000', 30, 0, 2000, 100, "datetime('now')", "datetime('now', '+15 days')"],
        ['SAVE100', 'Flat ₹100 off on bookings above ₹2000', 0, 100, 2000, 100, "datetime('now')", "datetime('now', '+60 days')"],
    ];
    $stmt = $pdo->prepare("INSERT INTO coupons (code, description, discount_percent, discount_amount, min_booking_amount, max_uses, valid_from, valid_to) VALUES (?,?,?,?,?,?,?,?)");
    foreach ($coupons as $c) {
        $stmt->execute($c);
    }

    $locations = [
        ['Connaught Place', 'Block A, Connaught Place, New Delhi', 'New Delhi', 28.6315, 77.2167],
        ['Noida Sector 62', 'A-Block, Sector 62, Noida', 'Noida', 28.6280, 77.3640],
        ['Gurgaon Cyber City', 'DLF Phase 2, Cyber City, Gurgaon', 'Gurgaon', 28.4949, 77.0883],
        ['Dwarka Sector 21', 'Sector 21, Dwarka, New Delhi', 'New Delhi', 28.5520, 77.0584],
        ['Faridabad NIT', 'NIT Faridabad, Haryana', 'Faridabad', 28.4080, 77.3178],
        ['Jaipur Station', 'Near Railway Station, Jaipur', 'Jaipur', 26.9200, 75.7873],
    ];
    $stmt = $pdo->prepare("INSERT INTO pickup_locations (name, address, city, lat, lng) VALUES (?,?,?,?,?)");
    foreach ($locations as $l) {
        $stmt->execute($l);
    }

    $reviews = [
        [1, 2, 'Great car for city driving. Very smooth and comfortable. Would definitely rent again!', 5],
        [1, 3, 'Good condition, well maintained. The AC works perfectly.', 4],
        [2, 2, 'Excellent sedan, loved the features. The sunroof is a bonus!', 5],
        [3, 3, 'Best SUV in this price range. Safe and powerful.', 5],
        [4, 2, 'Perfect for family trips. Spacious and comfortable for 7 people.', 5],
        [6, 3, 'Amazing bike, great for weekend rides. Classic feel!', 5],
        [10, 2, 'Ultimate luxury experience. Worth every penny for special occasions.', 5],
        [11, 3, 'Future is electric! Smooth ride, no noise, great range.', 4],
        [14, 2, 'Loaded with features. The panoramic sunroof is amazing.', 5],
        [17, 3, 'Built like a tank! Took it off-road and it handled everything.', 5],
    ];
    $stmt = $pdo->prepare("INSERT INTO reviews (vehicle_id, user_id, comment, rating) VALUES (?,?,?,?)");
    foreach ($reviews as $r) {
        $stmt->execute($r);
    }

    $pdo->exec("UPDATE vehicles SET rating = (SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.vehicle_id = vehicles.id), total_reviews = (SELECT COUNT(*) FROM reviews r WHERE r.vehicle_id = vehicles.id) WHERE id IN (SELECT DISTINCT vehicle_id FROM reviews)");

    $driverHash = password_hash('driver123', PASSWORD_DEFAULT);
    $drivers = [
        ['Amit Driver', '8765432100', $driverHash, 'Sedan', 'DL-01-AB-1234', 'Maruti Swift', 'online', 4.8],
        ['Suresh Rider', '8765432101', $driverHash, 'Bike', 'DL-02-CD-5678', 'Honda Activa', 'online', 4.5],
        ['Rajesh Verma', '8765432103', $driverHash, 'SUV', 'DL-04-GH-3456', 'Tata Nexon', 'online', 4.7],
    ];
    $stmt = $pdo->prepare("INSERT INTO drivers (name, phone, password_hash, vehicle_type, vehicle_number, vehicle_model, status, rating) VALUES (?,?,?,?,?,?,?,?)");
    foreach ($drivers as $d) {
        $stmt->execute($d);
    }

    $pricing = [
        [1, 350, 0, 0, 0, 0, 300, 0, 12, 500, 0, 350, 'Standard'],
        [3, 500, 0, 0, 0, 0, 300, 0, 16, 1000, 0, 500, 'Standard'],
        [4, 800, 0, 0, 0, 0, 300, 0, 18, 1500, 0, 800, 'Standard'],
        [10, 1500, 0, 0, 0, 0, 300, 0, 25, 3000, 0, 1500, 'Standard'],
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO vehicle_pricing (vehicle_id, base_rate, peak_hour_rate, weekend_rate, holiday_rate, night_rate, min_km, free_km_per_day, extra_km_rate, security_deposit, cancellation_fee, min_km_charge, name) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($pricing as $p) {
        $stmt->execute($p);
    }

    $packages = [
        [1, 'City Local 4hr', 4, 40, 800],
        [1, 'City Local 8hr', 8, 80, 1500],
        [3, 'SUV Local 4hr', 4, 40, 1200],
        [3, 'SUV Local 8hr', 8, 80, 2200],
        [4, 'Family 4hr', 4, 40, 1800],
        [4, 'Family 8hr', 8, 80, 3200],
        [10, 'Luxury 4hr', 4, 40, 3500],
        [10, 'Luxury 8hr', 8, 80, 6500],
    ];
    $stmt = $pdo->prepare("INSERT INTO pricing_packages (vehicle_id, name, hours, km_limit, price) VALUES (?,?,?,?,?)");
    foreach ($packages as $p) {
        $stmt->execute($p);
    }

    $tours = [
        ['Amboli Falls Getaway', 'Join us for a scenic trip to Amboli Falls. Transport, breakfast, and lunch included.', 'Amboli Falls', '2026-07-21', 499, 30, 0, 'SUV/Tempo', 'Breakfast, Lunch, Entry Fees, Guide'],
        ['Goa Beach Trip', 'Weekend getaway to Goa beaches. Includes hotel stay, transport, and sightseeing.', 'Goa', '2026-08-15', 1499, 20, 0, 'Tempo Traveller', 'Hotel, Breakfast, Dinner, Sightseeing'],
        ['Mahabaleshwar Hill Station', 'Explore the lush green hills of Mahabaleshwar. One day trip with lunch.', 'Mahabaleshwar', '2026-07-28', 699, 25, 0, 'SUV', 'Lunch, Entry Fees, Guide'],
    ];
    $stmt = $pdo->prepare("INSERT INTO tour_packages (title, description, destination, tour_date, price_per_person, max_participants, current_participants, vehicle_type, includes) VALUES (?,?,?,?,?,?,?,?,?)");
    foreach ($tours as $t) {
        $stmt->execute($t);
    }
}
