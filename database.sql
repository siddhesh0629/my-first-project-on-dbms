-- =============================================================================
-- Database: warehouse_db
-- Academic Evaluation Setup (3NF Relational Model)
-- Warehouse Management System
-- =============================================================================

CREATE DATABASE IF NOT EXISTS warehouse_db;
USE warehouse_db;

-- =============================================================================
-- 1. Clean up existing objects
-- WARNING: This setup script drops and recreates the project tables.
-- Back up existing data before running it.
-- =============================================================================

DROP VIEW IF EXISTS view_current_stock;

DROP TRIGGER IF EXISTS trg_stock_insert;
DROP TRIGGER IF EXISTS trg_stock_update;
DROP TRIGGER IF EXISTS trg_after_stock_update;
DROP TRIGGER IF EXISTS trg_stock_delete;
DROP TRIGGER IF EXISTS trg_after_stock_delete;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS order_details;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS stock_log;
DROP TABLE IF EXISTS stock;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS warehouses;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS categories;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- 2. Base Tables (10 Tables)
-- =============================================================================

-- 1. Users (Role-Based Access Control)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('ADMIN', 'STOCK_MANAGER', 'USER') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Categories
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Suppliers
CREATE TABLE suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(100) NOT NULL,
    contact_name VARCHAR(100),
    phone VARCHAR(20),
    city VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Warehouses
CREATE TABLE warehouses (
    warehouse_id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_name VARCHAR(100) NOT NULL,
    location VARCHAR(255),
    capacity INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Products
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(150) NOT NULL,
    sku VARCHAR(50) NOT NULL UNIQUE,
    price DECIMAL(10,2) NOT NULL,
    category_id INT,
    supplier_id INT,
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES categories(category_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_products_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Stock
CREATE TABLE stock (
    stock_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_quantity CHECK (quantity >= 0),
    CONSTRAINT uq_product_warehouse UNIQUE (product_id, warehouse_id),
    CONSTRAINT fk_stock_product
        FOREIGN KEY (product_id) REFERENCES products(product_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_warehouse
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Customers
CREATE TABLE customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    city VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Orders
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    order_date DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_orders_customer
        FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Order Details
CREATE TABLE order_details (
    order_detail_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity_ordered INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_details_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_details_product
        FOREIGN KEY (product_id) REFERENCES products(product_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Stock Log (Audit Ledger)
-- No foreign key to stock: log history can remain after a stock row is deleted.
CREATE TABLE stock_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    stock_id INT NOT NULL,
    action_type ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    old_quantity INT NOT NULL,
    new_quantity INT NOT NULL,
    change_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- 3. Seed Data
-- =============================================================================

-- Users
-- User accounts are created locally using setup_users.php (hashed passwords).

-- Categories
INSERT INTO categories (category_id, category_name, description) VALUES
(1, 'Laptops & Notebooks', 'Business laptops, ultrabooks, and high-performance gaming machines'),
(2, 'Computer Peripherals', 'Keyboards, precision mice, and gaming monitors'),
(3, 'Internal Components', 'CPUs, Graphics Cards, RAM modules, and storage drives');

-- Suppliers
INSERT INTO suppliers (supplier_id, supplier_name, contact_name, phone, city) VALUES
(1, 'Asus & ROG Tech Distro', 'Rajesh Sharma', '+91 98201 11223', 'Bengaluru'),
(2, 'Corsair & Component Hub', 'Amit Verma', '+91 98765 43210', 'Mumbai'),
(3, 'Apple & Dell Premier Logistics', 'Neha Kapoor', '+91 91234 56789', 'Gurugram');

-- Warehouses
INSERT INTO warehouses (warehouse_id, warehouse_name, location, capacity) VALUES
(1, 'Mumbai Central Warehouse', 'Andheri West,Mumbai,Maharashtra', 25000),
(2, 'Nashik Storage Facility', 'MIDC Ambad,Nashik,Maharashtra', 18000),
(3, 'Pune Logistics Terminal', 'Chakan, Pune, Maharashtra', 15000);

-- Products
INSERT INTO products (product_id, product_name, sku, price, category_id, supplier_id) VALUES
(1, 'Apple MacBook Pro M3 14"', 'LAP-MBP-14', 169900.00, 1, 3),
(2, 'Dell XPS 15 OLED Laptop', 'LAP-XPS-15', 184500.00, 1, 3),
(3, 'Lenovo Legion Pro 7i Gaming', 'LAP-LGN-07', 209990.00, 1, 1),
(4, 'ASUS ROG Zephyrus G16', 'LAP-ROG-G16', 174990.00, 1, 1),
(5, 'ASUS ROG Swift 27" 240Hz Monitor', 'MON-ROG-27', 54900.00, 2, 1),
(6, 'Logitech MX Master 3S Wireless Mouse', 'PER-LOG-MX3', 8995.00, 2, 2),
(7, 'Keychron Q1 Pro Mechanical Keyboard', 'PER-KEY-Q1P', 16499.00, 2, 2),
(8, 'NVIDIA GeForce RTX 4080 Super 16GB', 'GPU-NV-4080', 104500.00, 3, 2),
(9, 'Samsung 990 Pro 2TB NVMe PCIe 4.0 SSD', 'SSD-SAM-2TB', 17990.00, 3, 2),
(10, 'Corsair Vengeance 32GB (2x16GB) DDR5 RAM', 'RAM-COR-32G', 11490.00, 3, 2);

-- Stock
INSERT INTO stock (stock_id, product_id, warehouse_id, quantity) VALUES
(1, 1, 1, 24),
(2, 1, 2, 15),
(3, 2, 1, 18),
(4, 3, 2, 30),
(5, 4, 1, 22),
(6, 5, 2, 45),
(7, 6, 1, 80),
(8, 7, 3, 60),
(9, 8, 1, 16),
(10, 8, 2, 12),
(11, 9, 3, 90),
(12, 10, 1, 75);

-- Customers
INSERT INTO customers (customer_id, customer_name, email, phone, city) VALUES
(1, 'Infosys Enterprise Solutions', 'procurement@infosys.com', '+91 80 2852 0261', 'Bengaluru'),
(2, 'TCS Innovation Labs', 'asset.procure@tcs.com', '+91 22 6778 9999', 'Mumbai'),
(3, 'Persistent Systems R&D', 'hardware@persistent.com', '+91 20 6703 0000', 'Pune');

-- Orders
INSERT INTO orders (order_id, customer_id, order_date, total_amount) VALUES
(1, 1, '2026-09-15', 339800.00),
(2, 2, '2026-09-22', 184500.00),
(3, 3, '2026-10-02', 209990.00);

-- Order Details
INSERT INTO order_details
    (order_detail_id, order_id, product_id, quantity_ordered, unit_price) VALUES
(1, 1, 1, 2, 169900.00),
(2, 2, 2, 1, 184500.00),
(3, 3, 3, 1, 209990.00);

-- =============================================================================
-- 4. Analytical View (Dynamic Stock Valuation)
-- =============================================================================

CREATE OR REPLACE VIEW view_current_stock AS
SELECT
    s.stock_id,
    p.product_name,
    p.sku,
    c.category_name,
    w.warehouse_name,
    s.quantity,
    ROUND(s.quantity * p.price, 2) AS total_valuation,
    s.last_updated
FROM stock AS s
JOIN products AS p ON s.product_id = p.product_id
JOIN categories AS c ON p.category_id = c.category_id
JOIN warehouses AS w ON s.warehouse_id = w.warehouse_id;

-- =============================================================================
-- 5. Audit Triggers
-- Created after seed data so initial setup rows do not populate the audit log.
-- New stock insertions, quantity updates, and deletions are logged once.
-- =============================================================================

DELIMITER $$

CREATE TRIGGER trg_stock_insert
AFTER INSERT ON stock
FOR EACH ROW
BEGIN
    INSERT INTO stock_log
        (stock_id, action_type, old_quantity, new_quantity, change_timestamp)
    VALUES
        (NEW.stock_id, 'INSERT', 0, NEW.quantity, NOW());
END$$

CREATE TRIGGER trg_stock_update
AFTER UPDATE ON stock
FOR EACH ROW
BEGIN
    IF OLD.quantity <> NEW.quantity THEN
        INSERT INTO stock_log
            (stock_id, action_type, old_quantity, new_quantity, change_timestamp)
        VALUES
            (OLD.stock_id, 'UPDATE', OLD.quantity, NEW.quantity, NOW());
    END IF;
END$$

CREATE TRIGGER trg_stock_delete
AFTER DELETE ON stock
FOR EACH ROW
BEGIN
    INSERT INTO stock_log
        (stock_id, action_type, old_quantity, new_quantity, change_timestamp)
    VALUES
        (OLD.stock_id, 'DELETE', OLD.quantity, 0, NOW());
END$$

DELIMITER ;

-- =============================================================================
-- End of warehouse_db setup
-- Quick check after import:
-- SELECT * FROM view_current_stock;
-- SELECT * FROM stock_log;
-- =============================================================================
