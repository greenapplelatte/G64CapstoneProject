# DPBUS Login UI

This project implements the supplied DPBUS login design using the existing PHP folder structure.

## Structure

```text
waypoint/
├── css/
│   └── style.css
├── database/
│   └── database.php
├── includes/
│   └── auth_check.php
├── js/
│   └── login.js
├── auth.php
├── dashboard.php
├── index.php
├── logout.php
└── README.md
```

## Setup

1. Create a MySQL/MariaDB database named `waypoint`.
2. Create a `users` table:

```sql
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

3. Create a password hash with PHP:

```php
<?php echo password_hash('your-password', PASSWORD_DEFAULT); ?>
```

4. Insert the generated hash into the `users.password` column.
5. Update the database credentials in `database/database.php` if needed.
6. Put `waypoint` inside your XAMPP/WAMP web root and open `index.php` through Apache/PHP.

## Notes

- The layout is responsive and closely follows the supplied screenshot: light rounded login panel on the left and dark navy DPBUS branding on the right.
- `auth.php` handles login using PDO and `password_verify()`.
- `dashboard.php` demonstrates a protected page and guest access.
- `logout.php` destroys the session.
- The screenshot says “Continue as Guess”; that wording is intentionally preserved. You can change it to “Continue as Guest” if desired.
- The current file tree does not contain a `signup.php`, so the “sign up” text is a placeholder until a signup page is added.
