const express = require('express');
const cors = require('cors');
const path = require('path');
const Database = require('better-sqlite3');

const app = express();
const PORT = process.env.PORT || 3000;
const DB_PATH = path.join(__dirname, '..', 'vehigo-php', 'vehigo.db');

const db = new Database(DB_PATH);
db.pragma('journal_mode = WAL');
db.pragma('foreign_keys = ON');

app.use(cors());
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

// Static files
app.use('/admin', express.static(path.join(__dirname, '..', 'vehigo-php', 'admin')));
app.use('/uploads', express.static(path.join(__dirname, '..', 'vehigo-php', 'uploads')));
app.use('/mobile', express.static(path.join(__dirname, '..', 'vehigo-php', 'mobile')));
app.use('/assets', express.static(path.join(__dirname, '..', 'vehigo-php', 'mobile', 'assets')));

// API Routes
function jsonRes(res, data, status = 200) {
  res.status(status).json(data);
}
function jsonErr(res, msg, status = 400) {
  res.status(status).json({ success: false, error: msg });
}

// Auth
app.post('/api/index.php', (req, res) => {
  const action = req.query.action || req.body.action || '';
  handleApi(req, res, action);
});
app.get('/api/index.php', (req, res) => {
  const action = req.query.action || '';
  handleApi(req, res, action);
});

function handleApi(req, res, action) {
  const input = { ...req.query, ...req.body };
  try {
    switch (action) {
      case 'auth/login': {
        const phone = input.phone || '';
        if (phone.length < 10) return jsonErr(res, 'Invalid phone number');
        const otp = String(Math.floor(100000 + Math.random() * 900000));
        req.session = req.session || {};
        req.session.otp = otp;
        req.session.otp_phone = phone;
        return jsonRes(res, { success: true, message: 'OTP sent', otp });
      }
      case 'auth/verify': {
        const otp = input.otp || '';
        const phone = input.phone || (req.session && req.session.otp_phone) || '';
        if (otp !== (req.session && req.session.otp)) return jsonErr(res, 'Invalid OTP', 401);
        delete (req.session && req.session.otp);
        let user = db.prepare('SELECT * FROM users WHERE phone=?').get(phone);
        if (!user) {
          db.prepare("INSERT INTO users (name, phone, password_hash, is_verified) VALUES (?, ?, ?, 1)").run('User', phone, 'hashed');
          user = db.prepare("SELECT * FROM users WHERE phone=?").get(phone);
        }
        req.userId = user.id;
        return jsonRes(res, { success: true, user });
      }
      case 'vehicles/list': {
        const type = req.query.type || '';
        let query = "SELECT v.*, vc.name as category_name FROM vehicles v LEFT JOIN vehicle_categories vc ON v.category_id = vc.id WHERE v.is_active=1";
        const params = [];
        if (type) { query += " AND v.type=?"; params.push(type); }
        query += " ORDER BY v.is_featured DESC, v.rating DESC LIMIT 20";
        const vehicles = db.prepare(query).all(...params);
        return jsonRes(res, vehicles);
      }
      case 'vehicles/detail': {
        const id = parseInt(input.id) || 0;
        const vehicle = db.prepare("SELECT v.*, vc.name as category_name FROM vehicles v LEFT JOIN vehicle_categories vc ON v.category_id = vc.id WHERE v.id=?").get(id);
        if (!vehicle) return jsonErr(res, 'Vehicle not found', 404);
        vehicle.reviews = db.prepare("SELECT r.*, u.name as user_name FROM reviews r LEFT JOIN users u ON r.user_id = u.id WHERE r.vehicle_id=? ORDER BY r.created_at DESC LIMIT 5").all(id);
        vehicle.pricing = db.prepare("SELECT * FROM vehicle_pricing WHERE vehicle_id=?").get(id) || {};
        vehicle.packages = db.prepare("SELECT * FROM pricing_packages WHERE vehicle_id=? AND is_active=1 ORDER BY hours ASC").all(id);
        vehicle.gallery = db.prepare("SELECT * FROM vehicle_gallery WHERE vehicle_id=? ORDER BY sort_order ASC").all(id);
        return jsonRes(res, vehicle);
      }
      case 'addons/list': {
        const addons = db.prepare("SELECT * FROM tour_addons WHERE is_active=1 ORDER BY name ASC").all();
        return jsonRes(res, addons);
      }
      case 'tours/list': {
        const tours = db.prepare("SELECT * FROM tour_packages WHERE is_active=1 AND tour_date >= date('now') ORDER BY tour_date ASC").all();
        return jsonRes(res, tours);
      }
      case 'tours/book': {
        const tour_id = parseInt(input.tour_id) || 0;
        const persons = parseInt(input.persons) || 1;
        const tour = db.prepare('SELECT * FROM tour_packages WHERE id=? AND is_active=1').get(tour_id);
        if (!tour) return jsonErr(res, 'Tour not found', 404);
        const total_amount = tour.price_per_person * persons;
        db.prepare("INSERT INTO tour_bookings (tour_id, user_id, persons, total_amount, payment_status, status) VALUES (?,?,?,?,'paid','confirmed')").run(tour_id, req.userId || 1, persons, total_amount);
        db.prepare("UPDATE tour_packages SET current_participants = current_participants + ? WHERE id=?").run(persons, tour_id);
        return jsonRes(res, { success: true, message: 'Tour booked successfully', total: total_amount });
      }
      case 'bookings/create': {
        const vehicle_id = parseInt(input.vehicle_id) || 0;
        const pickup = input.pickup_location || '';
        const drop = input.drop_location || '';
        const distance = parseFloat(input.distance_km) || 0;
        const base_fare = parseFloat(input.base_fare) || 0;
        const tax = parseFloat(input.tax) || 0;
        const discount = parseFloat(input.discount) || 0;
        const total = parseFloat(input.total_fare) || 0;
        const date = input.pickup_date || new Date().toISOString().split('T')[0];
        const time = input.pickup_time || '09:00';
        const pickup_date = date + ' ' + time + ':00';
        const trip_type = input.trip_type || 'one_way';
        const ref = 'TRP' + Math.random().toString(36).substring(2, 8).toUpperCase();
        const p = db.prepare(`INSERT INTO bookings (user_id, vehicle_id, booking_ref, pickup_date, return_date, pickup_location, pickup_lat, pickup_lng, drop_location, drop_lat, drop_lng, distance_km, base_fare, tax, discount, total_fare, trip_type, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending')`);
        const result = p.run(req.userId || 1, vehicle_id, ref, pickup_date, pickup_date, pickup, input.pickup_lat || null, input.pickup_lng || null, drop, input.drop_lat || null, input.drop_lng || null, distance, base_fare, tax, discount, total, trip_type);
        db.prepare("UPDATE vehicles SET total_bookings = total_bookings + 1 WHERE id=?").run(vehicle_id);
        return jsonRes(res, { success: true, booking_ref: ref, id: result.lastInsertRowid });
      }
      case 'bookings/list': {
        const status = req.query.status || '';
        let query = "SELECT b.*, v.name as vehicle_name, v.type as vehicle_type, v.image FROM bookings b LEFT JOIN vehicles v ON b.vehicle_id = v.id WHERE b.user_id=?";
        const params = [req.userId || 1];
        if (status && status !== 'all') { query += " AND b.status=?"; params.push(status); }
        query += " ORDER BY b.created_at DESC";
        const bookings = db.prepare(query).all(...params);
        bookings.forEach(b => {
          b.driver_assigned = !!(b.driver_id && ['confirmed','ongoing','completed'].includes(b.status));
          if (b.pickup_lat && b.drop_lat) {
            b.navigation_url = `https://www.google.com/maps/dir/?api=1&origin=${b.pickup_lat},${b.pickup_lng}&destination=${b.drop_lat},${b.drop_lng}&travelmode=driving`;
          }
        });
        return jsonRes(res, bookings);
      }
      case 'bookings/detail': {
        const bid = parseInt(input.id) || 0;
        const booking = db.prepare("SELECT b.*, v.name as vehicle_name, v.type as vehicle_type, v.image, v.price_per_km, v.price_per_day FROM bookings b LEFT JOIN vehicles v ON b.vehicle_id = v.id WHERE b.id=? AND b.user_id=?").get(bid, req.userId || 1);
        if (!booking) return jsonErr(res, 'Booking not found', 404);
        booking.driver_assigned = !!(booking.driver_id && ['confirmed','ongoing','completed'].includes(booking.status));
        if (booking.driver_assigned) {
          const d = db.prepare("SELECT vehicle_model, vehicle_number, rating FROM drivers WHERE id=?").get(booking.driver_id);
          if (d) {
            booking.driver_vehicle = (d.vehicle_model || '') + ' · ' + (d.vehicle_number || '');
            booking.driver_rating = d.rating || 5.0;
          }
        }
        if (booking.pickup_lat && booking.drop_lat) {
          booking.navigation_url = `https://www.google.com/maps/dir/?api=1&origin=${booking.pickup_lat},${booking.pickup_lng}&destination=${booking.drop_lat},${booking.drop_lng}&travelmode=driving`;
        }
        return jsonRes(res, booking);
      }
      case 'bookings/detail': {
        const bid2 = parseInt(input.id) || 0;
        const b2 = db.prepare("SELECT b.*, v.name as vehicle_name, v.type as vehicle_type, v.image, v.price_per_km, v.price_per_day FROM bookings b LEFT JOIN vehicles v ON b.vehicle_id = v.id WHERE b.id=?").get(bid2);
        if (!b2) return jsonErr(res, 'Booking not found', 404);
        b2.driver_assigned = !!(b2.driver_id && ['confirmed','ongoing','completed'].includes(b2.status));
        if (b2.driver_assigned) {
          const d = db.prepare("SELECT vehicle_model, vehicle_number, rating FROM drivers WHERE id=?").get(b2.driver_id);
          if (d) { b2.driver_vehicle = (d.vehicle_model||'') + ' · ' + (d.vehicle_number||''); b2.driver_rating = d.rating || 5.0; }
        }
        if (b2.pickup_lat && b2.drop_lat) b2.navigation_url = `https://www.google.com/maps/dir/?api=1&origin=${b2.pickup_lat},${b2.pickup_lng}&destination=${b2.drop_lat},${b2.drop_lng}&travelmode=driving`;
        return jsonRes(res, b2);
      }
      case 'sos/create': {
        db.prepare("INSERT INTO sos_alerts (booking_id, user_id, alert_type, message, lat, lng) VALUES (?,?,?,?,?,?)").run(input.booking_id || null, req.userId || 1, input.alert_type || 'emergency', input.message || '', input.lat || null, input.lng || null);
        return jsonRes(res, { success: true, message: 'SOS alert sent to admin' });
      }
      case 'notifications/list': {
        const notifs = db.prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50").all(req.userId || 1);
        return jsonRes(res, notifs);
      }
      case 'notifications/unread_count': {
        const row = db.prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id=? AND is_read=0").get(req.userId || 1);
        return jsonRes(res, { count: row.count });
      }
      case 'notifications/mark_read': {
        const nid = parseInt(input.id) || 0;
        if (nid) {
          db.prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?").run(nid, req.userId || 1);
        } else {
          db.prepare("UPDATE notifications SET is_read=1 WHERE user_id=?").run(req.userId || 1);
        }
        return jsonRes(res, { success: true });
      }
      case 'notifications/create': {
        const targetUserId = parseInt(input.user_id) || 0;
        const title = input.title || '';
        const message = input.message || '';
        const type = input.type || 'info';
        if (!targetUserId || !title) return jsonErr(res, 'Missing user_id or title');
        db.prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)").run(targetUserId, title, message, type);
        return jsonRes(res, { success: true });
      }
      default:
        return jsonErr(res, 'Unknown action', 404);
    }
  } catch (e) {
    console.error('API Error:', e);
    return jsonErr(res, e.message, 500);
  }
}

// Serve mobile app as root
app.get('/', (req, res) => {
  res.sendFile(path.join(__dirname, '..', 'vehigo-php', 'mobile', 'index.html'));
});

app.listen(PORT, () => {
  console.log(`TripAny server running on http://localhost:${PORT}`);
  console.log(`Mobile app: http://localhost:${PORT}/`);
  console.log(`Admin panel: http://localhost:${PORT}/admin/`);
});
