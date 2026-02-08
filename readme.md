# WP Dummy Content Filler - WordPress Plugin

A comprehensive WordPress plugin for generating dummy content including posts, WooCommerce products, and users with custom meta fields and taxonomies.

## 📋 Table of Contents
- [Features](#features)
- [Installation](#installation)
- [Usage](#usage)
- [Screenshots](#screenshots)
- [File Structure](#file-structure)
- [API Documentation](#api-documentation)
- [Faker Data Types](#faker-data-types)
- [Customization](#customization)
- [Troubleshooting](#troubleshooting)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [License](#license)

## 🚀 Features

### 📝 **Post Types Module**
- ✅ Generate dummy posts for any public post type (excluding products)
- ✅ Create custom taxonomy terms with configurable assignments
- ✅ Add featured images from plugin assets
- ✅ Fill custom post meta fields (ACF, CMB2 support)
- ✅ Configurable post author, excerpt, and content
- ✅ Manage and delete dummy posts (including trashed)
- ✅ Clean up all associated meta data and taxonomy terms
- ✅ AJAX-based configuration loading

### 🛒 **WooCommerce Products Module**
- ✅ Generate dummy WooCommerce products with real data from CSV
- ✅ Import product data from Walmart CSV dataset
- ✅ Create product categories, tags, and custom taxonomies
- ✅ Add featured images from CSV URLs or plugin assets
- ✅ Configure product gallery images separately
- ✅ Set product prices, SKU, stock, and other WooCommerce meta
- ✅ Support for product_brand taxonomy (single assignment)
- ✅ Leave Empty option for all product meta fields

### 👥 **Users Module**
- ✅ Generate dummy users with various roles
- ✅ Fill user meta fields including WooCommerce billing/shipping
- ✅ Support for ACF and CMB2 user fields
- ✅ Auto-detect field types based on field names
- ✅ Clean user deletion with meta cleanup
- ✅ Manage dummy users list

## 📁 Installation

### Method 1: WordPress Admin
1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin"
4. Choose the ZIP file and click "Install Now"
5. Activate the plugin

### Method 2: Manual Installation
1. Extract the plugin ZIP file
2. Upload `wp-dummy-content-filler` folder to `/wp-content/plugins/`
3. Activate the plugin through WordPress Admin

### Requirements
- PHP 7.4 or higher
- WordPress 5.0 or higher
- For WooCommerce features: WooCommerce 5.0+
- For images: GD Library or Imagick extension

## 🎮 Usage

### Accessing the Plugin
After activation, navigate to:
- **WordPress Admin → Dummy Content**

### Generating Posts
1. Go to **Post Types** tab
2. Select post type (post, page, or custom post types)
3. Configure number of posts
4. Set content options (excerpt, featured images)
5. Configure taxonomy terms (create/assign)
6. Map custom meta fields to Faker data types
7. Click "Generate Posts"

### Generating Products
1. Go to **Products** tab (requires WooCommerce)
2. Configure number of products
3. Set product status and content options
4. Configure taxonomy terms (product_brand limited to 1 term)
5. Map product meta fields or select "Leave Empty"
6. Choose featured image and gallery options
7. Click "Generate Products"

### Generating Users
1. Go to **Users** tab
2. Configure number of users and role
3. Map user fields to Faker data types
4. Click "Generate Users"

### Managing Dummy Content
Each module has a "Manage" tab where you can:
- View all generated content
- Filter by type
- Delete individual items
- Bulk delete all dummy content
