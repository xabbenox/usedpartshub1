-- Create database
CREATE DATABASE IF NOT EXISTS used_parts_hub;
USE used_parts_hub;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(50),
    postal_code VARCHAR(10),
    country VARCHAR(50) DEFAULT 'Austria',
    registration_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    is_admin BOOLEAN DEFAULT FALSE
);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    parent_id INT,
    description TEXT,
    FOREIGN KEY (parent_id) REFERENCES categories(category_id) ON DELETE SET NULL
);

-- Car makes table
CREATE TABLE IF NOT EXISTS car_makes (
    make_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

-- Car models table
CREATE TABLE IF NOT EXISTS car_models (
    model_id INT AUTO_INCREMENT PRIMARY KEY,
    make_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    FOREIGN KEY (make_id) REFERENCES car_makes(make_id) ON DELETE CASCADE,
    UNIQUE KEY (make_id, name)
);

-- Parts listings table
CREATE TABLE IF NOT EXISTS parts (
    part_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    category_id INT NOT NULL,
    make_id INT,
    model_id INT,
    year_from INT,
    year_to INT,
    condition_rating ENUM('New', 'Like New', 'Good', 'Fair', 'Poor') NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    is_negotiable BOOLEAN DEFAULT FALSE,
    location VARCHAR(100),
    date_posted DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_sold BOOLEAN DEFAULT FALSE,
    views INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE,
    FOREIGN KEY (make_id) REFERENCES car_makes(make_id) ON DELETE SET NULL,
    FOREIGN KEY (model_id) REFERENCES car_models(model_id) ON DELETE SET NULL
);

-- Images table
CREATE TABLE IF NOT EXISTS images (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    part_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (part_id) REFERENCES parts(part_id) ON DELETE CASCADE
);

-- Messages table
CREATE TABLE IF NOT EXISTS messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    part_id INT,
    subject VARCHAR(100),
    message TEXT NOT NULL,
    sent_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (part_id) REFERENCES parts(part_id) ON DELETE SET NULL
);

-- Favorites table
CREATE TABLE IF NOT EXISTS favorites (
    favorite_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    part_id INT NOT NULL,
    date_added DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (part_id) REFERENCES parts(part_id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, part_id)
);

-- Insert some sample categories
INSERT INTO categories (name, description) VALUES
('Engine Parts', 'All parts related to the engine'),
('Transmission', 'Transmission and related components'),
('Suspension & Steering', 'Suspension and steering components'),
('Brakes', 'Brake system components'),
('Body Parts', 'External body panels and components'),
('Interior', 'Interior components and accessories'),
('Electrical', 'Electrical system components'),
('Wheels & Tires', 'Wheels, tires, and related components');

-- Insert some sample car makes
INSERT INTO car_makes (name) VALUES
('Audi'), ('BMW'), ('Mercedes-Benz'), ('Volkswagen'), ('Opel'),
('Ford'), ('Toyota'), ('Honda'), ('Hyundai'), ('Skoda');

-- Insert some sample car models
INSERT INTO car_models (make_id, name) VALUES
(1, 'A3'), (1, 'A4'), (1, 'A6'), (1, 'Q5'),
(2, '3 Series'), (2, '5 Series'), (2, 'X3'), (2, 'X5'),
(3, 'C-Class'), (3, 'E-Class'), (3, 'S-Class'), (3, 'GLC'),
(4, 'Golf'), (4, 'Passat'), (4, 'Tiguan'), (4, 'Polo'),
(5, 'Astra'), (5, 'Corsa'), (5, 'Insignia');