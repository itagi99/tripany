const { createClient } = require('@libsql/client');
const Database = require('better-sqlite3');
const path = require('path');
const fs = require('fs');

const local = new Database(path.join(__dirname, '..', 'vehigo-php', 'vehigo.db'));

const tursoUrl = process.env.TURSO_DB_URL;
const tursoToken = process.env.TURSO_DB_TOKEN;

if (!tursoUrl || !tursoToken) {
  console.error('Set TURSO_DB_URL and TURSO_DB_TOKEN');
  process.exit(1);
}

const turso = createClient({ url: tursoUrl, authToken: tursoToken });

function safeVal(v) {
  if (v === null || v === undefined) return null;
  if (Buffer.isBuffer(v)) return v.toString('hex');
  if (typeof v === 'bigint') return Number(v);
  if (typeof v === 'object') return JSON.stringify(v);
  return v;
}

async function main() {
  // 1. Drop all existing tables
  const tables = local.prepare("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name").all().map(t => t.name);
  for (const t of tables.reverse()) {
    try { await turso.execute({ sql: `DROP TABLE IF EXISTS "${t}"` }); } catch (e) {}
  }
  console.log('Dropped existing tables');

  // 2. Load schema from live DB
  const schemaSql = local.prepare("SELECT sql FROM sqlite_master WHERE type='table' AND sql IS NOT NULL AND name NOT LIKE 'sqlite_%'").all().map(r => r.sql).join(';\n') + ';';
  const stmts = schemaSql.split(';').filter(s => s.trim().length > 5);
  for (const stmt of stmts) {
    try {
      await turso.execute({ sql: stmt + ';' });
    } catch (e) {
      console.log('  Schema error: ' + e.message.substring(0, 80));
    }
  }
  console.log('Schema loaded (' + tables.length + ' tables)');

  // 3. Seed data in order (respecting FK constraints)
  const seedOrder = [
    'vehicle_categories', 'vehicles', 'vehicle_pricing',
    'users', 'drivers', 'settings',
    'pickup_locations', 'banners', 'coupons', 'offers',
    'tour_addons', 'tour_packages',
    'vehicle_gallery', 'vehicle_features', 'pricing_packages', 'reviews',
    'bookings', 'tour_bookings', 'notifications', 'sos_alerts', 'wishlist',
    'driver_documents'
  ];

  for (const table of seedOrder) {
    const exists = local.prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?").get(table);
    if (!exists) continue;
    const rows = local.prepare(`SELECT * FROM "${table}"`).all();
    if (rows.length === 0) continue;
    console.log(`  ${table}... (${rows.length} rows)`);

    const cols = Object.keys(rows[0]);
    for (const row of rows) {
      const vals = cols.map(c => safeVal(row[c]));
      try {
        const ph = cols.map(() => '?').join(',');
        await turso.execute({
          sql: `INSERT INTO "${table}" ("${cols.join('","')}") VALUES (${ph})`,
          args: vals
        });
      } catch (e) {
        console.log(`    ${row.id || row.code || ''}: ${e.message.substring(0, 60)}`);
      }
    }
  }
  console.log('All data seeded!');
}

main().catch(console.error);
