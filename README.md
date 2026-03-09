# AttendQR — Google Forms + QR Code Attendance System

## 📁 File Structure
```
attendqr/
├── index.php                  # Front controller / router
├── database.sql               # Run this first to set up DB
├── includes/
│   ├── config.php             # App config, session, helpers
│   ├── database.php           # PDO singleton + query helpers
│   ├── layout_header.php      # Sidebar + topbar HTML
│   └── layout_footer.php      # Scripts + closing tags
├── pages/
│   ├── login.php              # Login page (standalone)
│   ├── dashboard.php          # Dashboard with stats + charts
│   ├── events.php             # Event CRUD
│   ├── forms.php              # Google Forms embedding
│   ├── scanner.php            # QR Code scanner + log
│   ├── attendees.php          # Attendee list + export
│   ├── qrcodes.php            # QR generator + log
│   ├── reports.php            # Analytics + charts
│   └── logs.php               # Audit trail
└── assets/
    ├── css/style.css          # Full stylesheet
    └── js/app.js              # Scanner, QR gen, charts, modals
```

## 🚀 Setup Instructions

### 1. Requirements
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Web server: Apache / Nginx
- PDO extension enabled

### 2. Database Setup
```sql
-- Import the schema:
mysql -u root -p < database.sql
```
Or run it via phpMyAdmin.

### 3. Configure
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'attendqr');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
```

### 4. Web Server
**Apache** — place in `htdocs/attendqr/` and access at `http://localhost/attendqr/`

**Nginx** — point document root to `/attendqr/` with PHP-FPM.

**PHP Built-in Server (development)**:
```bash
cd attendqr
php -S localhost:8000
```
Then open: http://localhost:8000

### 5. Login
Default credentials (from seed data):
- **Email:** admin@attendqr.com
- **Password:** password

> ⚠️ Change the password after first login!

## 🔐 Security Notes
- All passwords hashed with `password_hash()` (bcrypt)
- CSRF tokens on all forms
- PDO prepared statements (no SQL injection)
- Session-based authentication

## 📧 Email Setup
Install PHPMailer via Composer:
```bash
composer require phpmailer/phpmailer
```
Then configure SMTP in `includes/config.php`.

## 🔲 QR Code Generation
Client-side using `qrcodejs` library (no server dependency needed).
For server-side generation, install:
```bash
composer require endroid/qr-code
```

## 📦 Optional: Composer Setup
```bash
composer init
composer require phpmailer/phpmailer endroid/qr-code
```

## 🗂️ User Stories Implemented
| Epic | Story | Status |
|------|-------|--------|
| EP-01 | US-01: Embed Google Form | ✅ |
| EP-01 | US-02: Manage Multiple Forms | ✅ |
| EP-02 | US-03: Auto-generate QR Code | ✅ |
| EP-02 | US-04: Email QR to Gmail | ✅ (UI ready, needs PHPMailer) |
| EP-03 | US-05: QR Scanner | ✅ |
| EP-03 | US-06: Validate Attendance | ✅ |
| EP-04 | US-07: Admin Dashboard | ✅ |
