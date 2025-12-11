# Inventory Management System

## Overview
A modern, responsive inventory management system built with HTML, CSS, JavaScript frontend and PHP backend with PostgreSQL database.

## Project Structure
```
/
├── index.php              # Main entry point / router
├── config/
│   ├── database.php       # Database connection class
│   └── init_db.php        # Database initialization script
├── api/
│   ├── products.php       # Products CRUD API
│   ├── categories.php     # Categories API
│   ├── stock.php          # Stock adjustment API
│   └── dashboard.php      # Dashboard stats API
├── public/
│   ├── index.html         # Main HTML file
│   ├── css/
│   │   └── style.css      # All styles
│   └── js/
│       └── app.js         # Frontend JavaScript
```

## Features
- **Dashboard**: Overview with stats (total products, low stock alerts, inventory value)
- **Products Management**: Add, edit, delete products with SKU, price, quantity, category
- **Categories**: Organize products into categories
- **Stock Adjustments**: Add/remove stock with transaction history
- **Low Stock Alerts**: Visual indicators for low and out-of-stock items
- **Search & Filter**: Find products by name, SKU, or category
- **Responsive Design**: Works on desktop, tablet, and mobile

## Tech Stack
- **Frontend**: HTML5, CSS3 (CSS Variables, Flexbox, Grid), Vanilla JavaScript (ES6+)
- **Backend**: PHP 8.3
- **Database**: PostgreSQL (via PDO)
- **Design**: Dark theme, modern card-based UI

## Database Tables
- `categories`: Product categories
- `products`: Inventory items with stock levels
- `stock_transactions`: History of stock adjustments

## API Endpoints
- `GET/POST/PUT/DELETE /api/products.php` - Product CRUD
- `GET/POST/DELETE /api/categories.php` - Category management
- `GET/POST /api/stock.php` - Stock transactions
- `GET /api/dashboard.php` - Dashboard statistics

## Running the Application
The application runs on PHP's built-in server on port 5000.
