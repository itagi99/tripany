<?php
// =============================================================
// Brand Configuration — loaded by all PHP pages
// Edit brand.config.json then run: node scripts/setup.js
// =============================================================

$BRAND = [
  'name' => 'TripAny',
  'short_name' => 'TripAny',
  'tagline' => 'Premium Vehicle Rental',
  'description' => 'Premium Vehicle Rental & Travel Booking Platform',
  'logo_text' => 'T',
  'copyright' => '© 2024 TripAny. All rights reserved.',
  'year' => '2024',

  // Colors
  'primary_color' => '#38BDF8',
  'primary_light' => '#7DD3FC',
  'primary_dark' => '#0EA5E9',
  'secondary_color' => '#BAE6FD',
  'success_color' => '#22C55E',
  'warning_color' => '#F59E0B',
  'danger_color' => '#EF4444',

  // Admin
  'admin_username' => 'admin',
  'admin_password' => 'admin123',

  // Contact
  'whatsapp' => '919876543210',
  'support_email' => 'support@tripany.com',
  'phone' => '+91 9876543210',

  // Maps
  'maps_api_key' => 'AIzaSyBg36zNfvaFGbYsfz5FqN0yIKf5tEY3BBQ',

  // Defaults
  'default_location' => 'Kittur',
  'default_pincode' => '591115',
  'test_user_name' => 'John Doe',
  'test_user_phone' => '9876543210',
  'test_user_password' => 'user123',

  // SEO
  'seo_title' => 'TripAny — Premium Vehicle Rental',
  'seo_description' => 'Book cars, bikes, and vehicles for rent. Safe, fast, and affordable.',
];

// Helper: Inline CSS gradient
function brandGradient() {
  global $BRAND;
  return 'linear-gradient(135deg, ' . $BRAND['primary_color'] . ', ' . $BRAND['primary_light'] . ')';
}

// Helper: Page title
function brandTitle($page = '') {
  global $BRAND;
  return $page ? $page . ' - ' . $BRAND['name'] : $BRAND['name'];
}
