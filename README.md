# NPATS — National Passport Application Tracking System

> A full-stack web application for the Ministry of Home Affairs to digitise and track passport applications from submission to collection.

---

## Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [Tech Stack](#tech-stack)
4. [Project Structure](#project-structure)
5. [Getting Started](#getting-started)
6. [Default Credentials](#default-credentials)
7. [User Roles & Permissions](#user-roles--permissions)
8. [Modules](#modules)
9. [Database Schema](#database-schema)
10. [Processing Stages](#processing-stages)
11. [Security](#security)
12. [Design Decisions](#design-decisions)

---

## Overview

NPATS provides a centralised platform for managing the entire passport application lifecycle. Passport officers capture and process applications; applicants track their progress online; administrators oversee the whole system with reports and audit logs.

The system is built to run on **XAMPP (Apache + MySQL + PHP)** at `http://localhost/npats`.

---

## Features

| Feature | Description |
|---|---|
| Role-based authentication | Admin, Officer, and Applicant portals with strict access controls |
| Passport application capture | Officers fill in a validated form including photo upload |
| Application tracking | Any user can track an application by number — no login required |
| Processing stage management | Officers update each of 7 stages with status, notes, and timestamps |
| Passport release module | Records collection date, applicant name, and responsible officer |
| Admin dashboard | Live statistics, application list, user management, reports |
| Notifications | In-app notifications sent when application stages are updated |
| Activity log | Full audit trail of every login, creation, and update |
| Responsive UI | Works on desktop, tablet, and mobile |
| Print support | Application details and reports can be printed cleanly |

---

## Tech Stack

- **Frontend:** HTML5, CSS3 (custom design system), Vanilla JavaScript
- **Backend:** PHP 8.1+ (PDO, prepared statements)
- **Database:** MySQL 8 (via phpMyAdmin or CLI)
- **Server:** Apache via XAMPP
- **Icons:** Font Awesome 6.5
- **Fonts:** DM Sans (Google Fonts)

---

## Project Structure

```
npats/
├── index.php                   # Login page
├── signup.php                  # Registration page
├── logout.php                  # Session termination
├── unauthorized.php            # Access denied page
├── public_track.php            # Public application tracker (no login)
├── notifications.php           # User notifications
├── npats.sql                   # Database schema + seed data
│
├── css/
│   └── main.css               # Complete design system
|      partials/
│      ├── auth.css  
│      ├── base.css
│      ├── components.css
│      ├── forms.css
│      ├── layout.css
|      ├── navbar.css
│      ├── utilities.css
│      └── varebles.css
│
├── js/
│   └── main.js                 # Client-side logic
│
├── includes/
│   ├── config.php              # DB connection, session, helpers
│   ├── header.php              # Role-aware navbar
│   └── footer.php              # Footer + JS include
│
├── assets/
│   └── photos/                 # Uploaded applicant photos
│
├── admin/
│   ├── dashboard.php           # Admin overview + stats
│   ├── users.php               # Create, activate/deactivate, reset users
│   ├── applications.php        # Browse & filter all applications
│   ├── view_application.php    # Read-only application detail
│   ├── reports.php             # Date-range reports by status/type/officer
│   └── activity.php           # Audit log with pagination
│
├── officer/
│   ├── dashboard.php           # Officer overview + quick actions
│   ├── new_application.php     # Capture new application with validation
│   ├── applications.php        # Officer's application list
│   ├── manage_application.php  # Update processing stages
│   └── releases.php            # Release passports & record collection
│
└── applicant/
    ├── dashboard.php           # Applicant overview + progress tracker
    ├── track.php               # Track by application number
    └── my_applications.php     # List of applicant's applications
```

---

## Getting Started

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) with **Apache** and **MySQL** running
- PHP 8.1 or higher
- A modern web browser

### Installation

**1. Clone / copy the project**

Place the `npats` folder inside your XAMPP web root:

```
C:\xampp\htdocs\npats\       (Windows)
/opt/lampp/htdocs/npats/     (Linux)
/Applications/XAMPP/htdocs/  (macOS)
```

**2. Create the database**

Open **phpMyAdmin** at `http://localhost/phpmyadmin`, then:

- Click **New** in the sidebar
- Name it `npats` and click **Create**
- Select the `npats` database, go to the **Import** tab
- Choose `npats.sql` from the project root and click **Go**

Or via MySQL CLI:

```bash
mysql -u root -p -e "CREATE DATABASE npats;"
mysql -u root -p npats < npats.sql
```

**3. Configure database credentials**

Open `includes/config.php` and update if needed:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // Set your MySQL password here
define('DB_NAME', 'npats');
```

**4. Launch the app**

Visit `http://localhost/npats` in your browser.

---

## Default Credentials

> **Change all passwords immediately after first use in a real deployment.**

| Role | Username | Password |
|---|---|---|
| Administrator | `admin` | `password` |
| Passport Officer | `officer1` | `password` |
| Applicant | `applicant1` | `password` |

---

## User Roles & Permissions

### Administrator
- Create, activate/deactivate, and reset passwords for all users
- View all passport applications system-wide
- Generate date-range reports by status, type, and officer
- Monitor the full activity/audit log

### Passport Officer
- Capture new passport applications (with photo upload)
- Verify documents and update processing stages
- Approve, reject, or advance applications through stages
- Record passport collection and release

### Applicant
- Track any application by application number (also available without login)
- View linked applications on the dashboard with a live progress tracker
- Receive in-app notifications when stages are updated

---

## Modules

### 1. User Authentication
- Login/logout with session management
- Role-based redirect on login
- `requireRole()` guard on every protected page — direct URL access by unauthorised users returns a 403-style page, not the actual content
- Passwords hashed with `password_hash()` (bcrypt, cost 12)

### 2. Passport Application Module
Officers register applications using a validated form:
- Full Name, National ID, Date of Birth, Gender
- Address, Phone, Email
- Passport Type (Normal / Express)
- Applicant Photo (JPG/PNG, max 2 MB)
- Auto-generated unique application number (`NPATS-YYYY-XXXXXX`)

Client-side validation (JS) and server-side validation (PHP) both run on every submission.

### 3. Application Tracking Module
- Track by application number — works for logged-in users and the public
- Displays a visual stage-by-stage progress tracker
- Shows status, responsible officer, timestamp, and comments per stage

### 4. Processing Stage Management
Officers update each stage via a modal dialog. For each stage the system stores:
- Stage status (Pending / In-Progress / Completed / Rejected)
- Officer responsible
- Date/time updated
- Comments/notes

Updating any stage automatically updates the application's `current_stage` and overall `status`.

### 5. Passport Release Module
When a passport reaches **Ready for Collection**:
- Officer opens the release modal and records the collection date and any notes
- The `passport_releases` table is updated
- The stage **Passport Released** is marked Completed
- The application status is set to **Completed**
- The applicant (if linked) receives an in-app notification

### 6. Dashboard Module
Each role has its own dashboard showing contextually relevant statistics, recent activity, and quick-action buttons.

---

## Database Schema

| Table | Purpose |
|---|---|
| `users` | All system users with role and active flag |
| `passport_applications` | Core application records |
| `processing_stages` | One row per stage per application |
| `passport_releases` | Collection records for released passports |
| `activity_log` | Audit trail of all significant actions |
| `notifications` | In-app notification messages per user |

---

## Processing Stages

Applications move through these stages in order:

1. **Application Submitted** — auto-completed on creation
2. **Document Verification** — officer reviews submitted documents
3. **Biometric Capture** — fingerprints and photo captured
4. **Background Check** — security/criminal check performed
5. **Passport Printing** — passport document printed
6. **Ready for Collection** — applicant can collect
7. **Passport Released** — passport handed over, application complete

Each stage can be independently set to: `Pending` · `In-Progress` · `Completed` · `Rejected`

---

## Security

- All user input sanitised with `htmlspecialchars()` before output
- All database queries use PDO prepared statements — no raw SQL interpolation
- Role checks (`requireRole()`) on every page — URL-guessing is blocked
- Session regenerated on login (`session_regenerate_id(true)`)
- Uploaded files validated by MIME type and size; stored with randomised filenames
- HTTP-only session cookies with SameSite=Strict

---

## Design Decisions

### UI Design System
A custom CSS design system inspired by authoritative government portals. Key choices:

- **Colour palette:** Deep navy (`#0B2545`) as the primary brand colour communicates trust and authority. Gold (`#C8911A`) provides an institutional accent — used sparingly for emphasis.
- **Typography:** DM Sans — geometric, readable, and modern without feeling generic.
- **Two-panel auth layout:** The split login/signup page gives the system a polished, SaaS-quality first impression while using the left panel for informational content.
- **Stat cards with coloured top borders:** A subtle visual hierarchy indicator that avoids the heavy-box look of full coloured cards.

### PHP Architecture
- Single `includes/config.php` bootstraps the DB, session, and all helper functions — no framework needed for a project of this scope.
- `statusBadge()`, `initials()`, and `logActivity()` are global helpers to keep templates DRY.
- Every page that requires authentication calls `requireRole()` as its first action after including config.

### Session Variables (PHP)
Session variables are used to persist authenticated user state between HTTP requests (which are stateless by nature):

```php
// Set on login
$_SESSION['user_id']    = $user['id'];
$_SESSION['user_name']  = $user['full_name'];
$_SESSION['user_role']  = $user['role'];
$_SESSION['user_email'] = $user['email'];

// Read on every page
$role = $_SESSION['user_role'] ?? '';

// Destroyed on logout
session_unset();
session_destroy();
```

Flash messages (`flash()` / `getFlash()`) also use the session — a message is stored in `$_SESSION['flash']` and consumed (and deleted) on the next page load, allowing post-redirect-get patterns without showing duplicate messages on refresh.

---

*Built for the C6-WDD-19 Web Design and Development module — Jan–Jun 2026.*
