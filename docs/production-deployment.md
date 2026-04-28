# Production Deployment Guide

You have successfully built the frontend for production! The Next.js frontend is now compiled and ready to be hosted, and the backend is prepped to be optimized. 

Here is everything you need to do to get the application running on your production server:

## 1. Deploying the Backend (Laravel 12)

**Files to upload to the server:**
Upload the entire `backend/` folder (excluding `node_modules`, `vendor`, and `.env` if you have a separate production `.env`).

**On your production server, navigate to the backend directory and run:**
```bash
# 1. Install PHP dependencies (no dev packages)
composer install --optimize-autoloader --no-dev

# 2. Set up environment (if not already done)
cp .env.example .env
php artisan key:generate

# 3. Optimize Laravel for performance
php artisan optimize
php artisan view:cache
php artisan event:cache

# 4. Run database migrations (Ensure your production DB credentials are in .env)
php artisan migrate --force

# 5. Make sure the storage folder is linked and has proper permissions
php artisan storage:link
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## 2. Deploying the Frontend (Next.js)

**Files to upload to the server:**
Upload the following files and directories from your `frontend/` folder:
- `.next` (the compiled build folder you just generated)
- `public`
- `package.json`
- `package-lock.json`
- `next.config.ts`
- `.env.production` (or setup your `.env.local` appropriately for production API variables)

**On your production server, navigate to the frontend directory and run:**
```bash
# 1. Install production Node dependencies
npm ci --legacy-peer-deps

# 2. Start the application using PM2 (or another process manager)
npm install -g pm2
pm2 start npm --name "timedoor-frontend" -- start
pm2 save
pm2 startup
```

## 3. Background Services

Remember to manage the background tasks as well, as discussed earlier.

**Queue Worker (Supervisor)**
You must configure Supervisor to keep the Laravel Queue Worker alive.
```bash
php artisan queue:work --sleep=3 --tries=3
```

**Scheduler (Cron)**
Add the scheduler to your server's crontab (`crontab -e`):
```bash
* * * * * cd /path/to/your/backend && php artisan schedule:run >> /dev/null 2>&1
```

## 4. Web Server (Nginx Setup Example)

Optionally, you will want a reverse proxy like Nginx to serve the site over port 80/443 pointing to your Next.js app running on port 3000, and your Laravel API running through PHP-FPM.

Enjoy your freshly deployed application!
