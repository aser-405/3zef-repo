CREATE DATABASE IF NOT EXISTS internet_dashboard;
USE internet_dashboard;

CREATE TABLE IF NOT EXISTS plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    bandwidth_mbps INT NOT NULL,
    monthly_price DECIMAL(8,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL,
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    plan_id INT NOT NULL,
    joined_at DATE NOT NULL,
    FOREIGN KEY (plan_id) REFERENCES plans(id)
);

CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    hostname VARCHAR(80) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    status ENUM('online','offline','maintenance') NOT NULL DEFAULT 'online',
    last_seen DATETIME NOT NULL,
    location VARCHAR(120) NOT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE IF NOT EXISTS usage_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    usage_date DATE NOT NULL,
    download_gb DECIMAL(8,2) NOT NULL,
    upload_gb DECIMAL(8,2) NOT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    subject VARCHAR(160) NOT NULL,
    priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    status ENUM('open','pending','resolved') NOT NULL DEFAULT 'open',
    opened_at DATETIME NOT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE IF NOT EXISTS outages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    region VARCHAR(120) NOT NULL,
    started_at DATETIME NOT NULL,
    eta_restore DATETIME NOT NULL,
    status ENUM('investigating','identified','monitoring','resolved') NOT NULL
);

INSERT INTO plans (name, bandwidth_mbps, monthly_price) VALUES
('Starter 50', 50, 19.99),
('Home 150', 150, 39.99),
('Pro 300', 300, 59.99),
('Business 600', 600, 99.99);

INSERT INTO customers (full_name, email, status, plan_id, joined_at) VALUES
('Lina Mansour', 'lina@example.com', 'active', 2, '2024-01-12'),
('Omar Hassan', 'omar@example.com', 'active', 3, '2023-11-05'),
('Huda Karim', 'huda@example.com', 'suspended', 1, '2023-09-28'),
('Khaled Rami', 'khaled@example.com', 'active', 4, '2024-02-03'),
('Maya Nasser', 'maya@example.com', 'active', 2, '2024-03-10');

INSERT INTO devices (customer_id, hostname, ip_address, status, last_seen, location) VALUES
(1, 'lina-router', '10.0.1.10', 'online', '2024-03-30 09:15:00', 'Amman - Dabouq'),
(2, 'omar-ont', '10.0.2.11', 'maintenance', '2024-03-30 07:45:00', 'Irbid - City Center'),
(3, 'huda-router', '10.0.3.20', 'offline', '2024-03-27 18:20:00', 'Zarqa - New District'),
(4, 'khaled-firewall', '10.0.4.9', 'online', '2024-03-30 09:10:00', 'Amman - Abdoun'),
(5, 'maya-router', '10.0.5.14', 'online', '2024-03-30 08:55:00', 'Salt - Downtown');

INSERT INTO usage_logs (customer_id, usage_date, download_gb, upload_gb) VALUES
(1, '2024-03-29', 38.2, 6.3),
(1, '2024-03-30', 42.1, 5.9),
(2, '2024-03-29', 55.4, 12.1),
(2, '2024-03-30', 60.0, 11.2),
(3, '2024-03-29', 12.5, 2.3),
(4, '2024-03-29', 78.8, 20.4),
(4, '2024-03-30', 82.3, 22.9),
(5, '2024-03-30', 44.6, 9.1);

INSERT INTO tickets (customer_id, subject, priority, status, opened_at) VALUES
(1, 'Intermittent connection drops', 'high', 'open', '2024-03-30 08:10:00'),
(2, 'Upgrade request for static IP', 'medium', 'pending', '2024-03-29 15:35:00'),
(3, 'Billing dispute', 'low', 'open', '2024-03-28 11:05:00'),
(4, 'Latency spikes in evenings', 'medium', 'open', '2024-03-29 18:45:00'),
(5, 'Router replacement request', 'low', 'resolved', '2024-03-27 09:30:00');

INSERT INTO outages (region, started_at, eta_restore, status) VALUES
('Amman - West', '2024-03-30 06:20:00', '2024-03-30 12:00:00', 'monitoring'),
('Irbid - North', '2024-03-29 21:15:00', '2024-03-30 02:00:00', 'resolved');
