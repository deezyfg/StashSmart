# StashSmart - Personal Finance Tracker

StashSmart is a comprehensive web-based personal finance tracker that helps users manage income, expenses, and savings effortlessly. It features a user-friendly interface for setting financial goals, categorizing transactions, and gaining insights into spending habits through detailed reports and analytics.

## Features

### 🏦 Financial Management

- **Transaction Tracking**: Record income, expenses, and transfers
- **Multiple Accounts**: Support for checking, savings, credit cards, and more
- **Categories**: Customizable income and expense categories
- **File Uploads**: Attach receipts to transactions

### 📊 Analytics & Insights

- **Spending Analytics**: Category-wise spending breakdown
- **Monthly Trends**: Track spending patterns over time
- **Budget Management**: Set and monitor budgets
- **Financial Goals**: Set and track savings goals

### 🔐 Security & Authentication

- **User Registration & Login**: Secure authentication system
- **JWT Tokens**: Secure API access
- **Password Protection**: Encrypted password storage
- **Activity Logging**: Track user actions for security

### 💻 Modern Interface

- **Responsive Design**: Works on desktop and mobile
- **Interactive Dashboard**: Real-time financial overview
- **Modern UI/UX**: Clean and intuitive interface

## Technology Stack

### Frontend

- **HTML5 & CSS3**: Modern semantic markup and styling
- **Vanilla JavaScript**: Interactive functionality
- **Font Awesome Icons**: Professional iconography
- **Responsive Design**: Mobile-first approach

### Backend

- **PHP 7.4+**: Server-side logic
- **MySQL 5.7+**: Database management
- **RESTful API**: Clean API architecture
- **JWT Authentication**: Secure token-based auth

## Installation & Setup

### Prerequisites

- **XAMPP** (or similar LAMP/WAMP stack)
- **PHP 7.4 or higher**
- **MySQL 5.7 or higher**
- **Web browser** (Chrome, Firefox, Safari, Edge)

### Step 1: Install XAMPP

1. Download XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Install XAMPP and start Apache and MySQL services

### Step 2: Clone/Download Project

1. Place the StashSmart folder in your XAMPP `htdocs` directory
   ```
   C:\xampp\htdocs\StashSmart\
   ```

### Step 3: Database Setup

1. Open your web browser and navigate to:
   ```
   http://localhost/StashSmart/backend/install.php
   ```
2. The installation script will:
   - Create the `stashsmart_db` database
   - Create all necessary tables
   - Set up default categories
   - Verify the installation

### Step 4: Configuration (Optional)

1. Edit database connection settings in:
   ```
   backend/config/database.php
   ```
2. Modify API configuration in:
   ```
   backend/config/config.php
   ```

### Step 5: Access the Application

1. Open your web browser and navigate to:
   ```
   http://localhost/StashSmart/
   ```
2. Create a new account or sign in

## API Documentation

### Authentication Endpoints

- `POST /backend/api/auth/register` - User registration
- `POST /backend/api/auth/login` - User login
- `GET /backend/api/auth/profile` - Get user profile
- `PUT /backend/api/auth/profile/update` - Update profile
- `PUT /backend/api/auth/change-password` - Change password
- `POST /backend/api/auth/logout` - Logout

### Transaction Endpoints

- `GET /backend/api/transactions` - Get transactions (with filters)
- `POST /backend/api/transactions` - Create transaction
- `GET /backend/api/transactions/{id}` - Get single transaction
- `PUT /backend/api/transactions/{id}` - Update transaction
- `DELETE /backend/api/transactions/{id}` - Delete transaction
- `GET /backend/api/transactions/analytics` - Get spending analytics

### Category Endpoints

- `GET /backend/api/categories` - Get categories
- `POST /backend/api/categories` - Create category
- `PUT /backend/api/categories/{id}` - Update category
- `DELETE /backend/api/categories/{id}` - Delete category

### Account Endpoints

- `GET /backend/api/accounts` - Get accounts
- `POST /backend/api/accounts` - Create account
- `PUT /backend/api/accounts/{id}` - Update account
- `DELETE /backend/api/accounts/{id}` - Delete account

### Budget Endpoints

- `GET /backend/api/budgets` - Get budgets
- `POST /backend/api/budgets` - Create budget
- `PUT /backend/api/budgets/{id}` - Update budget
- `DELETE /backend/api/budgets/{id}` - Delete budget

### Goal Endpoints

- `GET /backend/api/goals` - Get financial goals
- `POST /backend/api/goals` - Create goal
- `PUT /backend/api/goals/{id}` - Update goal
- `DELETE /backend/api/goals/{id}` - Delete goal

## Database Schema

### Tables

- **users**: User account information
- **categories**: Income and expense categories
- **accounts**: User bank accounts and wallets
- **transactions**: Financial transactions
- **financial_goals**: Savings goals
- **budgets**: Budget management
- **user_settings**: User preferences
- **activity_log**: User activity tracking

## File Structure

```
StashSmart/
├── index.html                 # Landing page
├── style.css                  # Main stylesheet
├── script.js                  # Main JavaScript
├── about/                     # About page
├── login/                     # Login page
├── sign-up/                   # Registration page
├── Finance-Dashboard/         # Dashboard interface
├── settings/                  # User settings
├── support/                   # Support page
├── assets/                    # Images and static files
├── favicon_io/               # Favicon files
├── js/                       # JavaScript modules
│   └── api.js               # API helper functions
└── backend/                  # PHP Backend
    ├── config/              # Configuration files
    │   ├── database.php    # Database connection
    │   └── config.php      # App configuration
    ├── controllers/         # Business logic
    │   └── AuthController.php
    ├── models/             # Data models
    │   ├── User.php
    │   └── Transaction.php
    ├── api/                # API endpoints
    │   ├── index.php      # API router
    │   ├── auth.php       # Auth endpoints
    │   └── transactions.php
    ├── utils/              # Utility classes
    │   ├── jwt.php        # JWT helper
    │   ├── validator.php  # Input validation
    │   └── helpers.php    # General helpers
    ├── sql/               # Database schema
    │   └── schema.sql    # Database structure
    └── install.php        # Installation script
```

## Usage Guide

### 1. Getting Started

1. Register for a new account or log in
2. Complete your profile setup
3. Add your bank accounts/wallets

### 2. Managing Transactions

1. Navigate to the dashboard
2. Click "Add Transaction"
3. Select type (Income/Expense)
4. Choose category and account
5. Enter amount and description
6. Save the transaction

### 3. Setting Budgets

1. Go to Budget section
2. Select a category
3. Set budget amount and period
4. Monitor spending against budget

### 4. Financial Goals

1. Navigate to Goals section
2. Create a new goal
3. Set target amount and date
4. Track progress regularly

### 5. Analytics

1. View spending breakdown by category
2. Monitor monthly trends
3. Export reports as needed

## Security Features

- **Password Hashing**: BCrypt encryption
- **JWT Tokens**: Secure API authentication
- **Input Validation**: Comprehensive data validation
- **SQL Injection Protection**: Prepared statements
- **XSS Protection**: Input sanitization
- **CORS Support**: Cross-origin resource sharing

## Browser Support

- Chrome 70+
- Firefox 65+
- Safari 12+
- Edge 79+

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is licensed under the MIT License. See LICENSE file for details.

## Support

For support, please contact:

- Email: support@stashsmart.com
- GitHub Issues: [Create an issue](https://github.com/saintdannyyy/StashSmart/issues)

## Changelog

### Version 1.0.0

- Initial release
- User authentication system
- Transaction management
- Category and account management
- Basic analytics
- Responsive design

---

Made with ❤️ by the StashSmart Team
