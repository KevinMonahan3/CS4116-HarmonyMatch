# HarmonyMatch

A music-based dating platform — *Find love in the same frequency.*

## Tech Stack
- **Frontend**: HTML5, CSS3, Vanilla JS
- **Backend**: PHP 8.x (no framework)
- **Database**: MySQL 8 / MariaDB
testing
## Project Structure

```
CS4116-HarmonyMatch/
├── assets/
│   ├── css/styles.css          # Design system (dark theme, purple/pink palette)
│   ├── js/                     # Per-page JS modules
│   └── img/                    # User uploaded photos (add to .gitignore)
├── config/
│   └── database.php            # PDO connection singleton
├── dal/                        # Data Access Layer (SQL only, no business logic)
│   ├── UserDAL.php
│   ├── MusicDAL.php
│   ├── MatchDAL.php
│   ├── MessageDAL.php
│   └── ReportDAL.php
├── controllers/                # Business logic
│   ├── AuthController.php
│   ├── UserController.php
│   ├── MatchController.php
│   └── AdminController.php
├── api/                        # AJAX endpoints (return JSON)
│   ├── auth.php
│   ├── matches.php
│   ├── messages.php
│   ├── users.php
│   └── admin.php
├── includes/                   # Shared PHP partials
│   ├── session.php
│   ├── header.php
│   └── footer.php
├── database/
│   └── schema.sql              # Full MySQL schema + seed data
├── index.php                   # Entry point (redirect)
├── login.php
├── onboarding.php
├── dashboard.php
├── search.php
├── profile.php
├── profile-own.php
├── chat.php
└── admin.php
```

## Setup (Local)

1. Install XAMPP / WAMP / Laragon
2. Place project in `htdocs/harmonymatch/`
3. Import `database/schema.sql` into phpMyAdmin
4. Update `config/database.php` with your DB credentials
5. Visit `http://localhost/harmonymatch/`

## Deployment (Free Hosting)

- Upload files via FTP
- Create MySQL DB in hosting control panel
- Import `schema.sql`
- Update `config/database.php` with hosting credentials