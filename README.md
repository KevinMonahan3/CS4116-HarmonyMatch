# HarmonyMatch

A music-based dating platform. This repo is now wired to work against the normalized Oracle Cloud MySQL schema described in the project database handoff.

## Tech Stack
- **Frontend**: HTML5, CSS3, Vanilla JS
- **Backend**: PHP 8.x (no framework)
- **Database**: MySQL 8 / Oracle Cloud MySQL
## Project Structure

```
CS4116-HarmonyMatch/
├── assets/
│   ├── css/styles.css          # Design system (dark theme, purple/pink palette)
│   ├── js/                     # Per-page JS modules
│   └── img/                    # User uploaded photos (add to .gitignore)
├── config/
│   ├── database.php            # PDO connection singleton
│   └── db.local.example.php    # Copy to db.local.php for local secrets
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
3. Copy `config/db.local.example.php` to `config/db.local.php`
4. Update `config/db.local.php` or set `DB_*` environment variables
5. Visit `http://localhost/harmonymatch/`

`config/db.local.php` is gitignored, so real credentials stay local.

## Deployment (Free Hosting)

- Upload files via FTP
- Provide the `DB_*` environment variables in your hosting panel, or deploy a server-side `config/db.local.php`
- Make sure the target database uses the normalized schema expected by the DAL
