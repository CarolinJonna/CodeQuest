# CodeQuest — Full XAMPP + PHP + MySQL Setup Guide

## What's in this package

```
codequest/
├── welcome.html          ← Landing page (login / signup) — ENTRY POINT
├── language-select.html  ← Language picker after signup
├── homepage.html         ← Dashboard, lessons map, leaderboard
├── lesson.html           ← Python Lesson 1
├── lesson-java.html      ← Java Lesson 1
├── cq.js                 ← Shared JS helper (API calls + cache)
├── config.php            ← DB credentials
├── db_setup.sql          ← Run once in phpMyAdmin to create tables
└── api/
    ├── auth.php          ← signup, login, logout, me
    └── progress.php      ← XP, chapters, leaderboard, profile, quiz
```

---

## STEP 1 — Install XAMPP

1. Download XAMPP from https://www.apachefriends.org
2. Install it (default path `C:\xampp` on Windows, `/opt/lampp` on Linux/Mac)
3. Open the **XAMPP Control Panel**
4. Start **Apache** ✅
5. Start **MySQL** ✅

---

## STEP 2 — Copy project files

Copy the entire `codequest` folder into your XAMPP web root:

- **Windows:** `C:\xampp\htdocs\codequest\`
- **Mac/Linux:** `/opt/lampp/htdocs/codequest/`

Also copy your video file:

```
C:\xampp\htdocs\codequest\lesson1-intro.mp4
```

After copying, your structure should look like:

```
C:\xampp\htdocs\codequest\
    welcome.html
    homepage.html
    lesson.html
    lesson-java.html
    language-select.html
    cq.js
    config.php
    db_setup.sql
    lesson1-intro.mp4
    api\
        auth.php
        progress.php
```

---

## STEP 3 — Create the database

1. Open your browser and go to: http://localhost/phpmyadmin
2. Click **"SQL"** tab at the top
3. Paste the contents of `db_setup.sql` into the box
4. Click **"Go"** / **"Execute"**

You should see the `codequest` database with 5 tables:
- `users`
- `chapter_completions`
- `quiz_scores`
- `activity_log`

---

## STEP 4 — Check config.php

Open `config.php` and confirm these match your XAMPP setup:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');   // default XAMPP username
define('DB_PASS', '');       // default XAMPP password (empty string)
define('DB_NAME', 'codequest');
```

If you set a MySQL password for root in XAMPP, update `DB_PASS`.

---

## STEP 5 — Open the app

Go to: **http://localhost/codequest/welcome.html**

You should see the CodeQuest landing page. Click **"Get Started for Free"** to create your first account!

---

## How the backend works

| Action | File | What happens |
|---|---|---|
| Sign Up | `api/auth.php?action=signup` | Creates user row, hashes password, starts session |
| Log In | `api/auth.php?action=login` | Verifies bcrypt password, updates streak, starts session |
| Log Out | `api/auth.php?action=logout` | Destroys PHP session |
| Check session | `api/auth.php?action=me` | Returns current user or 401 |
| Complete chapter | `api/progress.php?action=complete_chapter` | Saves XP, prevents double-counting |
| Save quiz score | `api/progress.php?action=save_quiz` | Stores quiz result, adds XP |
| Leaderboard | `api/progress.php?action=leaderboard` | Top 10 by XP from DB |
| Save profile | `api/progress.php?action=save_profile` | Updates name/username/password |
| Save language | `api/progress.php?action=save_lang` | Persists chosen language to DB |

---

## Troubleshooting

**"Database connection failed"**
→ Make sure MySQL is running in XAMPP Control Panel
→ Check DB_PASS in config.php (empty by default)

**Blank page / 404**
→ Make sure Apache is running
→ Check the folder is at `htdocs/codequest/` not `htdocs/codequest/codequest/`

**"Not authenticated" on homepage**
→ PHP sessions require the server (Apache). Don't open HTML files directly — always use http://localhost/...

**Video doesn't play**
→ Copy `lesson1-intro.mp4` into the `codequest/` folder
→ For Java lesson, add `lesson1-java-intro.mp4` too

**phpMyAdmin won't open**
→ Make sure both Apache AND MySQL are started in XAMPP

---

## Security notes (for production use)

- Change `SESSION_SECRET` in config.php to a random string
- Set a strong MySQL root password
- Move config.php outside the web root
- Add HTTPS (SSL certificate)
- For public hosting, use a VPS with proper firewall rules
