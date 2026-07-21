# TripAny - Complete Setup & Deployment Guide

## 1. Project File Structure (Only Essential Files)

```
vehigo/
├── vehigo-php/                    # PHP backend (optional if using Node server)
│   ├── api/
│   │   ├── index.php              # Mobile API (session-based, ?action=xxx)
│   │   └── rest.php               # REST API (Bearer token, /api/* routes)
│   ├── admin/                     # Admin panel UI (13 PHP files)
│   ├── driver/                    # Driver app UI (8 PHP files)
│   ├── mobile/
│   │   ├── index.html             # Main mobile app (single-page)
│   │   ├── assets/app.css         # Mobile app styles
│   │   └── sw.js                  # Service worker
│   ├── uploads/                   # Uploaded images (vehicles, banners, etc.)
│   ├── db.php                     # Database connection + migration
│   ├── helpers.php                # Utility functions
│   ├── router.php                 # Dev server router (php -S)
│   ├── schema.sql                 # Database DDL
│   ├── seed.php                   # Sample data seeder
│   └── vehigo.db                  # SQLite database
├── server/
│   └── index.js                   # Node.js Express server (stateless, Turso-ready)
├── package.json                   # Node.js dependencies
└── GUIDE.md                       # This file
```

**Essential for deployment:**
- **Option A (PHP):** `api/index.php`, `api/rest.php`, `db.php`, `schema.sql`, `mobile/index.html`, `mobile/assets/app.css`, `uploads/`
- **Option B (Node.js):** `server/index.js`, `mobile/index.html`, `mobile/assets/app.css`, `uploads/`

---

## 2. Database Schema (22 Tables)

### Core Tables
| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `users` | Customers | id, name, phone, password_hash, api_token, is_verified |
| `drivers` | Drivers | id, name, phone, password_hash, status, rating, api_token |
| `vehicles` | Rental vehicles | id, category_id, name, model, type, price_per_day, price_per_km, image, is_active |
| `vehicle_categories` | Vehicle categories | id, name, icon, active, sort_order |
| `vehicle_pricing` | Detailed pricing | vehicle_id, base_rate, extra_km_rate, security_deposit |
| `vehicle_gallery` | Vehicle images | vehicle_id, image_url, sort_order |
| `bookings` | All booking records | user_id, vehicle_id, driver_id, booking_ref, pickup/drop locations, fares, status |
| `notifications` | Push notifications | user_id, title, message, type, is_read |
| `reviews` | Vehicle ratings | vehicle_id, user_id, rating, comment |
| `wishlist` | Saved vehicles | user_id, vehicle_id |

### Tour & Promo Tables
| Table | Purpose |
|-------|---------|
| `tour_packages` | Tour packages (date, price, max participants) |
| `tour_bookings` | Tour booking records |
| `tour_addons` | Tour add-ons (water bottle, snacks, etc.) |
| `coupons` | Discount coupons |
| `offers` | Promotional offers |
| `banners` | Homepage carousel banners |

### Operational Tables
| Table | Purpose |
|-------|---------|
| `pickup_locations` | Predefined pickup spots |
| `pricing_packages` | Hourly/kilometer packages per vehicle |
| `sos_alerts` | Emergency alert records |
| `settings` | Key-value configuration |
| `driver_documents` | Driver document uploads |

Full DDL in `schema.sql` (285 lines).

---

## 3. Turso Database Migration

### Step 1: Install Turso CLI
```bash
# Windows (PowerShell)
winget install turso
# OR
npm install -g @turso/cli

# macOS / Linux
curl -sSfL https://get.tur.so/install.sh | bash
```

### Step 2: Create Turso Account & Database
```bash
# Login
turso auth login

# Create database
turso db create tripany-db

# Get connection URL
turso db show tripany-db --url
# Returns: libsql://tripany-db-<org>.turso.io

# Generate auth token
turso db create-token tripany-db
# Saves to: ~/.turso/tripany-db.token
```

### Step 3: Upload Schema & Data to Turso
```bash
# Method 1: Turso CLI (direct SQLite file upload)
turso db shell tripany-db < vehigo-php/schema.sql

# Then upload data from your local DB
# First dump as SQL:
sqlite3 vehigo-php/vehigo.db .dump > dump.sql
# Remove sqlite_sequence from dump, then:
turso db shell tripany-db < dump.sql

# Method 2: Using restore from URL
# Upload .db file to a public URL or use `turso db shell` interactively
```

### Step 4: Update Node.js Server to Use Turso
Install the Turso client:
```bash
npm install @libsql/client
```

Replace `server/index.js` database connection section:

```javascript
// OLD (SQLite local):
const Database = require('better-sqlite3');
const db = new Database('vehigo-php/vehigo.db');

// NEW (Turso):
const { createClient } = require('@libsql/client');
const db = createClient({
  url: process.env.TURSO_DB_URL || 'libsql://tripany-db-<org>.turso.io',
  authToken: process.env.TURSO_DB_TOKEN || 'your-auth-token',
});
```

**Query API change** (Turso is async, better-sqlite3 is sync):
```javascript
// OLD (sync):
const rows = db.prepare("SELECT * FROM users WHERE id=?").get(id);
const all = db.prepare("SELECT * FROM vehicles").all();

// NEW (async):
const result = await db.execute({ sql: "SELECT * FROM users WHERE id=?", args: [id] });
const rows = result.rows; // array of objects
// For single row:
const user = result.rows[0];
```

**Create a Turso-compatible `server/turso.js`:**

```javascript
const { createClient } = require('@libsql/client');
const path = require('path');
const fs = require('fs');

const TURSO_URL = process.env.TURSO_DB_URL;
const TURSO_TOKEN = process.env.TURSO_DB_TOKEN;

let db;

if (TURSO_URL && TURSO_TOKEN) {
  // Turso (production)
  db = createClient({
    url: TURSO_URL,
    authToken: TURSO_TOKEN,
  });
  console.log('Connected to Turso database');
} else {
  // SQLite fallback (local dev) using better-sqlite3
  const Database = require('better-sqlite3');
  const DB_PATH = path.join(__dirname, '..', 'vehigo-php', 'vehigo.db');
  
  // Wrap better-sqlite3 in async-compatible wrapper
  const sqlite3 = new Database(DB_PATH);
  sqlite3.pragma('journal_mode = WAL');
  sqlite3.pragma('foreign_keys = ON');
  
  db = {
    async execute({ sql, args }) {
      const stmt = sqlite3.prepare(sql);
      if (sql.trim().toUpperCase().startsWith('SELECT')) {
        return { rows: args ? stmt.all(...args) : stmt.all() };
      } else {
        stmt.run(...(args || []));
        return { rows: [] };
      }
    }
  };
  console.log('Connected to local SQLite database');
}

module.exports = db;
```

Usage in API handlers:
```javascript
const db = require('./turso');

async function handleVehiclesList(req, res) {
  const result = await db.execute({
    sql: "SELECT * FROM vehicles WHERE is_active=1 ORDER BY is_featured DESC",
  });
  res.json(result.rows);
}
```

---

## 4. Free Hosting Options

### Option A: All-in-One (Render + Turso) ⭐ RECOMMENDED

**Single Node.js backend serving both API + static files + Turso DB.**

1. Push code to GitHub
2. Sign up at [render.com](https://render.com) (free tier)
3. Create **New Web Service** → Connect your repo
4. Settings:
   - **Root Directory:** `vehigo` (or where `package.json` is)
   - **Build Command:** `npm install`
   - **Start Command:** `node server/index.js`
   - **Plan:** Free ($0/month)
5. Add Environment Variables:
   - `TURSO_DB_URL` = `libsql://tripany-db-<org>.turso.io`
   - `TURSO_DB_TOKEN` = (your generated token)
   - `PORT` = `10000`
6. Deploy → App live at `https://<app>.onrender.com`

**Limitations:** Free tier sleeps after 15 min inactivity; wakes on request (may take 30s).

### Option B: PHP Backend + Frontend Split

**Frontend** (mobile HTML/CSS/JS) → **Cloudflare Pages** (free, unlimited bandwidth)

1. Push `mobile/` folder to separate GitHub repo (or use subdirectory)
2. Sign up at [cloudflare.com](https://cloudflare.com)
3. Workers & Pages → Create → Connect to Git
4. Build settings: None (static files)
5. Deploy → `https://<project>.pages.dev`

**Backend** (PHP API) → **InfinityFree** or **AwardSpace** (free PHP hosting)

1. Sign up at [infinityfree.net](https://infinityfree.net) (free: 5GB disk, unmetered bandwidth)
2. Upload `api/`, `db.php`, `helpers.php`, `schema.sql`, `uploads/` via FTP/cPanel
3. The PHP API connects to Turso via `file_get_contents()` using Turso's HTTP API

### Option C: Fly.io + Turso (Never Sleeps)

[Fly.io](https://fly.io) free tier: 3 shared VMs, 256MB RAM, 3GB storage (never sleeps).

1. Install Fly CLI: `npm install -g @flyio/flyctl`
2. `fly launch` in the vehigo directory
3. Deploy: `fly deploy`

---

## 5. Turso HTTP API (PHP Fallback)

If you must keep PHP, use Turso's HTTP API directly (no special PHP extension needed):

```php
<?php
function tursoQuery($sql, $params = []) {
    $url = getenv('TURSO_DB_URL');  // libsql://tripany-db-<org>.turso.io
    $token = getenv('TURSO_DB_TOKEN');
    
    // Convert libsql:// URL to HTTPS REST endpoint
    $restUrl = str_replace('libsql://', 'https://', $url) . '/v2/pipeline';
    
    $body = json_encode([
        'requests' => [
            ['type' => 'execute', 'stmt' => ['sql' => $sql, 'args' => ['args' => $params]]]
        ]
    ]);
    
    $ch = curl_init($restUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $body,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}
```

Requires `curl` extension in PHP (usually enabled by default).

---

## 6. Quick Deploy Checklist

- [ ] Turso database created and schema loaded
- [ ] Turso URL and token added as environment variables
- [ ] Node.js server updated to use `@libsql/client`
- [ ] Static files (`mobile/index.html`, assets) included in deployment
- [ ] Google Maps API key added to the HTML (already in `mobile/index.html`)
- [ ] Upload directory (`uploads/`) writable or use CDN for images
- [ ] Service worker (`sw.js`) paths updated to match domain
- [ ] `PORT` environment variable set (Render uses 10000, default is 3000)

---

## 7. Turso Free Tier Limits

| Resource | Limit |
|----------|-------|
| Storage | 9 GB per database |
| Databases | 3 databases |
| Rows read | 1 billion / month |
| Rows written | 250 million / month |
| Locations | 30+ edge locations |
| Bandwidth | Included (no separate limit) |
| Snapshots | Daily automatic backups |

At ~100,000 API calls/day with ~10 rows read per call = ~30M rows read/month. Turso's free tier handles this easily.
