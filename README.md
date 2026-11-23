# E-Commerce CMS

A modern and dynamic E-Commerce Content Management System (CMS) built with **Vue.js** and **Laravel**.

This project provides an administration system for managing an online store, making it easier to control products, categories, orders, and other store-related content through a user-friendly dashboard.

## 🚀 Technologies Used

- **Vue.js** – Frontend development
- **Laravel** – Backend development
- **PHP** – Server-side programming
- **JavaScript** – Frontend functionality

## ✨ Features

- Product management
- Category management
- Order management
- Store content management
- Admin dashboard
- Dynamic and responsive user interface

## 📁 Project Structure

The project consists of two main parts:

- **Frontend:** Built with Vue.js
- **Backend:** Powered by Laravel

## ⚙️ Installation

### 1. Clone the repository

```bash
git clone YOUR_REPOSITORY_URL
```

### 2. Backend Setup (Laravel)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Configure your database settings in the `.env` file, then run:

```bash
php artisan migrate
php artisan serve
```

### 3. Frontend Setup (Vue.js)

```bash
cd frontend
npm install
npm run dev
```

## 🛠️ Requirements

- PHP
- Composer
- Node.js and npm
- MySQL (or another supported database)

## 📌 Note

Make sure to configure your environment variables and database connection before running the project.

## 👨‍💻 Developer

Developed with ❤️ using Vue.js and Laravel.
