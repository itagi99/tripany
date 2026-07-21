const { createClient } = require('@libsql/client');
const path = require('path');

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

module.exports = async (req, res) => {
  const db = getDb();
  const url = new URL(req.url, `https://${req.headers.host}`);
  const action = url.searchParams.get('action') || '';
  const input = Object.fromEntries(url.searchParams);

  let body = {};
  if (req.method === 'POST') {
    try {
      const chunks = [];
      for await (const chunk of req) chunks.push(chunk);
      body = JSON.parse(Buffer.concat(chunks).toString() || '{}');
    } catch (e) { body = {}; }
  }

  const all = { ...input, ...body };

  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  res.setHeader('Content-Type', 'application/json');

  if (req.method === 'OPTIONS') { res.statusCode = 200; res.end(''); return; }

  function json(data, status = 200) {
    res.statusCode = status;
    res.end(JSON.stringify(data));
  }
  function err(msg, status = 400) {
    json({ success: false, error: msg }, status);
  }

  try {
    switch (action) {

      case 'auth/login': {
        const phone = all.phone || '';
        if (phone.length < 10) return err('Invalid phone number');
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
        return json({ success: true, message: 'OTP sent', otp });
      }

      case 'auth/verify': {
        const otp = all.otp || '';
        const phone = all.phone || '';
        if (!phone || !otp) return err('Phone and OTP required', 401);
        let r = await db.execute({
          sql: "SELECT * FROM users WHERE phone=? AND otp_code=? AND otp_expires > datetime('now')",
          args: [phone, otp]
        });
        if (r.rows.length === 0) return err('Invalid or expired OTP', 401);
        const user = r.rows[0];
        await db.execute({
          sql: "UPDATE users SET otp_code=NULL, otp_expires=NULL, is_verified=1 WHERE id=?",
          args: [user.id]
        });
        return json({ success: true, user });
      }

      case 'vehicles/list': {
        const type = all.type || '';
        let sql = "SELECT v.*, vc.name as category_name FROM vehicles v LEFT JOIN vehicle_categories vc ON v.category_id = vc.id WHERE v.is_active=1";
        const args = [];
        if (type) { sql += " AND v.type=?"; args.push(type); }
        sql += " ORDER BY v.is_featured DESC, v.rating DESC LIMIT 20";
        const r = await db.execute({ sql, args });
        return json(r.rows);
      }

      case 'vehicles/detail': {
        const id = parseInt(all.id) || 0;
        const v = await db.execute({
          sql: "SELECT v.*, vc.name as category_name FROM vehicles v LEFT JOIN vehicle_categories vc ON v.category_id = vc.id WHERE v.id=?",
          args: [id]
        });
        if (v.rows.length === 0) return err('Vehicle not found', 404);
        const vehicle = v.rows[0];
        vehicle.reviews = (await db.execute({ sql: "SELECT r.*, u.name as user_name FROM reviews r LEFT JOIN users u ON r.user_id = u.id WHERE r.vehicle_id=? ORDER BY r.created_at DESC LIMIT 5", args: [id] })).rows;
        vehicle.pricing = (await db.execute({ sql: "SELECT * FROM vehicle_pricing WHERE vehicle_id=?", args: [id] })).rows[0] || {};
        vehicle.packages = (await db.execute({ sql: "SELECT * FROM pricing_packages WHERE vehicle_id=? AND is_active=1 ORDER BY hours ASC", args: [id] })).rows;
        vehicle.gallery = (await db.execute({ sql: "SELECT * FROM vehicle_gallery WHERE vehicle_id=? ORDER BY sort_order ASC", args: [id] })).rows;
        return json(vehicle);
      }

      case 'addons/list': {
        return json((await db.execute({ sql: "SELECT * FROM tour_addons WHERE is_active=1 ORDER BY name ASC" })).rows);
      }

      case 'tours/list': {
        return json((await db.execute({ sql: "SELECT * FROM tour_packages WHERE is_active=1 ORDER BY tour_date ASC" })).rows);
      }

      case 'tours/book': {
        const tour_id = parseInt(all.tour_id) || 0;
        const persons = parseInt(all.persons) || 1;
        const tour = await db.execute({ sql: "SELECT * FROM tour_packages WHERE id=? AND is_active=1", args: [tour_id] });
        if (tour.rows.length === 0) return err('Tour not found', 404);
        const t = tour.rows[0];
        const total_amount = t.price_per_person * persons;
        await db.execute({ sql: "INSERT INTO tour_bookings (tour_id, user_id, persons, total_amount, payment_status, status) VALUES (?,?,?,?,'paid','confirmed')", args: [tour_id, 1, persons, total_amount] });
        await db.execute({ sql: "UPDATE tour_packages SET current_participants = current_participants + ? WHERE id=?", args: [persons, tour_id] });
        return json({ success: true, message: 'Tour booked', total: total_amount });
      }

      case 'bookings/create': {
        const vid = parseInt(all.vehicle_id) || 0;
        const pickup = all.pickup_location || '';
        const drop = all.drop_location || '';
        const distance = parseFloat(all.distance_km) || 0;
        const base_fare = parseFloat(all.base_fare) || 0;
        const tax = parseFloat(all.tax) || 0;
        const discount = parseFloat(all.discount) || 0;
        const total = parseFloat(all.total_fare) || 0;
        const date = all.pickup_date || new Date().toISOString().split('T')[0];
        const time = all.pickup_time || '09:00';
        const trip_type = all.trip_type || 'one_way';
        const pricing_type = all.pricing_type || 'per_km';
        const package_id = all.package_id || null;
        const duration_days = parseInt(all.duration_days) || 1;
        const ref = 'TRP' + Math.random().toString(36).substring(2, 8).toUpperCase();
        const r = await db.execute({
          sql: "INSERT INTO bookings (user_id, vehicle_id, booking_ref, pickup_date, return_date, pickup_location, pickup_lat, pickup_lng, drop_location, drop_lat, drop_lng, distance_km, duration_days, base_fare, tax, discount, total_fare, trip_type, pricing_type, package_id, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending')",
          args: [1, vid, ref, date+' '+time+':00', (all.return_date||date)+' '+(all.return_time||time)+':00', pickup, all.pickup_lat||null, all.pickup_lng||null, drop, all.drop_lat||null, all.drop_lng||null, distance, duration_days, base_fare, tax, discount, total, trip_type, pricing_type, package_id]
        });
        await db.execute({ sql: "UPDATE vehicles SET total_bookings = total_bookings + 1 WHERE id=?", args: [vid] });
        return json({ success: true, booking_ref: ref, id: Number(r.lastInsertRowid) });
      }

      case 'bookings/list': {
        const status = all.status || '';
        let sql = "SELECT b.*, v.name as vehicle_name, v.type as vehicle_type, v.image FROM bookings b LEFT JOIN vehicles v ON b.vehicle_id = v.id WHERE b.user_id=?";
        const args = [1];
        if (status && status !== 'all') { sql += " AND b.status=?"; args.push(status); }
        sql += " ORDER BY b.created_at DESC";
        const r = await db.execute({ sql, args });
        return json(r.rows.map(b => ({
          ...b,
          driver_assigned: !!(b.driver_id && ['confirmed','ongoing','completed'].includes(b.status)),
          navigation_url: (b.pickup_lat && b.drop_lat) ? `https://www.google.com/maps/dir/?api=1&origin=${b.pickup_lat},${b.pickup_lng}&destination=${b.drop_lat},${b.drop_lng}&travelmode=driving` : null
        })));
      }

      case 'bookings/detail': {
        const id = parseInt(all.id) || 0;
        const r = await db.execute({ sql: "SELECT b.*, v.name as vehicle_name, v.type as vehicle_type, v.image, v.price_per_km, v.price_per_day FROM bookings b LEFT JOIN vehicles v ON b.vehicle_id = v.id WHERE b.id=?", args: [id] });
        if (r.rows.length === 0) return err('Booking not found', 404);
        const booking = r.rows[0];
        booking.driver_assigned = !!(booking.driver_id && ['confirmed','ongoing','completed'].includes(booking.status));
        if (booking.driver_assigned) {
          const d = await db.execute({ sql: "SELECT vehicle_model, vehicle_number, rating FROM drivers WHERE id=?", args: [booking.driver_id] });
          if (d.rows.length > 0) booking.driver_vehicle = (d.rows[0].vehicle_model||'') + ' \u00B7 ' + (d.rows[0].vehicle_number||'');
        }
        if (booking.pickup_lat && booking.drop_lat) {
          booking.navigation_url = `https://www.google.com/maps/dir/?api=1&origin=${booking.pickup_lat},${booking.pickup_lng}&destination=${booking.drop_lat},${booking.drop_lng}&travelmode=driving`;
        }
        return json(booking);
      }

      case 'sos/create': {
        await db.execute({ sql: "INSERT INTO sos_alerts (booking_id, user_id, alert_type, message, lat, lng) VALUES (?,?,?,?,?,?)", args: [all.booking_id||null, 1, all.alert_type||'emergency', all.message||'', all.lat||null, all.lng||null] });
        return json({ success: true, message: 'SOS alert sent to admin' });
      }

      case 'notifications/list': {
        return json((await db.execute({ sql: "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50", args: [1] })).rows);
      }

      case 'notifications/unread_count': {
        const r = await db.execute({ sql: "SELECT COUNT(*) as count FROM notifications WHERE user_id=? AND is_read=0", args: [1] });
        return json({ count: r.rows[0].count });
      }

      case 'notifications/mark_read': {
        const nid = parseInt(all.id) || 0;
        if (nid) {
          await db.execute({ sql: "UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?", args: [nid, 1] });
        } else {
          await db.execute({ sql: "UPDATE notifications SET is_read=1 WHERE user_id=?", args: [1] });
        }
        return json({ success: true });
      }

      case 'notifications/create': {
        const targetUserId = parseInt(all.user_id) || 0;
        if (!targetUserId || !all.title) return err('Missing user_id or title');
        await db.execute({ sql: "INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)", args: [targetUserId, all.title, all.message||'', all.type||'info'] });
        return json({ success: true });
      }

      default:
        return err('Unknown action', 404);
    }
  } catch (e) {
    console.error('API Error:', e);
    return err(e.message, 500);
  }
};
