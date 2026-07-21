const express = require('express');
const session = require('express-session');
const { createClient } = require('@libsql/client');
const path = require('path');

const app = express();

app.set('trust proxy', 1);
app.use(session({
  secret: process.env.SESSION_SECRET || 'tripany-session-secret-dev',
  resave: false,
  saveUninitialized: true,
  cookie: { secure: !!process.env.VERCEL, httpOnly: true, maxAge: 24 * 60 * 60 * 1000 }
}));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use((req, res, next) => {
  res.header('Access-Control-Allow-Origin', '*');
  res.header('Access-Control-Allow-Headers', 'Content-Type');
  next();
});

function getDb() {
  const url = process.env.TURSO_DB_URL;
  const token = process.env.TURSO_DB_TOKEN;
  if (url && token) {
    return createClient({ url, authToken: token });
  }
  return createClient({
    url: 'file:' + path.join(__dirname, '..', 'vehigo-php', 'vehigo.db'),
  });
}

function jsonRes(res, data, status = 200) {
  res.status(status).json(data);
}
function jsonErr(res, msg, status = 400) {
  res.status(status).json({ success: false, error: msg });
}

function uid(req) {
  return req.session.userId || parseInt(req.body._userId) || 1;
}

const handler = async (req, res) => {
  const db = getDb();
  const action = req.query.action || req.body.action || '';
  const input = { ...req.query, ...req.body };

  try {
    switch (action) {
      case 'auth/login': {
        const phone = input.phone || '';
        if (phone.length < 10) return jsonErr(res, 'Invalid phone number');
        const otp = String(Math.floor(100000 + Math.random() * 900000));
        let r = await db.execute({ sql: "SELECT id FROM users WHERE phone=?", args: [phone] });
        if (r.rows.length === 0) {
          await db.execute({
            sql: "INSERT INTO users (name, phone, password_hash, otp_code, otp_expires, is_verified) VALUES (?,?,?,?,datetime('now','+5 minutes'),0)",
            args: ['User', phone, 'otp', otp]
          });
        } else {
          await db.execute({
            sql: "UPDATE users SET otp_code=?, otp_expires=datetime('now','+5 minutes') WHERE phone=?",
            args: [otp, phone]
          });
        }
        return jsonRes(res, { success: true, message: 'OTP sent', otp });
      }

      case 'auth/verify': {
        const otp = input.otp || '';
        const phone = input.phone || '';
        if (!phone || !otp) return jsonErr(res, 'Phone and OTP required', 401);
        let r = await db.execute({
          sql: "SELECT * FROM users WHERE phone=? AND otp_code=? AND otp_expires > datetime('now')",
          args: [phone, otp]
        });
        if (r.rows.length === 0) return jsonErr(res, 'Invalid or expired OTP', 401);
        const user = r.rows[0];
        await db.execute({
          sql: "UPDATE users SET otp_code=NULL, otp_expires=NULL, is_verified=1 WHERE id=?",
          args: [user.id]
        });
        req.session.userId = user.id;
        return jsonRes(res, { success: true, user });
      }

      case 'vehicles/list': {
        const type = req.query.type || '';
        let sql = "SELECT v.*, vc.name as category_name FROM vehicles v LEFT JOIN vehicle_categories vc ON v.category_id = vc.id WHERE v.is_active=1";
        const args = [];
        if (type) { sql += " AND v.type=?"; args.push(type); }
        sql += " ORDER BY v.is_featured DESC, v.rating DESC LIMIT 20";
        const r = await db.execute({ sql, args });
        return jsonRes(res, r.rows);
      }

      case 'vehicles/detail': {
        const id = parseInt(input.id) || 0;
        const v = await db.execute({
          sql: "SELECT v.*, vc.name as category_name FROM vehicles v LEFT JOIN vehicle_categories vc ON v.category_id = vc.id WHERE v.id=?",
          args: [id]
        });
        if (v.rows.length === 0) return jsonErr(res, 'Vehicle not found', 404);
        const vehicle = v.rows[0];
        const reviews = await db.execute({
          sql: "SELECT r.*, u.name as user_name FROM reviews r LEFT JOIN users u ON r.user_id = u.id WHERE r.vehicle_id=? ORDER BY r.created_at DESC LIMIT 5",
          args: [id]
        });
        vehicle.reviews = reviews.rows;
        const pricing = await db.execute({
          sql: "SELECT * FROM vehicle_pricing WHERE vehicle_id=?", args: [id]
        });
        vehicle.pricing = pricing.rows[0] || {};
        const packages = await db.execute({
          sql: "SELECT * FROM pricing_packages WHERE vehicle_id=? AND is_active=1 ORDER BY hours ASC", args: [id]
        });
        vehicle.packages = packages.rows;
        const gallery = await db.execute({
          sql: "SELECT * FROM vehicle_gallery WHERE vehicle_id=? ORDER BY sort_order ASC", args: [id]
        });
        vehicle.gallery = gallery.rows;
        return jsonRes(res, vehicle);
      }

      case 'addons/list': {
        const r = await db.execute({ sql: "SELECT * FROM tour_addons WHERE is_active=1 ORDER BY name ASC" });
        return jsonRes(res, r.rows);
      }

      case 'tours/list': {
        const r = await db.execute({ sql: "SELECT * FROM tour_packages WHERE is_active=1 AND tour_date >= date('now') ORDER BY tour_date ASC" });
        return jsonRes(res, r.rows);
      }

      case 'tours/book': {
        const tour_id = parseInt(input.tour_id) || 0;
        const persons = parseInt(input.persons) || 1;
        const tour = await db.execute({
          sql: "SELECT * FROM tour_packages WHERE id=? AND is_active=1", args: [tour_id]
        });
        if (tour.rows.length === 0) return jsonErr(res, 'Tour not found', 404);
        const t = tour.rows[0];
        const total_amount = t.price_per_person * persons;
        const userId = uid(req);
        await db.execute({
          sql: "INSERT INTO tour_bookings (tour_id, user_id, persons, total_amount, payment_status, status) VALUES (?,?,?,?,'paid','confirmed')",
          args: [tour_id, userId, persons, total_amount]
        });
        await db.execute({
          sql: "UPDATE tour_packages SET current_participants = current_participants + ? WHERE id=?",
          args: [persons, tour_id]
        });
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
        const return_date_raw = input.return_date || date;
        const return_time = input.return_time || time;
        const return_date = return_date_raw + ' ' + return_time + ':00';
        const trip_type = input.trip_type || 'one_way';
        const pricing_type = input.pricing_type || 'per_km';
        const package_id = input.package_id || null;
        const duration_days = parseInt(input.duration_days) || 1;
        const ref = 'TRP' + Math.random().toString(36).substring(2, 8).toUpperCase();
        const userId = uid(req);
        const r = await db.execute({
          sql: "INSERT INTO bookings (user_id, vehicle_id, booking_ref, pickup_date, return_date, pickup_location, pickup_lat, pickup_lng, drop_location, drop_lat, drop_lng, distance_km, duration_days, base_fare, tax, discount, total_fare, trip_type, pricing_type, package_id, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending')",
          args: [userId, vehicle_id, ref, pickup_date, return_date, pickup, input.pickup_lat || null, input.pickup_lng || null, drop, input.drop_lat || null, input.drop_lng || null, distance, duration_days, base_fare, tax, discount, total, trip_type, pricing_type, package_id]
        });
        await db.execute({
          sql: "UPDATE vehicles SET total_bookings = total_bookings + 1 WHERE id=?",
          args: [vehicle_id]
        });
        return jsonRes(res, { success: true, booking_ref: ref, id: Number(r.lastInsertRowid) });
      }

      case 'bookings/list': {
        const status = req.query.status || '';
        const userId = uid(req);
        let sql = "SELECT b.*, v.name as vehicle_name, v.type as vehicle_type, v.image FROM bookings b LEFT JOIN vehicles v ON b.vehicle_id = v.id WHERE b.user_id=?";
        const args = [userId];
        if (status && status !== 'all') { sql += " AND b.status=?"; args.push(status); }
        sql += " ORDER BY b.created_at DESC";
        const r = await db.execute({ sql, args });
        const bookings = r.rows.map(b => ({
          ...b,
          driver_assigned: !!(b.driver_id && ['confirmed','ongoing','completed'].includes(b.status)),
          navigation_url: (b.pickup_lat && b.drop_lat)
            ? `https://www.google.com/maps/dir/?api=1&origin=${b.pickup_lat},${b.pickup_lng}&destination=${b.drop_lat},${b.drop_lng}&travelmode=driving`
            : null
        }));
        return jsonRes(res, bookings);
      }

      case 'bookings/detail': {
        const id = parseInt(input.id) || 0;
        const r = await db.execute({
          sql: "SELECT b.*, v.name as vehicle_name, v.type as vehicle_type, v.image, v.price_per_km, v.price_per_day FROM bookings b LEFT JOIN vehicles v ON b.vehicle_id = v.id WHERE b.id=?",
          args: [id]
        });
        if (r.rows.length === 0) return jsonErr(res, 'Booking not found', 404);
        const booking = r.rows[0];
        booking.driver_assigned = !!(booking.driver_id && ['confirmed','ongoing','completed'].includes(booking.status));
        if (booking.driver_assigned) {
          const d = await db.execute({
            sql: "SELECT vehicle_model, vehicle_number, rating FROM drivers WHERE id=?",
            args: [booking.driver_id]
          });
          if (d.rows.length > 0) {
            booking.driver_vehicle = (d.rows[0].vehicle_model || '') + ' \u00B7 ' + (d.rows[0].vehicle_number || '');
            booking.driver_rating = d.rows[0].rating || 5.0;
          }
        }
        if (booking.pickup_lat && booking.drop_lat) {
          booking.navigation_url = `https://www.google.com/maps/dir/?api=1&origin=${booking.pickup_lat},${booking.pickup_lng}&destination=${booking.drop_lat},${booking.drop_lng}&travelmode=driving`;
        }
        return jsonRes(res, booking);
      }

      case 'sos/create': {
        await db.execute({
          sql: "INSERT INTO sos_alerts (booking_id, user_id, alert_type, message, lat, lng) VALUES (?,?,?,?,?,?)",
          args: [input.booking_id || null, uid(req), input.alert_type || 'emergency', input.message || '', input.lat || null, input.lng || null]
        });
        return jsonRes(res, { success: true, message: 'SOS alert sent to admin' });
      }

      case 'notifications/list': {
        const userId = uid(req);
        const r = await db.execute({
          sql: "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50",
          args: [userId]
        });
        return jsonRes(res, r.rows);
      }

      case 'notifications/unread_count': {
        const userId = uid(req);
        const r = await db.execute({
          sql: "SELECT COUNT(*) as count FROM notifications WHERE user_id=? AND is_read=0",
          args: [userId]
        });
        return jsonRes(res, { count: r.rows[0].count });
      }

      case 'notifications/mark_read': {
        const userId = uid(req);
        const nid = parseInt(input.id) || 0;
        if (nid) {
          await db.execute({ sql: "UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?", args: [nid, userId] });
        } else {
          await db.execute({ sql: "UPDATE notifications SET is_read=1 WHERE user_id=?", args: [userId] });
        }
        return jsonRes(res, { success: true });
      }

      case 'notifications/create': {
        const targetUserId = parseInt(input.user_id) || 0;
        const title = input.title || '';
        const message = input.message || '';
        const type = input.type || 'info';
        if (!targetUserId || !title) return jsonErr(res, 'Missing user_id or title');
        await db.execute({
          sql: "INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)",
          args: [targetUserId, title, message, type]
        });
        return jsonRes(res, { success: true });
      }

      default:
        return jsonErr(res, 'Unknown action', 404);
    }
  } catch (e) {
    console.error('API Error:', e);
    return jsonErr(res, e.message, 500);
  }
};

app.all('/api/index.php', handler);
app.all('/', handler);

module.exports = app;
