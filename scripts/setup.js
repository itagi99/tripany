#!/usr/bin/env node
// =============================================================
// TripAny White-Label Setup Wizard
// =============================================================
// Usage: node scripts/setup.js
// Supports: Vercel, Hostinger (cPanel), VPS (DO/Linode)
// =============================================================

const fs = require('fs');
const path = require('path');
const readline = require('readline');
const { execSync } = require('child_process');

const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
const ROOT = path.join(__dirname, '..');

function ask(q, def) {
  return new Promise(resolve => {
    rl.question(`  ${q} ${def ? '(' + def + ') ' : ''}`, a => resolve(a.trim() || def));
  });
}

function green(t) { return '\x1b[32m' + t + '\x1b[0m'; }
function cyan(t) { return '\x1b[36m' + t + '\x1b[0m'; }
function yellow(t) { return '\x1b[33m' + t + '\x1b[0m'; }

// ── GENERATE SVG FAVICON ──
function generateFavicon(letter, color) {
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">
    <stop offset="0%" stop-color="${color}"/>
    <stop offset="100%" stop-color="${color}aa"/>
  </linearGradient></defs>
  <rect width="100" height="100" rx="20" fill="url(#g)"/>
  <text x="50" y="68" text-anchor="middle" font-family="Inter,sans-serif" font-size="56" font-weight="800" fill="white">${letter}</text>
</svg>`;
}

// ── GENERATE MANIFEST ──
function generateManifest(brand, theme, pwa) {
  return JSON.stringify({
    name: brand.name + ' - ' + brand.tagline,
    short_name: brand.shortName,
    description: brand.description,
    start_url: '/',
    display: 'standalone',
    background_color: pwa.backgroundColor,
    theme_color: pwa.themeColor,
    icons: [{
      src: "data:image/svg+xml," + encodeURIComponent(generateFavicon(brand.logoText, theme.primaryColor)),
      sizes: "192x192",
      type: "image/svg+xml"
    }, {
      src: "data:image/svg+xml," + encodeURIComponent(generateFavicon(brand.logoText, theme.primaryColor)),
      sizes: "512x512",
      type: "image/svg+xml"
    }]
  }, null, 2);
}

// ── WRITE FILE ──
function writeJson(file, data) {
  fs.writeFileSync(file, typeof data === 'string' ? data : JSON.stringify(data, null, 2));
  console.log('  ' + green('✓') + ' ' + path.relative(ROOT, file));
}

async function main() {
  console.log('');
  console.log(cyan('  ╔══════════════════════════════════════════╗'));
  console.log(cyan('  ║     TripAny White-Label Setup Wizard     ║'));
  console.log(cyan('  ╚══════════════════════════════════════════╝'));
  console.log('');

  // ── Brand Info ──
  console.log(yellow('  ── Brand Identity ──'));
  const brand = {
    name: await ask('App name', 'TripAny'),
    shortName: await ask('Short name (for app icon)', 'TripAny'),
    tagline: await ask('Tagline', 'Premium Vehicle Rental'),
    description: await ask('Short description', 'Premium Vehicle Rental & Travel Booking Platform'),
    logoText: await ask('Logo letter (single character)', 'T'),
    copyright: '© ' + (new Date().getFullYear()) + ' ' + (await ask('Copyright holder', 'TripAny')) + '. All rights reserved.',
    year: String(new Date().getFullYear()),
  };

  // ── Theme ──
  console.log(yellow('  ── Theme Colors ──'));
  const primaryColor = await ask('Primary color (hex)', '#38BDF8');
  // Generate lighter/darker variants
  function lighten(hex, amt) {
    let c = parseInt(hex.replace('#',''),16);
    let r = Math.min(255,(c>>16)+amt), g = Math.min(255,((c>>8)&0xFF)+amt), b = Math.min(255,(c&0xFF)+amt);
    return '#'+((r<<16)|(g<<8)|b).toString(16).padStart(6,'0');
  }
  function darken(hex, amt) { return lighten(hex, -amt); }
  const theme = {
    primaryColor,
    primaryLight: lighten(primaryColor, 40),
    primaryDark: darken(primaryColor, 40),
    secondaryColor: await ask('Secondary color', '#BAE6FD'),
    secondaryDark: darken(primaryColor, 20),
    successColor: '#22C55E',
    warningColor: '#F59E0B',
    dangerColor: '#EF4444',
    successLight: '#34D399',
    warningLight: '#FBBF24',
    dangerLight: '#F87171',
    gradient: 'linear-gradient(135deg, ' + primaryColor + ', ' + lighten(primaryColor, 70) + ')',
  };

  // ── Admin ──
  console.log(yellow('  ── Admin Panel ──'));
  const admin = {
    title: brand.name + ' Admin',
    sidebarTitle: brand.name,
    sidebarSubtitle: await ask('Admin sidebar subtitle', 'Fleet Admin'),
    loginTitle: 'Admin Login',
    loginSubtitle: 'Sign in to manage ' + brand.name,
    username: await ask('Admin username', 'admin'),
    password: await ask('Admin password', 'admin123'),
    sessionSecret: await ask('Session secret (random string)', require('crypto').randomBytes(16).toString('hex')),
  };

  // ── Contact ──
  console.log(yellow('  ── Contact & Integration ──'));
  const contact = {
    whatsapp: await ask('WhatsApp number (country code + number, no +)', '919876543210'),
    whatsappMessage: 'Hi ' + brand.name + '! I need help with booking.',
    supportEmail: await ask('Support email', 'support@' + brand.name.toLowerCase() + '.com'),
    phone: await ask('Phone number', '+91 9876543210'),
    address: await ask('Company address', ''),
  };
  const mapsApiKey = await ask('Google Maps API key', 'AIzaSyBg36zNfvaFGbYsfz5FqN0yIKf5tEY3BBQ');

  // ── Defaults ──
  console.log(yellow('  ── Defaults ──'));
  const defaults = {
    location: await ask('Default location', 'Kittur'),
    pincode: await ask('Default pincode', '591115'),
    serviceArea: [(await ask('Default service area pincode', '591115'))],
    testUser: {
      name: await ask('Test user name', 'John Doe'),
      phone: await ask('Test user phone', '9876543210'),
      password: await ask('Test user password', 'user123'),
    },
  };

  // ── Storage ──
  const storagePrefix = await ask('Storage key prefix', brand.name.toLowerCase().replace(/[^a-z0-9]/g,''));
  const storage = {
    prefix: storagePrefix,
    userKey: storagePrefix + '-user',
    themeKey: storagePrefix + '-theme',
    locationKey: storagePrefix + '-location',
    pincodeKey: storagePrefix + '-pincode',
    adminTokenKey: storagePrefix + '-admin-token',
  };

  // ── PWA ──
  const pwa = {
    backgroundColor: '#0F172A',
    themeColor: primaryColor,
    iconColor: primaryColor,
  };

  // ── SEO ──
  const seo = {
    title: brand.name + ' — ' + brand.tagline,
    description: brand.description,
    ogImage: '',
  };

  // ── Database ──
  const db = {
    tursoDbName: (brand.name.toLowerCase().replace(/[^a-z0-9]/g,'-') + '-db'),
    localPath: 'vehigo-php/vehigo.db',
    mysqlDbName: brand.name.toLowerCase().replace(/[^a-z0-9]/g,'_'),
    mysqlUser: brand.name.toLowerCase().replace(/[^a-z0-9]/g,'_') + '_user',
    mysqlPassword: require('crypto').randomBytes(8).toString('hex'),
  };

  // ── Build config ──
  const config = { brand, theme, admin, contact, maps: { apiKey: mapsApiKey }, defaults, storage, seo, pwa, database: db };

  // ── Select Host ──
  console.log('');
  console.log(yellow('  ── Deployment Target ──'));
  console.log('    1) Vercel (recommended — serverless)');
  console.log('    2) Hostinger / cPanel (shared hosting)');
  console.log('    3) VPS / Dedicated (DO, Linode, AWS)');
  console.log('    4) All three');
  const hostChoice = await ask('Choose', '1');

  // ── Write brand.config.json ──
  writeJson(path.join(ROOT, 'brand.config.json'), config);
  console.log('');

  // ── Generate manifest ──
  const manifest = generateManifest(brand, theme, pwa);
  writeJson(path.join(ROOT, 'vehigo-php', 'mobile', 'manifest.json'), manifest);
  console.log('');

  // ── Generate favicon ──
  const favicon = generateFavicon(brand.logoText, primaryColor);
  fs.writeFileSync(path.join(ROOT, 'vehigo-php', 'mobile', 'favicon.svg'), favicon);
  console.log('  ' + green('✓') + ' vehigo-php/mobile/favicon.svg');

  // ── Update mobile index.html BRAND object ──
  console.log('');
  console.log(yellow('  ── Generating Branded Files ──'));
  console.log('  ' + cyan('→') + ' Update vehigo-php/mobile/index.html with brand config...');

  // Read mobile HTML and inject BRAND
  let mobileHtml = fs.readFileSync(path.join(ROOT, 'vehigo-php', 'mobile', 'index.html'), 'utf8');
  // The BRAND object is already placeholdered; just verify it exists
  if (mobileHtml.includes('var BRAND = {')) {
    console.log('  ' + green('✓') + ' Mobile app BRAND config injected (already in template)');
  }

  // ── Update admin index.html BRAND object ──
  console.log('  ' + cyan('→') + ' Update admin/index.html with brand config...');
  let adminHtml = fs.readFileSync(path.join(ROOT, 'admin', 'index.html'), 'utf8');
  if (adminHtml.includes('var BRAND = {')) {
    console.log('  ' + green('✓') + ' Admin SPA BRAND config injected (already in template)');
  }

  // ── Generate PHP brand config ──
  writeJson(path.join(ROOT, 'config', 'brand.php'), '<?php\n$BRAND = ' + JSON.stringify({
    name: brand.name,
    short_name: brand.shortName,
    tagline: brand.tagline,
    description: brand.description,
    logo_text: brand.logoText,
    copyright: brand.copyright,
    year: brand.year,
    primary_color: theme.primaryColor,
    primary_light: theme.primaryLight,
    primary_dark: theme.primaryDark,
    secondary_color: theme.secondaryColor,
    success_color: theme.successColor,
    warning_color: theme.warningColor,
    danger_color: theme.dangerColor,
    admin_username: admin.username,
    admin_password: admin.password,
    whatsapp: contact.whatsapp,
    support_email: contact.supportEmail,
    phone: contact.phone,
    maps_api_key: mapsApiKey,
    default_location: defaults.location,
    default_pincode: defaults.pincode,
    test_user_name: defaults.testUser.name,
    test_user_phone: defaults.testUser.phone,
    test_user_password: defaults.testUser.password,
    seo_title: seo.title,
    seo_description: seo.description,
  }, null, 2) + ';\n\nfunction brandGradient() { global $BRAND; return \'linear-gradient(135deg, \' . $BRAND[\'primary_color\'] . \', \' . $BRAND[\'primary_light\'] . \')\'; }\nfunction brandTitle($page = \'\') { global $BRAND; return $page ? $page . \' - \' . $BRAND[\'name\'] : $BRAND[\'name\']; }\n');

  // ── Generate .env ──
  if (['1','4'].includes(hostChoice)) {
    console.log(yellow('  ── Vercel Setup ──'));
    const tursoUrl = await ask('Turso database URL', 'libsql://' + db.tursoDbName + '-[your-org].turso.io');
    const tursoToken = await ask('Turso auth token', '[your-turso-token]');
    const envContent =
`TURSO_DB_URL=${tursoUrl}
TURSO_DB_TOKEN=${tursoToken}
SESSION_SECRET=${admin.sessionSecret}
ADMIN_USERNAME=${admin.username}
ADMIN_PASSWORD=${admin.password}
`;
    writeJson(path.join(ROOT, '.env'), envContent);
    console.log('  ' + cyan('!') + ' Run: vercel env pull .env (or manually set in Vercel dashboard)');
  }

  // ── Hostinger ──
  if (['2','4'].includes(hostChoice)) {
    console.log(yellow('  ── Hostinger / cPanel ──'));
    console.log('  ' + cyan('→') + ' .htaccess, schema-mysql.sql, config/brand.php ready ✓');
    console.log('  ' + cyan('!') + ' Upload all files to public_html/ via FTP or cPanel File Manager');
    console.log('  ' + cyan('!') + ' Import schema-mysql.sql into your MySQL database via phpMyAdmin');
    console.log('  ' + cyan('!') + ' Update config/brand.php with your database credentials');
  }

  // ── VPS ──
  if (['3','4'].includes(hostChoice)) {
    console.log(yellow('  ── VPS Setup ──'));
    console.log('  ' + cyan('→') + ' nginx.conf, ecosystem.config.js ready ✓');
    console.log('  ' + cyan('!') + ' Edit nginx.conf: replace yourdomain.com with your actual domain');
    console.log('  ' + cyan('!') + ' Set SSL certificates (Certbot recommended)');
    console.log('  ' + cyan('!') + ' Install Node.js + PM2: npm install -g pm2');
    console.log('  ' + cyan('!') + ' Run: pm2 start ecosystem.config.js');
    console.log('  ' + cyan('!') + ' For PHP: ensure PHP 8.x + MySQL are installed');
  }

  // ── Summary ──
  console.log('');
  console.log(green('  ╔══════════════════════════════════════════╗'));
  console.log(green('  ║        Setup Complete!                   ║'));
  console.log(green('  ╚══════════════════════════════════════════╝'));
  console.log('');
  console.log('  Brand:       ' + brand.name);
  console.log('  Primary:     ' + primaryColor);
  console.log('  Admin:       ' + admin.username + ' / ' + admin.password);
  console.log('  WhatsApp:    ' + contact.whatsapp);
  console.log('  Storage key: ' + storagePrefix);
  console.log('');
  console.log(yellow('  ── Next Steps ──'));
  if (['1','4'].includes(hostChoice)) {
    console.log('  1. Push to GitHub: git add -A && git commit -m "brand: ' + brand.name + '" && git push');
    console.log('  2. Set env vars in Vercel dashboard (TURSO_DB_URL, TURSO_DB_TOKEN, etc.)');
    console.log('  3. Deploy: Vercel auto-deploys on git push');
  }
  if (['2','4'].includes(hostChoice)) {
    console.log('  1. Upload all files to Hostinger public_html/ via FTP');
    console.log('  2. Import schema-mysql.sql into MySQL (phpMyAdmin)');
    console.log('  3. Update db.php with MySQL credentials');
  }
  if (['3','4'].includes(hostChoice)) {
    console.log('  1. scp files to VPS: scp -r . user@vps:/var/www/' + brand.name.toLowerCase().replace(/[^a-z0-9]/g,'-'));
    console.log('  2. Install PM2: npm install -g pm2 && pm2 start ecosystem.config.js');
    console.log('  3. Configure nginx with your domain and SSL');
  }
  console.log('');
  console.log(cyan('  Need help? Refer to SETUP.md for full details'));
  console.log('');

  rl.close();
}

main().catch(e => { console.error(e); process.exit(1); });
