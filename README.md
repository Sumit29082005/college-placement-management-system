# 🎓 College Placement Management System

A full-stack placement portal for colleges.

| Layer    | Tech |
|----------|------|
| Frontend | HTML5, CSS3, vanilla JavaScript (fetch API) |
| Backend  | PHP 8+ (PDO, sessions, JSON REST-style endpoints) |
| Database | MySQL / MariaDB |
| Python   | Reporting & analytics CLI (stats, CSV export, eligibility, charts) |

## Features
**Student:** register / login · browse drives · automatic eligibility check (CGPA, backlogs, department) · one-click apply · track application status · edit profile.
**Admin (Placement cell):** dashboard stats (placement %, highest/average package, department-wise bars) · manage students, companies, drives · open/close drives · update application status (applied → shortlisted → interviewed → selected / rejected) · export applications to CSV.
**Python tools:** department stats, placed-students CSV, eligible-students list per drive, matplotlib chart.

## Setup (XAMPP / WAMP / LAMP)
1. Copy this folder to your web root, e.g. `C:\xampp\htdocs\placement-system`.
2. Start **Apache** and **MySQL**.
3. Import the schema: open phpMyAdmin → Import → `database/schema.sql`
   (or `mysql -u root -p < database/schema.sql`).
4. Edit `config/db.php` if your MySQL user/password differ.
5. Visit `http://localhost/placement-system/setup.php` **once** to create the admin and demo data, then **delete `setup.php`**.
6. Open `http://localhost/placement-system/`.

| Role    | Email                | Password     |
|---------|----------------------|--------------|
| Admin   | admin@college.edu    | Admin@123    |
| Student | aarav@college.edu    | Student@123  |

Change the admin password after first login (update `users.password_hash` using `password_hash()`).

## Python tools
```bash
cd python
pip install -r requirements.txt
python placement_tools.py stats
python placement_tools.py placed --out placed.csv
python placement_tools.py eligible --drive 1 --out eligible.csv
python placement_tools.py chart --out chart.png
```
Uses env vars `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` (defaults: localhost / placement_db / root / empty).

## Project structure
```
placement-system/
├── index.html          login + student registration
├── student.html        student dashboard
├── admin.html          admin dashboard
├── setup.php           one-time seed script (delete after use)
├── api/                auth.php · student.php · admin.php  (JSON endpoints)
├── config/db.php       database connection
├── includes/helpers.php  session, JSON helpers, eligibility logic
├── assets/css|js       styles and frontend scripts
├── database/schema.sql MySQL schema
└── python/             placement_tools.py + requirements.txt
```

## API overview (`api/<file>.php?action=...`)
- `auth`: login, register, logout, me
- `student`: profile, update_profile, drives, apply, applications
- `admin`: stats, students, delete_student, companies, add_company, delete_company, drives, add_drive, toggle_drive, delete_drive, applications, export_csv, update_status

## Security notes
Prepared statements everywhere (SQL injection), `password_hash`/`password_verify`, session ID regeneration on login, role checks on every endpoint, output escaping in JS (XSS), CSV formula-injection guard, `.htaccess` blocks direct access to config/includes/database/python (Apache).
For production also add: HTTPS, CSRF tokens, login rate limiting, a dedicated MySQL user (not root), and remove `setup.php`.

## Ideas to extend
Recruiter login · resume upload · email notifications (Python + SMTP) · interview scheduling · password reset · pagination/search.
