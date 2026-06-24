# LostFound — Lost and Found Inventory Management System

A web-based Lost and Found management system built with PHP, MySQL, and Tailwind CSS. Designed for schools, universities, offices, and organizations to efficiently record, track, match, and return lost and found items.

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Default Credentials](#default-credentials)
- [File Structure](#file-structure)
- [User Roles](#user-roles)
- [Screenshots](#screenshots)
- [Database Schema](#database-schema)
- [Security](#security)
- [License](#license)

---

## Overview

LostFound is a full-stack inventory management system that streamlines the process of handling lost and found items. It provides a structured workflow from item reporting, through automated matching, to claim submission and admin approval — all through a clean, modern dashboard interface.

---

## Features

### Core Modules
- **Dashboard** — Real-time statistics, recent activity feed, and quick action buttons
- **Lost Items** — Report, track, and manage lost item records with photo uploads
- **Found Items** — Record found items with storage location tracking
- **Claims Management** — Submit ownership claims with proof of ownership and evidence photos
- **Matching Engine** — Automated scoring algorithm that matches lost items against found items by category, location, date, and keyword similarity
- **Reports** — Filterable reports with CSV export and print support across 5 report types
- **Notifications** — In-app notification system for claim updates and match alerts
- **Categories** — Full CRUD management for item categories
- **Locations** — Manage campus/facility locations with building and floor details
- **User Management** — Admin panel for managing user accounts and roles

### Authentication
- Secure login and registration
- Role-based access control (Admin / Staff / User)
- CSRF protection on all forms
- Password hashing with BCrypt
- Session management with secure cookie settings
- Forgot password with token-based reset flow

### UI/UX
- Modern SaaS-style dashboard design
- Full dark mode with localStorage persistence
- Responsive layout for desktop and tablet
- Status badges with color coding
- Active filter pills with individual clear buttons
- Flash message notifications
- Modal forms for quick CRUD operations
- Print-friendly report pages

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 |
| Database | MySQL 8.0 |
| Frontend | HTML5, Tailwind CSS (CDN), Vanilla JavaScript |
| Server | Apache (XAMPP) |
| Icons | Heroicons (inline SVG) |
| Fonts | Inter (Google Fonts) |

---

## System Requirements

- XAMPP 8.x or any Apache + PHP 8.0+ stack
- MySQL 5.7+ or MariaDB 10.4+
- PHP Extensions: `pdo_mysql`, `fileinfo`, `mbstring`
- Web browser with JavaScript enabled

---

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/your-username/lostfound.git
```

Or download and extract the ZIP file.

### 2. Move to XAMPP

Copy the `lostfound` folder into your XAMPP htdocs directory:

```
C:/xampp/htdocs/lostfound/
```

### 3. Create Upload Directories

Create these folders manually — they are excluded from the repository:

```
C:/xampp/htdocs/lostfound/uploads/lost_items/
C:/xampp/htdocs/lostfound/uploads/found_items/
C:/xampp/htdocs/lostfound/uploads/claims/
```

### 4. Import the Database

1. Start **Apache** and **MySQL** in the XAMPP Control Panel
2. Open `http://localhost/phpmyadmin`
3. Create a new database named `lostfound_db`
4. Click **Import** and select `database/schema.sql`
5. Click **Go**

### 5. Configure Database Connection

Open `config/database.php` and update if needed:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'lostfound_db');
define('DB_USER', 'root');
define('DB_PASS', '');  // Set your MySQL password if applicable
```

### 6. Configure Base URL

Open `config/app.php` and confirm:

```php
define('BASE_URL', 'http://localhost/lostfound');
define('BASE_PATH', 'C:/xampp/htdocs/lostfound');
```

### 7. Set Admin Password

Run this SQL in phpMyAdmin to set a secure admin password:

```sql
UPDATE users
SET password_hash = '$2y$12$YOUR_BCRYPT_HASH_HERE'
WHERE email = 'admin@lostfound.local';
```

Or simply log in with the default credentials below and change the password via User Management.

### 8. Access the Application

```
http://localhost/lostfound/
```

---

## Default Credentials

> **Change these immediately after first login.**

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@lostfound.local` | `password` |

To create additional staff or user accounts, register via `/modules/auth/register.php` or use the Admin → Users panel.

---

## File Structure

```
lostfound/
├── index.php                        # Entry point (redirects to login or dashboard)
├── .htaccess                        # Apache security rules
│
├── config/
│   ├── app.php                      # App constants, upload paths, settings
│   └── database.php                 # PDO database connection
│
├── database/
│   └── schema.sql                   # Full database schema with seed data
│
├── includes/
│   ├── auth.php                     # Session management, CSRF, role checks, logging
│   ├── helpers.php                  # Utility functions (e(), statusBadge(), paginate(), etc.)
│   └── upload.php                   # Secure file upload handler
│
├── layouts/
│   ├── header.php                   # HTML head, dark mode init, flash messages
│   ├── sidebar.php                  # Navigation sidebar and top bar
│   └── footer.php                   # Closing HTML tags, JS include
│
├── assets/
│   ├── css/app.css                  # Custom styles and data-table utility classes
│   └── js/app.js                    # Dark mode toggle, modals, confirm dialogs, image preview
│
├── modules/
│   ├── auth/
│   │   ├── login.php
│   │   ├── register.php
│   │   ├── logout.php
│   │   └── forgot_password.php
│   │
│   ├── dashboard/
│   │   └── index.php                # Stats cards, recent items, activity feed
│   │
│   ├── items/
│   │   ├── lost/
│   │   │   ├── index.php            # List with filters and pagination
│   │   │   ├── create.php           # Report lost item form
│   │   │   ├── view.php             # Item detail with potential matches
│   │   │   ├── edit.php             # Edit item and status
│   │   │   └── delete.php           # Delete with photo cleanup
│   │   └── found/
│   │       ├── index.php
│   │       ├── create.php           # Record found item (staff/admin)
│   │       ├── view.php             # Item detail with claims list
│   │       ├── edit.php
│   │       └── delete.php
│   │
│   ├── claims/
│   │   ├── index.php                # Claims list (filtered by role)
│   │   ├── create.php               # Submit ownership claim
│   │   ├── view.php                 # Claim detail
│   │   └── review.php               # Approve or reject claim (staff/admin)
│   │
│   ├── matching/
│   │   └── index.php                # Matching engine runner and results
│   │
│   ├── categories/
│   │   └── index.php                # CRUD with modal forms (admin only)
│   │
│   ├── locations/
│   │   └── index.php                # CRUD with modal forms (admin only)
│   │
│   ├── reports/
│   │   ├── index.php                # Reports dashboard with 5 tabs
│   │   └── export_csv.php           # CSV export handler
│   │
│   └── notifications/
│       └── index.php                # Notification inbox with mark-read
│
├── admin/
│   └── users/
│       ├── index.php                # User list with role and status management
│       ├── create.php               # Create new user account
│       └── edit.php                 # Edit user details and password
│
└── uploads/                         # Not committed to repository
    ├── lost_items/
    ├── found_items/
    └── claims/
```

---

## User Roles

| Role | Access Level |
|---|---|
| **Admin** | Full access — manage users, categories, locations, approve claims, view all reports |
| **Staff** | Record found items, update item status, review and approve/reject claims |
| **User** | Report lost items, view found items, submit claims, track own claim status |

---

## Database Schema

The system uses 10 database tables:

| Table | Purpose |
|---|---|
| `users` | User accounts with role assignments |
| `roles` | Role definitions (admin, staff, user) |
| `lost_items` | Lost item records with owner info and status |
| `found_items` | Found item records with storage location |
| `categories` | Item categories (Electronics, Wallets, Keys, etc.) |
| `locations` | Campus/facility locations |
| `claims` | Ownership claims with proof and evidence |
| `item_matches` | Auto-generated and confirmed matches between lost and found items |
| `notifications` | In-app notification records per user |
| `activity_logs` | Full audit trail of all user actions |

---

## Matching Algorithm

The matching engine scores lost items against found items across five criteria:

| Criteria | Points |
|---|---|
| Same category | 30 |
| Same location | 20 |
| Found date is after lost date | 10 |
| Item name word overlap | up to 25 |
| Description keyword overlap | up to 15 |

Items scoring **40 or above** are surfaced as suggested matches. Staff can confirm or dismiss each suggestion. Confirmed matches update the lost item status to `matched` and notify the original reporter.

---

## Reports

Five report types are available under the Reports module:

- **Lost Items** — All lost item records filtered by date range
- **Found Items** — All found item records filtered by date range
- **Claims** — All claim submissions filtered by date range
- **By Category** — Item distribution breakdown per category with percentage bars
- **Monthly Summary** — 6-month comparison of lost vs found counts with visual bars

All reports support **CSV export** (Excel-compatible with UTF-8 BOM) and **browser print** with sidebar automatically hidden.

---

## Security

- All database queries use **PDO prepared statements** — no raw SQL concatenation
- Passwords hashed with **BCrypt** (`PASSWORD_BCRYPT`, cost factor 12)
- **CSRF tokens** on every POST form
- **XSS prevention** via `htmlspecialchars()` wrapper `e()` on all output
- File uploads validated by **MIME type** (finfo), extension whitelist, and size limit (5MB)
- PHP execution blocked in the `uploads/` directory via `.htaccess`
- Direct access to `config/`, `includes/`, and `database/` blocked via `.htaccess`
- Session cookies set with `httponly`, `samesite=Strict`
- Role checks enforced server-side on every protected page

---

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/your-feature`)
3. Commit your changes (`git commit -m 'Add your feature'`)
4. Push to the branch (`git push origin feature/your-feature`)
5. Open a Pull Request

---

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

---

## Author

Built with PHP, MySQL, and Tailwind CSS.  
Designed for educational and organizational use.
