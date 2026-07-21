const { createClient } = require('@libsql/client');
const path = require('path');
const crypto = require('crypto');

function getDb() {
  const url = process.env.TURSO_DB_URL;
  const token = process.env.TURSO_DB_TOKEN;
  if (url && token) {
    return createClient({ url, authToken: token });
  }
  return createClient({ url: 'file:' + path.join(__dirname, '..', 'vehigo-php', 'vehigo.db') });
}

const ADMIN_TOKENS = new Set();
const ADMIN_SECRET = process.env.SESSION_SECRET || 'tripany-admin-secret-key';
const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'admin123';

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
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  res.setHeader('Content-Type', 'application/json');

  if (req.method === 'OPTIONS') { res.statusCode = 200; res.end(''); return; }

  function json(data, status = 200) {
    res.statusCode = status;
    res.end(JSON.stringify(data));
  }
  function err(msg, status = 400) {
    json({ success: false, error: msg }, status);
  }

  function verifyAdmin() {
    const auth = req.headers.authorization || '';
    const token = auth.replace('Bearer ', '');
    return ADMIN_TOKENS.has(token);
  }

  try {
    switch (action) {

      // ── CUSTOMER AUTH ──
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

      // ── ADMIN AUTH ──
      case 'admin/login': {
        const username = all.username || '';
        const password = all.password || '';
        if (username === ADMIN_USERNAME && password === ADMIN_PASSWORD) {
          const token = crypto.randomBytes(32).toString('hex');
          ADMIN_TOKENS.add(token);
          return json({ success: true, token, user: { username: 'admin', role: 'super_admin' } });
        }
        return err('Invalid credentials', 401);
      }

      case 'admin/verify': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        return json({ success: true, user: { username: 'admin', role: 'super_admin' } });
      }

      // ── VEHICLES ──
      case 'admin/vehicles': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const search = all.search || '';
        const type = all.type || '';
        let sql = "SELECT v.*, vc.name as category_name FROM vehicles v LEFT JOIN vehicle_categories vc ON v.category_id = vc.id WHERE 1=1";
        const args = [];
        if (type) { sql += " AND v.type=?"; args.push(type); }
        if (search) { sql += " AND (v.name LIKE ? OR v.brand LIKE ? OR v.model LIKE ?)"; args.push('%'+search+'%','%'+search+'%','%'+search+'%'); }
        sql += " ORDER BY v.created_at DESC";
        return json((await db.execute({ sql, args })).rows);
      }

      case 'admin/vehicle/add': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const r = await db.execute({
          sql: `INSERT INTO vehicles (category_id,name,brand,model,year,type,fuel_type,transmission,seats,bags,price_per_day,price_per_km,image,description,features,inclusions,exclusions,facilities,terms,cancellation_policy,is_active,is_featured,total_bookings,rating,total_reviews)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
          args: [all.category_id||1, all.name||'', all.brand||'', all.model||'', all.year||2024, all.type||'Sedan', all.fuel_type||'Petrol', all.transmission||'Manual', all.seats||5, all.bags||2, all.price_per_day||0, all.price_per_km||0, all.image||'', all.description||'', all.features||'', all.inclusions||'', all.exclusions||'', all.facilities||'', all.terms||'', all.cancellation_policy||'', all.is_active?1:0, all.is_featured?1:0, 0, 0, 0]
        });
        return json({ success: true, id: Number(r.lastInsertRowid) });
      }

      case 'admin/vehicle/update': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing vehicle id');
        await db.execute({
          sql: `UPDATE vehicles SET category_id=?,name=?,brand=?,model=?,year=?,type=?,fuel_type=?,transmission=?,seats=?,bags=?,price_per_day=?,price_per_km=?,image=?,description=?,features=?,inclusions=?,exclusions=?,facilities=?,terms=?,cancellation_policy=?,is_active=?,is_featured=? WHERE id=?`,
          args: [all.category_id||1, all.name||'', all.brand||'', all.model||'', all.year||2024, all.type||'Sedan', all.fuel_type||'Petrol', all.transmission||'Manual', all.seats||5, all.bags||2, all.price_per_day||0, all.price_per_km||0, all.image||'', all.description||'', all.features||'', all.inclusions||'', all.exclusions||'', all.facilities||'', all.terms||'', all.cancellation_policy||'', all.is_active?1:0, all.is_featured?1:0, id]
        });
        return json({ success: true });
      }

      case 'admin/vehicle/delete': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing vehicle id');
        await db.execute({ sql: "DELETE FROM vehicles WHERE id=?", args: [id] });
        return json({ success: true });
      }

      // ── DRIVERS ──
      case 'admin/drivers': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        return json((await db.execute({ sql: "SELECT * FROM drivers ORDER BY created_at DESC" })).rows);
      }

      case 'admin/driver/add': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const r = await db.execute({
          sql: `INSERT INTO drivers (name,phone,license_number,vehicle_model,vehicle_number,status,rating,total_trips,lat,lng)
                VALUES (?,?,?,?,?,?,?,?,?,?)`,
          args: [all.name||'', all.phone||'', all.license_number||'', all.vehicle_model||'', all.vehicle_number||'', all.status||'offline', all.rating||0, all.total_trips||0, all.lat||null, all.lng||null]
        });
        return json({ success: true, id: Number(r.lastInsertRowid) });
      }

      case 'admin/driver/update': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing driver id');
        await db.execute({
          sql: `UPDATE drivers SET name=?,phone=?,license_number=?,vehicle_model=?,vehicle_number=?,status=?,rating=?,total_trips=?,lat=?,lng=? WHERE id=?`,
          args: [all.name||'', all.phone||'', all.license_number||'', all.vehicle_model||'', all.vehicle_number||'', all.status||'offline', all.rating||0, all.total_trips||0, all.lat||null, all.lng||null, id]
        });
        return json({ success: true });
      }

      case 'admin/driver/delete': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing driver id');
        await db.execute({ sql: "DELETE FROM drivers WHERE id=?", args: [id] });
        return json({ success: true });
      }

      // ── BOOKINGS ──
      case 'admin/bookings': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const status = all.status || '';
        let sql = "SELECT b.*, u.name as user_name, u.phone as user_phone, v.name as vehicle_name, v.type as vehicle_type FROM bookings b LEFT JOIN users u ON b.user_id = u.id LEFT JOIN vehicles v ON b.vehicle_id = v.id WHERE 1=1";
        const args = [];
        if (status && status !== 'all') { sql += " AND b.status=?"; args.push(status); }
        sql += " ORDER BY b.created_at DESC";
        return json((await db.execute({ sql, args })).rows);
      }

      case 'admin/booking/update': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing booking id');
        const status = all.status || '';
        const driverId = all.driver_id || null;
        if (status) {
          await db.execute({ sql: "UPDATE bookings SET status=? WHERE id=?", args: [status, id] });
        }
        if (driverId) {
          await db.execute({ sql: "UPDATE bookings SET driver_id=? WHERE id=?", args: [driverId, id] });
        }
        return json({ success: true });
      }

      // ── CATEGORIES ──
      case 'admin/categories': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        return json((await db.execute({ sql: "SELECT * FROM vehicle_categories ORDER BY sort_order ASC" })).rows);
      }

      case 'admin/category/add': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const r = await db.execute({ sql: "INSERT INTO vehicle_categories (name,slug,icon,sort_order,active) VALUES (?,?,?,?,?)", args: [all.name||'', all.slug||'', all.icon||'', all.sort_order||0, 1] });
        return json({ success: true, id: Number(r.lastInsertRowid) });
      }

      case 'admin/category/update': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing category id');
        await db.execute({ sql: "UPDATE vehicle_categories SET name=?,slug=?,icon=?,sort_order=?,active=? WHERE id=?", args: [all.name||'', all.slug||'', all.icon||'', all.sort_order||0, all.active?1:0, id] });
        return json({ success: true });
      }

      case 'admin/category/delete': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing category id');
        await db.execute({ sql: "DELETE FROM vehicle_categories WHERE id=?", args: [id] });
        return json({ success: true });
      }

      // ── PRICING PACKAGES ──
      case 'admin/pricing': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        return json((await db.execute({ sql: "SELECT pp.*, v.name as vehicle_name FROM pricing_packages pp LEFT JOIN vehicles v ON pp.vehicle_id = v.id ORDER BY pp.vehicle_id, pp.hours ASC" })).rows);
      }

      case 'admin/pricing/add': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const r = await db.execute({ sql: "INSERT INTO pricing_packages (vehicle_id,hours,kms,price,label,is_active) VALUES (?,?,?,?,?,?)", args: [all.vehicle_id||0, all.hours||1, all.kms||0, all.price||0, all.label||'', all.is_active?1:0] });
        return json({ success: true, id: Number(r.lastInsertRowid) });
      }

      case 'admin/pricing/update': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing pricing id');
        await db.execute({ sql: "UPDATE pricing_packages SET vehicle_id=?,hours=?,kms=?,price=?,label=?,is_active=? WHERE id=?", args: [all.vehicle_id||0, all.hours||1, all.kms||0, all.price||0, all.label||'', all.is_active?1:0, id] });
        return json({ success: true });
      }

      case 'admin/pricing/delete': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing pricing id');
        await db.execute({ sql: "DELETE FROM pricing_packages WHERE id=?", args: [id] });
        return json({ success: true });
      }

      // ── TOUR PACKAGES ──
      case 'admin/tours': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        return json((await db.execute({ sql: "SELECT * FROM tour_packages ORDER BY tour_date DESC" })).rows);
      }

      case 'admin/tour/add': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const r = await db.execute({
          sql: `INSERT INTO tour_packages (title,destination,tour_date,return_date,description,vehicle_type,price_per_person,max_participants,current_participants,included_items,add_ons,is_active)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)`,
          args: [all.title||'', all.destination||'', all.tour_date||'', all.return_date||'', all.description||'', all.vehicle_type||'', all.price_per_person||0, all.max_participants||0, all.current_participants||0, all.included_items||'', all.add_ons||'', all.is_active?1:0]
        });
        return json({ success: true, id: Number(r.lastInsertRowid) });
      }

      case 'admin/tour/update': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing tour id');
        await db.execute({
          sql: `UPDATE tour_packages SET title=?,destination=?,tour_date=?,return_date=?,description=?,vehicle_type=?,price_per_person=?,max_participants=?,current_participants=?,included_items=?,add_ons=?,is_active=? WHERE id=?`,
          args: [all.title||'', all.destination||'', all.tour_date||'', all.return_date||'', all.description||'', all.vehicle_type||'', all.price_per_person||0, all.max_participants||0, all.current_participants||0, all.included_items||'', all.add_ons||'', all.is_active?1:0, id]
        });
        return json({ success: true });
      }

      case 'admin/tour/delete': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing tour id');
        await db.execute({ sql: "DELETE FROM tour_packages WHERE id=?", args: [id] });
        return json({ success: true });
      }

      // ── COUPONS ──
      case 'admin/coupons': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        return json((await db.execute({ sql: "SELECT * FROM coupons ORDER BY created_at DESC" })).rows);
      }

      case 'admin/coupon/add': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const r = await db.execute({
          sql: `INSERT INTO coupons (code,description,discount_type,discount_value,min_fare,max_discount,usage_limit,used_count,valid_from,valid_until,is_active)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)`,
          args: [all.code||'', all.description||'', all.discount_type||'percentage', all.discount_value||0, all.min_fare||0, all.max_discount||0, all.usage_limit||0, 0, all.valid_from||'', all.valid_until||'', all.is_active?1:0]
        });
        return json({ success: true, id: Number(r.lastInsertRowid) });
      }

      case 'admin/coupon/update': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing coupon id');
        await db.execute({
          sql: `UPDATE coupons SET code=?,description=?,discount_type=?,discount_value=?,min_fare=?,max_discount=?,usage_limit=?,valid_from=?,valid_until=?,is_active=? WHERE id=?`,
          args: [all.code||'', all.description||'', all.discount_type||'percentage', all.discount_value||0, all.min_fare||0, all.max_discount||0, all.usage_limit||0, all.valid_from||'', all.valid_until||'', all.is_active?1:0, id]
        });
        return json({ success: true });
      }

      case 'admin/coupon/delete': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing coupon id');
        await db.execute({ sql: "DELETE FROM coupons WHERE id=?", args: [id] });
        return json({ success: true });
      }

      // ── BANNERS ──
      case 'admin/banners': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        return json((await db.execute({ sql: "SELECT * FROM banners ORDER BY sort_order ASC" })).rows);
      }

      case 'admin/banner/add': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const r = await db.execute({
          sql: "INSERT INTO banners (title,image_url,link_url,sort_order,is_active) VALUES (?,?,?,?,?)",
          args: [all.title||'', all.image_url||'', all.link_url||'', all.sort_order||0, all.is_active?1:0]
        });
        return json({ success: true, id: Number(r.lastInsertRowid) });
      }

      case 'admin/banner/update': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing banner id');
        await db.execute({ sql: "UPDATE banners SET title=?,image_url=?,link_url=?,sort_order=?,is_active=? WHERE id=?", args: [all.title||'', all.image_url||'', all.link_url||'', all.sort_order||0, all.is_active?1:0, id] });
        return json({ success: true });
      }

      case 'admin/banner/delete': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing banner id');
        await db.execute({ sql: "DELETE FROM banners WHERE id=?", args: [id] });
        return json({ success: true });
      }

      // ── OFFERS ──
      case 'admin/offers': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        return json((await db.execute({ sql: "SELECT * FROM offers ORDER BY created_at DESC" })).rows);
      }

      case 'admin/offer/add': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const r = await db.execute({
          sql: "INSERT INTO offers (title,description,discount_percent,code,valid_from,valid_until,is_active) VALUES (?,?,?,?,?,?,?)",
          args: [all.title||'', all.description||'', all.discount_percent||0, all.code||'', all.valid_from||'', all.valid_until||'', all.is_active?1:0]
        });
        return json({ success: true, id: Number(r.lastInsertRowid) });
      }

      case 'admin/offer/update': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing offer id');
        await db.execute({ sql: "UPDATE offers SET title=?,description=?,discount_percent=?,code=?,valid_from=?,valid_until=?,is_active=? WHERE id=?", args: [all.title||'', all.description||'', all.discount_percent||0, all.code||'', all.valid_from||'', all.valid_until||'', all.is_active?1:0, id] });
        return json({ success: true });
      }

      case 'admin/offer/delete': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing offer id');
        await db.execute({ sql: "DELETE FROM offers WHERE id=?", args: [id] });
        return json({ success: true });
      }

      // ── SOS ALERTS ──
      case 'admin/sos': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        return json((await db.execute({ sql: "SELECT s.*, u.name as user_name, u.phone as user_phone FROM sos_alerts s LEFT JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC" })).rows);
      }

      // ── DASHBOARD ──
      case 'admin/dashboard': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const totalBookings = (await db.execute({ sql: "SELECT COUNT(*) as c FROM bookings" })).rows[0].c;
        const totalRevenue = (await db.execute({ sql: "SELECT COALESCE(SUM(total_fare),0) as c FROM bookings WHERE status='completed'" })).rows[0].c;
        const activeDrivers = (await db.execute({ sql: "SELECT COUNT(*) as c FROM drivers WHERE status='online'" })).rows[0].c;
        const totalUsers = (await db.execute({ sql: "SELECT COUNT(*) as c FROM users" })).rows[0].c;
        const totalVehicles = (await db.execute({ sql: "SELECT COUNT(*) as c FROM vehicles WHERE is_active=1" })).rows[0].c;
        const pendingBookings = (await db.execute({ sql: "SELECT COUNT(*) as c FROM bookings WHERE status='pending'" })).rows[0].c;
        const recentBookings = (await db.execute({
          sql: "SELECT b.*, u.name as user_name, v.name as vehicle_name FROM bookings b LEFT JOIN users u ON b.user_id = u.id LEFT JOIN vehicles v ON b.vehicle_id = v.id ORDER BY b.created_at DESC LIMIT 5"
        })).rows;
        const topDrivers = (await db.execute({ sql: "SELECT * FROM drivers ORDER BY rating DESC LIMIT 3" })).rows;
        const sosAlerts = (await db.execute({ sql: "SELECT COUNT(*) as c FROM sos_alerts WHERE status='pending'" })).rows[0].c;
        return json({
          totalBookings, totalRevenue, activeDrivers, totalUsers, totalVehicles,
          pendingBookings, recentBookings, topDrivers, sosAlerts
        });
      }

      // ── SEND NOTIFICATION ──
      case 'admin/notification/send': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const userId = parseInt(all.user_id) || 0;
        if (!userId || !all.title) return err('Missing user_id or title');
        await db.execute({ sql: "INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,?)", args: [userId, all.title, all.message||'', all.type||'info'] });
        return json({ success: true });
      }

      // ── PUBLIC ENDPOINTS (no auth) ──
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

      case 'admin/addons': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        return json((await db.execute({ sql: "SELECT * FROM tour_addons ORDER BY name ASC" })).rows);
      }

      case 'admin/addon/add': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const r = await db.execute({
          sql: "INSERT INTO tour_addons (name,description,icon,price,is_active) VALUES (?,?,?,?,?)",
          args: [all.name||'', all.description||'', all.icon||'📦', all.price||0, all.is_active?1:0]
        });
        return json({ success: true, id: Number(r.lastInsertRowid) });
      }

      case 'admin/addon/update': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing addon id');
        await db.execute({ sql: "UPDATE tour_addons SET name=?,description=?,icon=?,price=?,is_active=? WHERE id=?", args: [all.name||'', all.description||'', all.icon||'📦', all.price||0, all.is_active?1:0, id] });
        return json({ success: true });
      }

      case 'admin/addon/delete': {
        if (!verifyAdmin()) return err('Unauthorized', 401);
        const id = parseInt(all.id) || 0;
        if (!id) return err('Missing addon id');
        await db.execute({ sql: "DELETE FROM tour_addons WHERE id=?", args: [id] });
        return json({ success: true });
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
          if (d.rows.length > 0) booking.driver_vehicle = (d.rows[0].vehicle_model||'') + ' · ' + (d.rows[0].vehicle_number||'');
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

      // ── PUBLIC OFFERS ──
      case 'offers/list': {
        return json((await db.execute({ sql: "SELECT * FROM offers WHERE is_active=1 ORDER BY created_at DESC" })).rows);
      }

      default:
        return err('Unknown action', 404);
    }
  } catch (e) {
    console.error('API Error:', e);
    return err(e.message, 500);
  }
};
