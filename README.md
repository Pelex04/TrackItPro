TrackItPro - Smart Personal Habit Tracker with Gamification
Overview
TrackItPro is a web-based application designed to help users build and maintain positive habits through a gamified experience. It allows users to create, manage, and track habits while earning points, badges, and streaks to boost motivation. The system features a responsive, mobile-first interface, secure authentication, and analytical insights into user progress. The target audience includes students, professionals, and fitness enthusiasts looking to improve their daily or weekly routines.
Features

User Authentication: Secure registration and login with hashed passwords, session management, and optional password reset functionality.
Habit Management: Add, edit, delete, and mark habits as completed, with details like name, frequency (daily/weekly), and goals.
Progress Tracking & Analytics: Monitor streaks, view progress percentages, and generate weekly/monthly reports with dynamic charts.
Gamification: Earn points for completing habits, unlock badges (e.g., "7-Day Streak"), and view leaderboards (optional multi-user mode).
Personalization: Customize themes (light/dark mode) via cookies and receive motivational quotes on login.
Notifications: Optional reminders to keep users on track.
Scalability: Designed to support future extensions, such as a mobile app, and easy addition of new rewards or badges.

System Requirements

Frontend: HTML, CSS, JavaScript (for dynamic charts and visualizations).
Backend: PHP (Object-Oriented) with PDO for database interactions.
Database: MySQL for storing user accounts, habits, progress, and rewards.
Security: HTTPS, CSRF tokens, prepared statements, and secure password hashing (password_hash, password_verify).
Performance: Supports at least 100 concurrent users with optimized queries.
Browser Compatibility: Modern browsers (Chrome, Firefox, Safari, Edge) with responsive, mobile-first design.

Installation
Prerequisites

PHP 7.4 or higher
MySQL 5.7 or higher
Web server (e.g., Apache, Nginx)
Composer (for PHP dependencies)
Node.js (optional, for JavaScript dependencies if using a build tool)

Setup Instructions

Clone the Repository:
git clone https://github.com/your-repo/trackitpro.git
cd trackitpro


Install PHP Dependencies:
composer install


Set Up the Database:

Create a MySQL database (e.g., trackitpro).
Run the following SQL to create the required tables:CREATE DATABASE trackitpro;
USE trackitpro;

CREATE TABLE users (
    user_id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    preferences TEXT,
    reset_token VARCHAR(255),
    reset_expires DATETIME,
    PRIMARY KEY (user_id)
);

CREATE TABLE habits (
    habit_id INT NOT NULL AUTO_INCREMENT,
    user_id INT,
    name VARCHAR(255) NOT NULL,
    frequency ENUM('daily', 'weekly') NOT NULL,
    goal INT NOT NULL,
    PRIMARY KEY (habit_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE progress (
    progress_id INT NOT NULL AUTO_INCREMENT,
    habit_id INT,
    date_completed DATE NOT NULL,
    PRIMARY KEY (progress_id),
    FOREIGN KEY (habit_id) REFERENCES habits(habit_id)
);

CREATE TABLE rewards (
    reward_id INT NOT NULL AUTO_INCREMENT,
    user_id INT,
    badge_name VARCHAR(255),
    points INT,
    PRIMARY KEY (reward_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);


Update the database configuration in config/database.php with your credentials:<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'trackitpro');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
?>




Configure the Web Server:

Point your web server (e.g., Apache) to the project’s public/ directory.
Ensure .htaccess is enabled for URL rewriting.
Enable HTTPS for secure communication.


Run the Application:

Navigate to http://localhost/trackitpro in your browser.
Register a new account or log in to start tracking habits.





Database Schema
The database (trackitpro) consists of four tables:

users:

user_id: INT, Primary Key, Auto-increment
name: VARCHAR(255), Not Null
email: VARCHAR(255), Not Null, Unique
password_hash: VARCHAR(255), Not Null
preferences: TEXT, Nullable (stores theme preferences, e.g., light/dark mode)
reset_token: VARCHAR(255), Nullable (for password reset)
reset_expires: DATETIME, Nullable (expiration for reset token)


habits:

habit_id: INT, Primary Key, Auto-increment
user_id: INT, Nullable, Foreign Key (references users.user_id)
name: VARCHAR(255), Not Null (e.g., "Read for 30 mins")
frequency: ENUM('daily', 'weekly'), Not Null
goal: INT, Not Null (e.g., 5 times per week)


progress:

progress_id: INT, Primary Key, Auto-increment
habit_id: INT, Nullable, Foreign Key (references habits.habit_id)
date_completed: DATE, Not Null


rewards:

reward_id: INT, Primary Key, Auto-increment
user_id: INT, Nullable, Foreign Key (references users.user_id)
badge_name: VARCHAR(255), Nullable (e.g., "7-Day Streak")
points: INT, Nullable



Usage

Register/Login: Create an account or log in securely. Use the password reset feature if needed.
Manage Habits: Add habits (e.g., "Exercise 30 mins") with frequency and goals via the habit management page.
Track Progress: Mark habits as completed and view streaks, charts, and reports on the dashboard.
Earn Rewards: Complete habits to earn points and unlock badges. Check the leaderboard for rankings (if enabled).
Customize: Set theme preferences and enable optional reminders in the settings page.
View Analytics: Access detailed progress reports and visualizations on the progress page.

Future Scope

Mobile App: Extend TrackItPro to iOS and Android platforms.
Admin Panel: Add functionality to manage users, moderate content, and view system analytics.
Enhanced Notifications: Integrate email or push notifications for reminders.
Additional Gamification: Introduce new badges, challenges, or reward systems.

Security Features

Passwords hashed using password_hash() and verified with password_verify().
Prepared statements for all database queries to prevent SQL injection.
CSRF tokens to protect forms.
Secure session management for user authentication.
Cookies for storing user preferences (e.g., theme).
HTTPS required for deployment.

Contributing
Contributions are welcome! To contribute:

Fork the repository.
Create a new branch (git checkout -b feature/your-feature).
Commit your changes (git commit -m "Add your feature").
Push to the branch (git push origin feature/your-feature).
Open a pull request.

License
This project is licensed under the MIT License. See the LICENSE file for details.
Contact
For questions or feedback, reach out to the project maintainers at kingsleychideru1404@gmail.com.
