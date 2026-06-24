<div align="center">

# LostFound

### Lost and Found Inventory Management System

A web-based Lost and Found management system built with PHP, MySQL, and Tailwind CSS. Designed for schools, universities, offices, and organizations to efficiently record, track, match, and return lost and found items.

<br>

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-CDN-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)
![XAMPP](https://img.shields.io/badge/XAMPP-Compatible-FB7A24?style=for-the-badge&logo=xampp&logoColor=white)


<br>

**Modern SaaS-style dashboard for managing lost items, found items, claims, reports, matching, notifications, and user roles.**

<br>

[Overview](#overview) · [Features](#features) · [Installation](#installation) · [File Structure](#file-structure) · [Security](#security)

</div>

---

## Overview

LostFound is a full-stack inventory management system that streamlines the process of handling lost and found items. It provides a structured workflow from item reporting, through automated matching, to claim submission and admin approval — all through a clean, modern dashboard interface.

The system is designed for local deployment using XAMPP, Apache, PHP, and MySQL, making it practical for schools, offices, universities, and organizations that need a reliable internal lost and found tracking solution.

---

## Features

<table>
<tr>
<td width="50%" valign="top">

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

</td>
<td width="50%" valign="top">

### Authentication and Access

- Secure login and registration
- Role-based access control for Admin, Staff, and User accounts
- CSRF protection on all forms
- Password hashing with BCrypt
- Secure session management
- Forgot password with token-based reset flow

### UI and Experience

- Modern SaaS-style dashboard design
- Full dark mode with localStorage persistence
- Responsive layout for desktop and tablet
- Status badges with color coding
- Active filter pills with individual clear buttons
- Flash message notifications
- Modal forms for quick CRUD operations
- Print-friendly report pages

</td>
</tr>
</table>

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 |
| Database | MySQL 8.0 |
| Frontend | HTML5, Tailwind CSS CDN, Vanilla JavaScript |
| Server | Apache through XAMPP |
| Icons | Heroicons inline SVG |
| Fonts | Inter through Google Fonts |

---

## System Requirements

| Requirement | Version / Notes |
|---|---|
| XAMPP | 8.x recommended |
| PHP | 8.0 or higher |
| MySQL | 5.7+ or MariaDB 10.4+ |
| Apache | Included with XAMPP |
| PHP Extensions | `pdo_mysql`, `fileinfo`, `mbstring` |
| Browser | JavaScript enabled |

---

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/your-username/lostfound.git
```

Or download and extract the ZIP file.

### 2. Move to XAMPP

Copy the `lostfound` folder into your XAMPP `htdocs` directory:

```text
C:/xampp/htdocs/lostfound/
```

### 3. Create Upload Directories

Create these folders manually. They are excluded from the repository:

```text
C:/xampp/htdocs/lostfound/uploads/lost_items/
C:/xampp/htdocs/lostfound/uploads/found_items/
C:/xampp/htdocs/lostfound/uploads/claims/
```

### 4. Import the Database

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin`.
3. Create a new database named `lostfound_db`.
4. Click **Import** and select `database/schema.sql`.
5. Click **Go**.

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

Or log in with the default credentials below and change the password through User Management.

### 8. Access the Application

```text
http://localhost/lostfound/
```

---

## Default Credentials

> **Important:** Change these credentials immediately after first login.

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@lostfound.local` | `password` |

To create additional staff or user accounts, register through `/modules/auth/register.php` or use the **Admin > Users** panel.

---

## File Structure

```text
lostfound/
├── index.php                        # Entry point: redirects to login or dashboard
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
│   ├── helpers.php                  # Utility functions: e(), statusBadge(), paginate(), etc.
│   └── upload.php                   # Secure file upload handler
│
├── layouts/
│   ├── header.php                   # HTML head, dark mode init, flash messages
│   ├── sidebar.php                  # Navigation sidebar and top bar
│   └── footer.php                   # Closing HTML tags and JavaScript include
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
│   │       ├── create.php           # Record found item for staff/admin
│   │       ├── view.php             # Item detail with claims list
│   │       ├── edit.php
│   │       └── delete.php
│   │
│   ├── claims/
│   │   ├── index.php                # Claims list filtered by role
│   │   ├── create.php               # Submit ownership claim
│   │   ├── view.php                 # Claim detail
│   │   └── review.php               # Approve or reject claim for staff/admin
│   │
│   ├── matching/
│   │   └── index.php                # Matching engine runner and results
│   │
│   ├── categories/
│   │   └── index.php                # CRUD with modal forms for admin only
│   │
│   ├── locations/
│   │   └── index.php                # CRUD with modal forms for admin only
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
| **Staff** | Record found items, update item status, review and approve or reject claims |
| **User** | Report lost items, view found items, submit claims, track own claim status |

---

## Database Schema

The system uses 10 database tables:

| Table | Purpose |
|---|---|
| `users` | User accounts with role assignments |
| `roles` | Role definitions: admin, staff, user |
| `lost_items` | Lost item records with owner info and status |
| `found_items` | Found item records with storage location |
| `categories` | Item categories such as Electronics, Wallets, Keys, and more |
| `locations` | Campus or facility locations |
| `claims` | Ownership claims with proof and evidence |
| `item_matches` | Auto-generated and confirmed matches between lost and found items |
| `notifications` | In-app notification records per user |
| `activity_logs` | Full audit trail of all user actions |

---

## Matching Algorithm

The matching engine scores lost items against found items across five criteria:

| Criteria | Points |
|---|---:|
| Same category | 30 |
| Same location | 20 |
| Found date is after lost date | 10 |
| Item name word overlap | Up to 25 |
| Description keyword overlap | Up to 15 |

Items scoring **40 or above** are surfaced as suggested matches. Staff can confirm or dismiss each suggestion. Confirmed matches update the lost item status to `matched` and notify the original reporter.

---

## Reports

Five report types are available under the Reports module:

| Report Type | Description |
|---|---|
| **Lost Items** | All lost item records filtered by date range |
| **Found Items** | All found item records filtered by date range |
| **Claims** | All claim submissions filtered by date range |
| **By Category** | Item distribution breakdown per category with percentage bars |
| **Monthly Summary** | 6-month comparison of lost vs found counts with visual bars |

All reports support **CSV export**, Excel-compatible UTF-8 BOM formatting, and **browser print** with the sidebar automatically hidden.

---

## Security

| Security Area | Implementation |
|---|---|
| Database Access | All database queries use PDO prepared statements |
| Passwords | Hashed with BCrypt using `PASSWORD_BCRYPT` and cost factor 12 |
| Form Protection | CSRF tokens on every POST form |
| Output Safety | XSS prevention through `htmlspecialchars()` wrapper `e()` |
| Upload Security | MIME type validation, extension whitelist, and 5MB size limit |
| Upload Hardening | PHP execution blocked in the `uploads/` directory through `.htaccess` |
| Directory Protection | Direct access to `config/`, `includes/`, and `database/` blocked through `.htaccess` |
| Sessions | Cookies use `httponly` and `samesite=Strict` |
| Authorization | Role checks enforced server-side on every protected page |

---

## Screenshots

<table>
<tr>
<td width="50%" valign="top">

<strong>Dashboard</strong>

<br><br>

<img src="https://github.com/user-attachments/assets/f6de2905-2767-4bcb-9fa6-1cfc2b7c4a9e7" alt="Dashboard Screenshot" width="100%">


</td>
<td width="50%" valign="top">

<strong>Item Management</strong>

<br><br>

<img src="https://github.com/user-attachments/assets/f137437b-9b56-4cc3-b48d-bd1c943c0e08" alt="Item Management Screenshot" width="100%">


</td>
</tr>

<tr>
<td width="50%" valign="top">

<strong>Claims Management</strong>

<br><br>

<img src="https://github.com/user-attachments/assets/7586904f-d2e7-49b0-8c7e-df1fe68df1cb" alt="Claims Management Screenshot" width="100%">


</td>
<td width="50%" valign="top">

<strong>Reports</strong>

<br><br>

<img src="https://github.com/user-attachments/assets/e0370154-6783-42cb-b90f-d517ac10fe3a" alt="Reports Screenshot" width="100%">


</td>
</tr>
</table>
---

## Contributing

1. Fork the repository.
2. Create a feature branch:

```bash
git checkout -b feature/your-feature
```

3. Commit your changes:

```bash
git commit -m "Add your feature"
```

4. Push to the branch:

```bash
git push origin feature/your-feature
```

5. Open a Pull Request.

---

## License



---

## Author
**Vincent Luke Elpedez**

Built with PHP, MySQL, and Tailwind CSS.  
Designed for educational and organizational use.

<div align="center">

<br>

**LostFound — A professional inventory workflow for returning lost items to their rightful owners.**

</div>
