// =============================================================
// PM2 Ecosystem Config for TripAny White-Label (VPS Node.js)
// =============================================================

module.exports = {
  apps: [{
    name: 'tripany-api',
    script: 'api/server.js',
    instances: 1,
    exec_mode: 'fork',
    watch: false,
    env: {
      NODE_ENV: 'production',
      PORT: 3001,
      TURSO_DB_URL: process.env.TURSO_DB_URL || '',
      TURSO_DB_TOKEN: process.env.TURSO_DB_TOKEN || '',
      SESSION_SECRET: process.env.SESSION_SECRET || 'change-me-in-production',
      ADMIN_USERNAME: process.env.ADMIN_USERNAME || 'admin',
      ADMIN_PASSWORD: process.env.ADMIN_PASSWORD || 'admin123',
    },
    error_file: 'logs/err.log',
    out_file: 'logs/out.log',
    log_date_format: 'YYYY-MM-DD HH:mm:ss',
    max_restarts: 10,
    restart_delay: 5000,
  }]
};
