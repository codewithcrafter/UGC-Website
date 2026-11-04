# MY UGC VIDEO — Hybrid Dynamic Blog CMS & Admin System

A high-performance, secure PHP + MySQL dynamic blog and editorial CMS integrated with the existing HTML/CSS/Vanilla JS static website for **MY UGC VIDEO**.

---

## Architecture Overview

- **Core Website:** Pure static HTML5, CSS3, and Vanilla JavaScript (`index.html`, `about.html`, `services.html`, `contact.html`, `404.html`, `services/*.html`). Remains 100% untouched and framework-free.
- **Dynamic Blog:** PHP 8 + MySQL PDO dynamic blog engine (`blogs.php`, `blog.php`).
- **Admin CMS:** Secure, brand-consistent administrative content management system located at `/admin/`.
- **Database:** MySQL database named `my_ugc_video_cms` with versioned schema at `database/schema.sql`.

---

## Local XAMPP Setup Instructions

### 1. Project Directory in XAMPP
The website can be placed directly in or linked to `C:\xampp\htdocs\`:
```
C:\xampp\htdocs\UGC Website\
```
*(If the repository is located at `D:\ADITYA\UGC Website`, a directory junction links it directly to `C:\xampp\htdocs\UGC Website`).*

### 2. Start Apache and MySQL
1. Open the **XAMPP Control Panel** (`C:\xampp\xampp-control.exe`).
2. Start the **Apache** service (Port 80/443).
3. Start the **MySQL** service (Port 3306).

### 3. Database Import / Initialization
If setting up on a fresh machine:
1. Open phpMyAdmin (`http://localhost/phpmyadmin/`) or open a terminal.
2. Import `database/schema.sql` into MySQL:
   ```cmd
   mysql -u root < "C:\xampp\htdocs\UGC Website\database\schema.sql"
   ```
3. To migrate all original static blog articles automatically, run:
   ```
   http://localhost/UGC%20Website/database/migrate_existing_blogs.php?run=1
   ```

### 4. Create Initial Admin Account
1. Open the setup page in your browser:
   ```
   http://localhost/UGC%20Website/admin/setup.php
   ```
2. Enter your Name, Email, and a secure password (minimum 8 characters).
3. Once created, the setup script is **permanently locked** for security.

### 5. Access the Admin CMS Dashboard
- **Login URL:** `http://localhost/UGC%20Website/admin/login.php`
- **Dashboard URL:** `http://localhost/UGC%20Website/admin/dashboard.php`

---

## Key URLs

| Section | Local URL |
| :--- | :--- |
| **Homepage** | `http://localhost/UGC%20Website/index.html` |
| **Dynamic Blog Listing** | `http://localhost/UGC%20Website/blogs.php` (or `/blogs/`) |
| **Individual Blog Detail** | `http://localhost/UGC%20Website/blog.php?slug=what-is-ugc` (or `/blog/what-is-ugc/`) |
| **Admin Login** | `http://localhost/UGC%20Website/admin/login.php` |
| **Admin Dashboard** | `http://localhost/UGC%20Website/admin/dashboard.php` |
| **Create New Article** | `http://localhost/UGC%20Website/admin/posts/create.php` |
| **Categories** | `http://localhost/UGC%20Website/admin/categories/index.php` |
| **Media Library** | `http://localhost/UGC%20Website/admin/media/index.php` |

---

## Security Features

1. **Prepared Statements:** All database queries utilize PDO prepared statements with bound parameters to prevent SQL injection.
2. **Password Hashing:** Passwords are encrypted using PHP's native `password_hash()` (BCRYPT / DEFAULT) and verified via `password_verify()`.
3. **CSRF Tokens:** All state-changing POST requests (login, post creation, post edit, delete, category changes) are strictly validated with cryptographic CSRF tokens.
4. **Session Hardening:** Cookies are configured with `HttpOnly`, `SameSite=Lax`, and `session_regenerate_id(true)` upon successful login.
5. **Rate Limiting:** Failed login attempts are throttled to a maximum of 5 attempts per 15 minutes per IP address.
6. **Upload Protections:** Uploaded media undergoes MIME validation via `finfo`, file extension validation, and safe randomized file naming. Direct PHP execution inside `uploads/` is disabled via `.htaccess`.
7. **XSS Escaping:** All user-supplied data in templates is escaped using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`. Article HTML content is sanitized with an allowed list of tags.

---

## Publishing New Articles

Every new article created and published through the Admin CMS:
1. Automatically appears in the public blog listing (`blogs.php`).
2. Automatically generates its own public URL (`/blog/your-slug/` or `/blog.php?slug=your-slug`).
3. Automatically appears in related article sections across other articles.
4. Automatically uses the exact same typography, responsive layout, and dark forest green brand design.
5. Can be saved as **Draft** for private review before public release.

