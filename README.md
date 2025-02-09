# Poultry
PoultryManagementSystem
Developed by Moses Kiplangat, Software Developer under Divi IT Solutions

Overview
This Poultry Management System is a comprehensive web-based platform to manage all aspects of a poultry farm. The system includes functionalities for:

Egg Production Management: Track daily egg production, including batch details and damaged eggs.
Expenses & Feeds Management: Record various expenses including feeds, medications, utilities, maintenance, and salaries.
Sales Management: Record and track sales for eggs and chicks.
Financial Reports: Generate Profit & Loss reports and detailed Balance Sheets, including a comparison of income (sales) and expenses (including salaries) with additional analysis on net profit or loss.
Asset Management: Manage farm assets such as equipment, buildings, vehicles, and land.
Liabilities & Capital Management: Track liabilities (loans, supplier credits, and other obligations) along with payment tracking and reporting. Also, manage capital investments.
User Management: CRUD operations on users (admins, managers, and workers) with role-specific functionality.
Charts & Graphs: Visualize liability trends and compare egg production with egg sales using interactive charts (Chart.js).
AJAX Operations: All CRUD operations in the user management module are processed using AJAX with SweetAlert notifications for a seamless experience.
Project Structure
pgsql
Copy
Edit
/ (project root)
├── README.md
├── db.php                   # Centralized database connection using PDO
├── admin_dashboard.php      # Admin dashboard with all functionalities
├── manager_dashboard.php    # Manager dashboard with limited functionality & financial reports
├── worker_dashboard.php     # Worker dashboard with essential functions (e.g., egg production)
├── process_customer.php     # Process customer addition
├── process_user.php         # Process user add, edit, and delete (returns JSON responses)
├── process_liability.php    # Process adding liabilities
├── process_payment.php      # Process recording payments towards liabilities
├── capital.sql              # SQL script to create the Capital table
├── liabilities.sql          # SQL script to create the Liabilities table
└── (other process files and assets as needed)
Setup and Installation
Database Setup:

Create a MySQL database (e.g., poultry_farm).

Execute the SQL scripts provided to create the necessary tables:

Example for Liabilities Table:

sql
Copy
Edit
CREATE TABLE IF NOT EXISTS liabilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    liability_type ENUM('Loan', 'Supplier Credit', 'Other') NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('Pending', 'Paid') DEFAULT 'Pending',
    paid_amount DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
Capital Table:

sql
Copy
Edit
CREATE TABLE IF NOT EXISTS capital (
    id INT AUTO_INCREMENT PRIMARY KEY,
    amount DECIMAL(10,2) NOT NULL,
    source VARCHAR(100) NOT NULL,
    date_added DATE NOT NULL DEFAULT CURRENT_DATE,
    notes TEXT
);
Also, execute any additional SQL scripts provided for other functionalities (e.g., users, egg production, expenses, sales, etc.).

Configure Database Connection:

Open db.php and update the connection details (host, db, user, pass) to match your MySQL server settings.
Deploying the Application:

Place all files in your web server's root (or a subdirectory) so they are accessible via your browser.
Make sure PHP and MySQL are installed and configured on your server.
Key Functionalities
1. Dashboard Functionality
Admin Dashboard: Provides full access to all modules including egg production, expenses, feeds, medications, sales, assets, liabilities, user management, and comprehensive financial reports (Profit & Loss, Balance Sheet).
Manager Dashboard: Limited access to review key statistics, detailed financial reports, and generate printable reports.
Worker Dashboard: Focused on essential functions like egg production recording.
2. User Management (AJAX-Based)
Add/Edit/Delete Users: All CRUD operations on users are handled via AJAX to provide a seamless user experience with SweetAlert notifications.
Editable Fields: Only full_name and username are editable in the user update operation; email remains unchanged to avoid duplicate issues.
JSON Responses: The process_user.php file returns JSON responses to inform the front-end of success or error statuses.
3. Liabilities & Payments Management
Liabilities Management: Users can add new liabilities with details such as description, amount, liability type, and due date.
Payment Tracking: The system tracks payments made towards each liability. The liabilities table is updated with a running total of paid_amount, and the remaining balance is computed dynamically.
Liability Payment Report: A separate section generates a payment report (with date filters) and displays a table comparing the liability amount, paid amount, remaining balance, last payment date, and payment method.
Charts: Interactive charts visualize liability trends and compare liabilities vs. payments using Chart.js.
4. Financial Reports
Profit & Loss Report:
Income: Derived from sales revenue.
Expenses: Includes all expenses, with salaries specifically included.
Net Profit/Loss: Computed as total income minus total expenses.
Balance Sheet:
Displays assets (from the assets module), liabilities, capital invested (initial capital of Kshs. 50,000), and equity.
Presented in a Dr/Cr format to give a clear financial snapshot.
5. Charts & Graphs
Liability Trends Chart: A bar chart comparing total liabilities against total payments made.
Egg Production vs. Sales Chart: A line chart comparing daily egg production with egg sales.
Front-End Libraries
SweetAlert2: For stylish pop-up notifications.
Chart.js: For interactive charts and graphs.
FontAwesome: For icons representing various modules (e.g., liabilities, users).
How to Use
Navigating the Dashboard:

Use the sidebar to switch between sections (egg production, expenses, liabilities, users, reports, etc.).
On mobile devices, the sidebar is hidden by default and can be toggled using the hamburger button.
Performing CRUD Operations:

Users: Add, edit, or delete users via the Manage Users section. Operations are processed using AJAX, and notifications are displayed via SweetAlert.
Liabilities: Add new liabilities, make payments, and view payment history within the Liabilities Management section. Use the payment modal to record payments without leaving the page.
Reports: Generate, view, and print Profit & Loss and Balance Sheet reports by selecting date ranges and clicking the print buttons.
Viewing Charts:

The Liability Trends Chart and Egg Production vs. Sales Chart dynamically fetch data from the database and render interactive graphs using Chart.js.
Error Handling & Debugging
PHP Errors: Detailed error reporting is enabled for development. Errors are displayed on the front-end (ensure this is disabled in production).
AJAX Error Handling: The AJAX functions in the Manage Users section capture errors and display them using SweetAlert.
Credits
Developed by: Moses Kiplangat
Role: Software Developer
Company: Divi IT Solutions

For any issues or further customizations, please contact Moses Kiplangat at Divi IT Solutions.

This README provides a comprehensive overview of this Poultry Management System along with setup instructions, key functionalities, and usage guidelines. Enjoy building and expanding your system!
