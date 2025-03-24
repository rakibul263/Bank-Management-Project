# Bank Management System

A comprehensive web-based banking system built with PHP and MySQL that provides secure banking services for customers and administrative tools for bank staff.

## Features

### Customer Features
- **Account Management**: Create and manage savings and current accounts
- **Transactions**: Deposit, withdraw, and transfer funds between accounts
- **Loan Management**: Apply for loans and track loan status
- **Statement Generation**: View and download account statements
- **Profile Management**: Update personal information and security settings

### Admin Features
- **User Management**: View, approve, and manage user accounts
- **Account Administration**: Monitor and manage customer accounts
- **Transaction Oversight**: Review and track all financial transactions
- **Loan Processing**: Approve or reject loan applications
- **Withdrawal Approvals**: Process withdrawal requests

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript
- **Libraries**: PDO for database connections
- **Security**: Password hashing, session management, input sanitization

## Installation

1. Clone the repository
```
git clone https://github.com/yourusername/Bank-Management-Project.git
cd Bank-Management-Project
```

2. Configure your web server (Apache/Nginx) to serve the project files

3. Create the MySQL database:
```
mysql -u username -p < database.sql
```

4. Update the database configuration in `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'banking_system');
```

5. Create an admin user:
```
php create_admin.php
```

6. Start the PHP development server:
```
php -S localhost:8000
```

7. Open your browser and navigate to `http://localhost:8000`

## Usage

### Customer Portal
- Register for a new account
- Login to access banking features
- Manage accounts, perform transactions, and apply for services

### Admin Portal
- Access the admin panel at `/admin`
- Login with admin credentials
- Manage users, accounts, transactions, and bank operations

## Security Features

- Password hashing with bcrypt
- Session management and protection
- Input sanitization to prevent SQL injection
- OTP verification for sensitive operations
- Transaction logging and audit trails

## Project Structure

- `/` - Root directory containing main application files
- `/admin` - Administrative interface
- `/includes` - Common functions, templates, and styles
- `/vendor` - Third-party dependencies

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- [PHP](https://www.php.net/)
- [MySQL](https://www.mysql.com/)
- [Bootstrap](https://getbootstrap.com/) (if used in your project) 