# InventoryPro - Inventory Management System

## Overview
A comprehensive, modern inventory management system built with HTML, CSS, JavaScript frontend and PHP backend with PostgreSQL database. Features a complete POS system, customer/supplier management, credit sales tracking, and detailed reporting.

## Project Structure
```
/
├── index.php              # Main entry point / router
├── config/
│   ├── database.php       # Database connection class
│   └── init_db.php        # Database initialization (all tables)
├── api/
│   ├── products.php       # Products CRUD API
│   ├── categories.php     # Categories API
│   ├── stock.php          # Stock adjustment API
│   ├── dashboard.php      # Dashboard stats API
│   ├── customers.php      # Customers CRUD API
│   ├── suppliers.php      # Suppliers CRUD API
│   ├── sales.php          # Sales/POS API
│   ├── credit.php         # Credit sales & payments API
│   ├── reports.php        # Reports API
│   └── settings.php       # Settings API
├── public/
│   ├── index.html         # Main HTML file
│   ├── css/
│   │   └── style.css      # All styles
│   └── js/
│       └── app.js         # Frontend JavaScript
```

## Features

### Dashboard
- Today's sales and monthly sales totals
- Total products count with low stock alerts
- Customer count and pending credits overview
- Low stock items list with visual indicators
- Recent activity feed

### Point of Sale (POS)
- Quick product search and category filter
- Add products to cart with quantity adjustment
- Automatic tax calculation based on settings
- Discount support
- Multiple payment methods: Cash, Card, Credit
- Customer selection for credit sales
- Automatic invoice generation

### Products Management
- Full CRUD operations
- SKU, name, description, category
- Sell price and cost price tracking
- Quantity and minimum stock level
- Stock adjustment with transaction logging

### Categories
- Create and manage product categories
- Track product count per category

### Customers
- Customer database with contact info
- Credit limit management
- Credit balance tracking
- Search functionality

### Suppliers
- Supplier database
- Contact person, phone, email, address
- Notes field for additional info

### Sales History
- View all sales transactions
- Filter by date range and payment status
- View sale details with line items
- Invoice number tracking

### Credit Sales
- Track all credit sales
- Record partial payments
- Due date tracking
- Status: Pending, Partial, Paid
- Overdue alerts

### Reports
- Summary report with key metrics
- Sales report with daily breakdown
- Inventory report by category and product
- Credit report with customer balances and overdue items

### Settings
- Company information (name, address, phone, email)
- Tax rate configuration
- Currency symbol
- Low stock threshold
- Invoice prefix customization

## Tech Stack
- **Frontend**: HTML5, CSS3 (CSS Variables, Flexbox, Grid), Vanilla JavaScript (ES6+)
- **Backend**: PHP 8.2
- **Database**: PostgreSQL (via PDO)
- **Design**: Dark theme, modern card-based UI, fully responsive

## Database Tables
- `categories` - Product categories
- `products` - Inventory items with stock levels
- `stock_transactions` - History of stock adjustments
- `customers` - Customer database
- `suppliers` - Supplier database
- `sales` - Sales transactions
- `sale_items` - Line items for each sale
- `credit_sales` - Credit sale tracking
- `credit_payments` - Payment records for credit sales
- `settings` - Application configuration

## Running the Application
The application runs on PHP's built-in server on port 5000:
```
php -S 0.0.0.0:5000 index.php
```
