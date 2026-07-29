## ⚙️ How to Run Locally

Since this is a PHP/MySQL project, you will need a local server environment like **WAMP**, **XAMPP**, or **MAMP** to run it.

### 1. Prerequisites
* Ensure your local server (Apache and MySQL) is running.
* PHP 7.4 or higher is recommended.

### 2. Project Setup
1. Clone this repository into your local server's root directory:
   * **WAMP:** `C:\wamp64\www\`
   * **XAMPP:** `C:\xampp\htdocs\`
   ```bash
   git clone [https://github.com/Anaathithan/MotoStock26.git](https://github.com/Anaathithan/MotoStock26.git)


   Navigate to the project folder:

Bash
cd MotoStock26
3. Database Setup (Crucial)
This project relies on a relational database to manage inventory, sales, and user roles.

Open phpMyAdmin in your browser (usually http://localhost/phpmyadmin).

Create a new database named motostock26 (or check the exact name required in config.php).

Click on the newly created database, navigate to the Import tab at the top.

Choose the motostock_db.sql file located in the root of this repository.

Click Import (or Go) at the bottom of the page to populate the tables and default data.

4. Database Configuration
If your local MySQL setup requires a specific password, update the database connection file (e.g., config.php or db_connect.php) with your credentials:

PHP
$servername = "localhost";
$username = "root"; // Your MySQL username
$password = ""; // Your MySQL password
$dbname = "motostock26"; 
5. Access the Application
Open your web browser and navigate to:
http://localhost/MotoStock26

🔑 Test Credentials for Recruiters
To easily test the tiered access and permissions, use the following default login credentials:

Owner (Admin):

Email/Username: [Insert Admin Email]

Password: [Insert Admin Password]

Access: Full system control, profit reports, wholesale pricing, employee management.

Cashier:

Email/Username: [Insert Cashier Email]

Password: [Insert Cashier Password]

Access: Processing invoices, applying discounts, viewing current stock levels.

Staff:

Email/Username: [Insert Staff Email]

Password: [Insert Staff Password]

Access: Tracking service jobs, basic inventory lookup.


### Next Steps for You:
1. Open your repository on GitHub.
2. Click the green **"Add a README"** button (or click the pencil icon if you already have one).
3. Paste this block of text in, making sure to replace the bracketed `[Insert ...]` placeholders under the **Test Credentials** section with the actual dummy logins from your database.
4. Scroll to the bottom and click **Commit changes**.