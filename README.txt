ArtisanConnect NG — Deployment Guide (InfinityFree / cPanel Shared Hosting)
=============================================================================

FILES:
  config.php    → Database, session, helpers, shared CSS/layout
  index.php     → Homepage + artisan search & discovery
  auth.php      → Login, registration, logout
  profile.php   → Public artisan profile, portfolio, booking form
  dashboard.php → User dashboard (artisan + customer)
  messages.php  → Real-time messaging system
  admin.php     → Admin panel (stats, verification, user management)

DEPLOYMENT STEPS:
  1. Log into your InfinityFree / cPanel control panel
  2. Open phpMyAdmin → Create database "artisanconnect"
  3. Import artisanconnect.sql (Import tab)
  4. Open config.php and update:
       DB_HOST → your InfinityFree MySQL host (e.g. sql123.infinityfree.com)
       DB_NAME → your database name (prefix_artisanconnect)
       DB_USER → your cPanel MySQL username
       DB_PASS → your MySQL password
  5. Upload ALL files (including the /uploads/ folder) to public_html/
  6. CHMOD /uploads/ to 755
  7. Visit your site: yoursite.infinityfreeapp.com

DEFAULT CREDENTIALS (from the SQL seed data):
  Admin:    admin@artisanconnect.ng  / Admin@1234
  Artisan:  chukwu@demo.ng          / Test@1234
  Customer: customer@demo.ng        / Test@1234
NB: if the credentails are not working, change credentials
FEATURES:
  ✓ Artisan registration with trade & location
  ✓ Portfolio image upload & gallery
  ✓ Service listing (fixed / hourly / negotiable)
  ✓ Location-based search with filters
  ✓ Customer booking system (pending → accepted → completed)
  ✓ Star review & rating system (auto-recalculates artisan average)
  ✓ Real-time messaging (5s polling, no WebSocket needed)
  ✓ Admin verification panel
  ✓ CSRF protection on all forms
  ✓ PDO prepared statements (SQL injection prevention)
  ✓ Bcrypt password hashing
  ✓ Mobile-responsive (CSS3 Flexbox/Grid)

NOTE: Create /uploads/portfolio/ and /uploads/avatar/ folders on your server
      if they are not created automatically.

Project Credit: 
Sarverun Simeon Tertese 
Peter Nice Renet
March 2026
