# TripAny White-Label Deployment Guide

## Quick Start

```bash
node scripts/setup.js
```

Answer the prompts to brand the app (name, colors, WhatsApp, admin creds, etc.).
Then follow the section below for your chosen hosting platform.

---

## 1. Vercel (Serverless — Recommended)

### Prerequisites
- Node.js 18+
- Vercel account + CLI (`npm i -g vercel`)
- Turso account + database

### Steps

```bash
# 1. Brand the app
node scripts/setup.js
# Choose option 1 (Vercel) when prompted

# 2. Set environment variables in Vercel dashboard
# Go to Project > Settings > Environment Variables and add:
# - TURSO_DB_URL
# - TURSO_DB_TOKEN
# - SESSION_SECRET
# - ADMIN_USERNAME
# - ADMIN_PASSWORD

# 3. Deploy
git add -A
git commit -m "brand: <YourBrand>"
git push
# Vercel auto-deploys
```

### File Structure (Vercel)
```
/          → vercel.json routes to api/server.js
/admin/    → admin SPA (served as static from /public)
/api/      → Node.js serverless function
/mobile/   → mobile PWA → redirected to / by vercel.json
```

### Environment Variables
| Variable | Description |
|---|---|
| `TURSO_DB_URL` | Turso database URL (from `turso db show`) |
| `TURSO_DB_TOKEN` | Turso auth token (from `turso db tokens create`) |
| `SESSION_SECRET` | Random string for admin token generation |
| `ADMIN_USERNAME` | Admin panel login username |
| `ADMIN_PASSWORD` | Admin panel login password |

---

## 2. Hostinger / cPanel (Shared Hosting)

### Prerequisites
- Hostinger / any cPanel hosting account
- PHP 8.0+
- MySQL database (created via cPanel)

### Steps

```bash
# 1. Brand the app
node scripts/setup.js
# Choose option 2 (Hostinger) when prompted

# 2. Import MySQL schema
# Open phpMyAdmin → select your database → Import → choose schema-mysql.sql

# 3. Configure database credentials
# Edit config/brand.php → add your MySQL host/db/user/pass

# 4. Upload files
# Use FTP or cPanel File Manager
# Upload everything in /vehigo-php/ to public_html/

# 5. Set up subdomain for admin (optional but recommended)
# cPanel → Subdomains → admin.yourdomain.com → /public_html/admin/

# 6. Update config/brand.php with your MySQL credentials
```

### File Structure (Hostinger)
```
public_html/
├── index.php          → Router / entry point
├── .htaccess          → Apache rewrite rules (set up)
├── config/brand.php   → Brand + DB config (UPDATE THIS)
├── mobile/            → Mobile PWA
├── admin/             → Admin SPA (served by PHP or static)
├── api/               → PHP API handlers
└── assets/            → CSS, JS, images
```

### Required .htaccess Rules (already in .htaccess)
```apache
RewriteEngine On
RewriteRule ^admin/?$ admin/index.html [L]
RewriteRule ^mobile/?$ mobile/index.html [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^api/(.*)$ api/index.php [QSA,L]
```

---

## 3. VPS (DigitalOcean, Linode, AWS EC2)

### Prerequisites
- Ubuntu 22.04+ VPS
- Node.js 18+ (`curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash - && sudo apt install -y nodejs`)
- PM2 (`npm i -g pm2`)
- Nginx (`sudo apt install nginx`)
- MySQL 8+ (`sudo apt install mysql-server`)
- PHP 8.x + MySQL extension (`sudo apt install php8.1-mysql`)
- Domain pointed to VPS IP
- SSL cert (Certbot: `sudo apt install certbot python3-certbot-nginx`)

### Steps

```bash
# 1. Brand the app (local machine)
node scripts/setup.js
# Choose option 3 (VPS) when prompted

# 2. Copy files to VPS
scp -r . user@your-vps-ip:/var/www/your-brand/

# 3. Install dependencies & start Node API
ssh user@your-vps-ip
cd /var/www/your-brand
npm install --production
pm2 start ecosystem.config.js
pm2 save
sudo env PATH=$PATH:/usr/bin pm2 startup systemd -u user --hp /home/user

# 4. Configure Nginx
sudo cp nginx.conf /etc/nginx/sites-available/your-brand
sudo ln -s /etc/nginx/sites-available/your-brand /etc/nginx/sites-enabled/
# Edit nginx.conf → replace yourdomain.com
sudo nginx -t
sudo systemctl reload nginx

# 5. SSL
sudo certbot --nginx -d yourdomain.com -d admin.yourdomain.com

# 6. Import MySQL schema
mysql -u root -p your_db < schema-mysql.sql

# 7. Create config/brand.php with MySQL credentials
# Edit /var/www/your-brand/config/brand.php
```

### File Structure (VPS)
```
/var/www/your-brand/
├── api/server.js      → Node.js API (managed by PM2)
├── vehigo-php/        → PHP app (served by Nginx → PHP-FPM)
│   ├── mobile/        → Mobile PWA
│   ├── config/        → PHP config
│   └── ...
├── admin/             → Admin SPA
├── nginx.conf         → Nginx virtual host config
├── ecosystem.config.js → PM2 process config
└── brand.config.json  → Master brand config
```

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│                 brand.config.json                    │
│              (master — edit this first)             │
└──────────┬─────────────────────┬───────────────────┘
           │                     │
           ▼                     ▼
┌───────────────────┐   ┌───────────────────┐
│   Mobile PWA      │   │   Admin SPA      │
│ (vehigo-php/      │   │ (admin/          │
│  mobile/)         │   │  index.html)     │
│                   │   │                   │
│ ● BRAND injected  │   │ ● BRAND injected │
│ ● Dynamic colors  │   │ ● Dynamic colors │
│ ● Dynamic strings │   │ ● Dynamic title  │
│ ● WhatsApp button │   │ ● Dynamic creds  │
└───────────────────┘   └───────────────────┘
           │                     │
           ▼                     ▼
┌─────────────────────────────────────────────────────┐
│                  API Layer                          │
│  ┌──────────┐  ┌──────────┐  ┌───────────────────┐ │
│  │ Vercel   │  │ Hostinger│  │ VPS (Node+PHP)   │ │
│  │ Node.js  │  │ PHP 8.x  │  │ Node.js + PM2    │ │
│  │ (server- │  │ (api/    │  │ + Nginx + PHP-FPM│ │
│  │ less)    │  │ index.php)│  │                   │ │
│  └──────────┘  └──────────┘  └───────────────────┘ │
└─────────────────────────────────────────────────────┘
           │                     │
           ▼                     ▼
┌───────────────────┐   ┌───────────────────┐
│     Database       │   │   External APIs  │
│  ┌─────────────┐   │   │ ┌─────────────┐  │
│  │ Turso       │   │   │ │ Google Maps │  │
│  │ (SQLite)    │   │   │ │ WhatsApp    │  │
│  ├─────────────┤   │   │ └─────────────┘  │
│  │ MySQL       │   │   │                   │
│  │ (Hostinger) │   │   │                   │
│  ├─────────────┤   │   │                   │
│  │ MySQL       │   │   │                   │
│  │ (VPS)       │   │   │                   │
│  └─────────────┘   │   └───────────────────┘
└───────────────────┘
```

---

## Brand Config Reference

### `brand.config.json`

```json
{
  "brand": {
    "name": "YourBrand",
    "shortName": "Brand",
    "tagline": "Premium Vehicle Rental",
    "logoText": "Y",
    "copyright": "© 2025 YourBrand"
  },
  "theme": {
    "primary": "#38BDF8",
    "secondary": "#BAE6FD",
    "gradient": "linear-gradient(135deg, #38BDF8, #7DD3FC)"
  },
  "admin": {
    "username": "admin",
    "password": "admin123",
    "sessionSecret": "random-hex-string"
  },
  "contact": {
    "whatsapp": "919876543210",
    "supportEmail": "support@yourbrand.com"
  },
  "maps": {
    "apiKey": "your-google-maps-key"
  },
  "storage": {
    "prefix": "yourbrand"
  },
  "defaults": {
    "location": "DefaultCity",
    "pincode": "123456",
    "testUser": {
      "name": "John Doe",
      "phone": "9876543210",
      "password": "user123"
    }
  }
}
```

---

## Database Schema

See `schema-mysql.sql` for the complete MySQL schema.

### Key Tables
- `vehicles` — Fleet inventory
- `drivers` — Driver profiles
- `categories` — Vehicle categories (sedan, SUV, etc.)
- `bookings` — Customer bookings
- `pricing` — Vehicle pricing rules
- `tours` — Tour packages
- `tour_addons` — Tour add-ons
- `coupons` — Discount coupons
- `banners` — Homepage banners
- `offers` — Special offers
- `sos_alerts` — Emergency alerts
- `notifications` — Push notifications
- `users` — Registered users
- `user_addresses` — User saved addresses
- `settings` — App settings
- `contact_messages` — Contact form submissions
- `fuel_log` — Fuel consumption tracking
- `maintenance` — Vehicle maintenance records
- `cancellations` — Booking cancellations
- `reviews` — Customer reviews

---

## Troubleshooting

### Vercel — "Module not found"
- Run `npm install` locally first
- Ensure `package.json` has only `@libsql/client` in dependencies
- `.npmrc` with `omit=dev` prevents devDependencies from being installed

### Vercel — "Error: Cannot find module better-sqlite3"
- `better-sqlite3` must be in `devDependencies`, not `dependencies`
- `.npmrc` must contain `omit=dev`
- Turso/LibSQL is the production database driver

### Hostinger — "404 Not Found"
- Ensure `.htaccess` is uploaded and visible in public_html/
- Enable `mod_rewrite` in cPanel → Select PHP Version → Options → check mod_rewrite

### VPS — "502 Bad Gateway"
- Check PM2 is running: `pm2 list`
- Check Nginx config: `sudo nginx -t`
- Check Nginx → PM2 proxy: ensure proxy_pass `http://localhost:3000` matches PM2 port

### VPS — "MySQL connection refused"
- Ensure MySQL is running: `sudo systemctl status mysql`
- Check MySQL user permissions: `GRANT ALL PRIVILEGES ON your_db.* TO 'your_user'@'localhost';`
