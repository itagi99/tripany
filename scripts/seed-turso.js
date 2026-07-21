// Run: node scripts/seed-turso.js
// Loads schema + data from local SQLite into Turso
// Usage: TURSO_DB_URL=libsql://... TURSO_DB_TOKEN=... node scripts/seed-turso.js

const { createClient } = require('@libsql/client');
const Database = require('better-sqlite3');
const path = require('path');

const local = new Database(path.join(__dirname, '..', 'vehigo-php', 'vehigo.db'));

const tursoUrl = process.env.TURSO_DB_URL;
const tursoToken = process.env.TURSO_DB_TOKEN;

if (!tursoUrl || !tursoToken) {
  console.error('Set TURSO_DB_URL and TURSO_DB_TOKEN');
  process.exit(1);
}

const turso = createClient({ url: tursoUrl, authToken: tursoToken });

async function main() {
  // 1. Load schema from schema.sql
  const fs = require('fs');
  const schema = fs.readFileSync(path.join(__dirname, '..', 'vehigo-php', 'schema.sql'), 'utf8');
  // Split by semicolons and execute each statement
  const stmts = schema.split(';').filter(s => s.trim().length > 0);
  for (const stmt of stmts) {
    try {
      await turso.execute({ sql: stmt });
    } catch (e) {
      // Table might already exist - ignore
      console.log('  (skip) ' + e.message.substring(0, 60));
    }
  }
  console.log('Schema loaded');

  // 2. Seed data
  const tables = [
    'vehicle_categories', 'vehicles', 'vehicle_pricing', 'pricing_packages',
    'vehicle_gallery', 'vehicle_features', 'users', 'drivers',
    'bookings', 'banners', 'coupons', 'offers', 'reviews',
    'pickup_locations', 'tour_packages', 'tour_addons', 'settings'
  ];

  for (const table of tables) {
    const rows = local.prepare(`SELECT * FROM "${table}"`).all();
    if (rows.length === 0) continue;
    console.log(`  Seeding ${table}... (${rows.length} rows)`);

    for (const row of rows) {
      const cols = Object.keys(row);
      const vals = cols.map(c => row[c]);
      const placeholders = cols.map(() => '?').join(',');
      try {
        await turso.execute({
          sql: `INSERT INTO "${table}" ("${cols.join('","')}") VALUES (${placeholders})`,
          args: vals.map(v => v === null ? undefined : v)
        });
      } catch (e) {
        console.log(`    Error row: ${e.message.substring(0, 80)}`);
      }
    }
  }
  console.log('All data seeded!');
}

main().catch(console.error);
