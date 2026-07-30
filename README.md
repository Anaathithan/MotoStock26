# 🏍️ MotoStock26

A full-stack motorcycle inventory management system built with PHP and MySQL. Designed to handle secure CRUD operations for dealership stock, featuring a containerized deployment architecture and a cloud-based SSL database.

**[🌐 View Live Project](https://motostock26.onrender.com)**

Login credentials 
Username : owner1
Password : admin123

> **Note to recruiters & reviewers:** This application is hosted on free-tier cloud servers (Render & Aiven). If the database has gone to sleep due to inactivity, **it may take 30-60 seconds to load on your first visit** while the server wakes up. Thank you for your patience!

---



## 🚀 Features

- **Inventory Management:** Full CRUD (Create, Read, Update, Delete) functionality for tracking motorcycle stock.
- **Secure Authentication:** User login and session management.
- **CSRF Protection:** Form submissions are protected by dynamically generated cross-site request forgery tokens.
- **Cloud Database Integration:** Connects securely to an Aiven MySQL cloud database using strict SSL requirements (`mysqli_real_connect`).
- **Secret Management:** Sensitive credentials (like `DB_PASS` and SMTP passwords) are completely hidden from source code using Environment Variables.

---

## 🛠️ Tech Stack

- **Frontend:** HTML, CSS, JavaScript 
- **Backend:** PHP 8.3
- **Database:** MySQL (Hosted on Aiven Cloud)
- **Deployment & DevOps:** Docker, Apache, Render 

---

## 💻 Local Installation

If you would like to run this project locally on your own machine (using WAMP/XAMPP):

1. **Clone the repository:**
   ```bash
   git clone [https://github.com/Anaathithan/MotoStock26.git](https://github.com/Anaathithan/MotoStock26.git)