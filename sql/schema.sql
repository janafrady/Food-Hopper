
-- Food Hopper Database Schema

CREATE DATABASE IF NOT EXISTS food_hopper;
USE food_hopper;


-- TABLE: Customer
CREATE TABLE Customer (
    customer_id    INT AUTO_INCREMENT PRIMARY KEY,
    customer_name  VARCHAR(100) NOT NULL,
    customer_address VARCHAR(255) NOT NULL,
    customer_email VARCHAR(150) NOT NULL UNIQUE,
    customer_phone VARCHAR(20)  NOT NULL UNIQUE,
    credit_card    VARCHAR(20)  NOT NULL,
    account_password VARCHAR(255) NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- TABLE: Restaurant
CREATE TABLE Restaurant (
    restaurant_id      INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_name    VARCHAR(150) NOT NULL,
    restaurant_address VARCHAR(255) NOT NULL,
    restaurant_email   VARCHAR(150) NOT NULL UNIQUE,
    restaurant_phone   VARCHAR(20)  NOT NULL UNIQUE,
    cuisine_type       VARCHAR(80)  NOT NULL,
    restaurant_rating  DECIMAL(2,1) DEFAULT 0.0 CHECK (restaurant_rating BETWEEN 0 AND 5),
    account_password   VARCHAR(255) NOT NULL,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- TABLE: Contractor (Delivery Driver)
CREATE TABLE Contractor (
    contractor_id     INT AUTO_INCREMENT PRIMARY KEY,
    contractor_name   VARCHAR(100) NOT NULL,
    contractor_phone  VARCHAR(20)  NOT NULL UNIQUE,
    years_employed    INT DEFAULT 0,
    transportation    VARCHAR(50)  NOT NULL,
    location          VARCHAR(255),
    contractor_rating DECIMAL(2,1) DEFAULT 0.0 CHECK (contractor_rating BETWEEN 0 AND 5),
    account_password  VARCHAR(255) NOT NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- TABLE: Order
CREATE TABLE `Order` (
    order_id       INT AUTO_INCREMENT PRIMARY KEY,
    customer_id    INT NOT NULL,
    restaurant_id  INT NOT NULL,
    contractor_id  INT,
    order_datetime DATETIME DEFAULT CURRENT_TIMESTAMP,
    order_status   ENUM('in-process','ready','out-for-delivery','completed','canceled')
                   NOT NULL DEFAULT 'in-process',
    total_cost     DECIMAL(10,2) NOT NULL DEFAULT 0.00 CHECK (total_cost >= 0),
    FOREIGN KEY (customer_id)   REFERENCES Customer(customer_id),
    FOREIGN KEY (restaurant_id) REFERENCES Restaurant(restaurant_id),
    FOREIGN KEY (contractor_id) REFERENCES Contractor(contractor_id)
);


-- TABLE: Item  (belongs to a Restaurant)
CREATE TABLE Item (
    item_id       INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT NOT NULL,
    item_name     VARCHAR(150) NOT NULL,
    item_price    DECIMAL(8,2) NOT NULL CHECK (item_price >= 0),
    item_category VARCHAR(80),
    is_available  TINYINT(1) DEFAULT 1,
    FOREIGN KEY (restaurant_id) REFERENCES Restaurant(restaurant_id)
        ON DELETE CASCADE
);


-- TABLE: OrderItem  (many-to-many: Order <-> Item)
CREATE TABLE OrderItem (
    order_id  INT NOT NULL,
    item_id   INT NOT NULL,
    quantity  INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
    PRIMARY KEY (order_id, item_id),
    FOREIGN KEY (order_id) REFERENCES `Order`(order_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id)  REFERENCES Item(item_id)
);


-- TABLE: Payment  (1:1 with Order)
CREATE TABLE Payment (
    payment_id     INT AUTO_INCREMENT PRIMARY KEY,
    order_id       INT NOT NULL UNIQUE,
    payment_date   DATETIME DEFAULT CURRENT_TIMESTAMP,
    payment_method VARCHAR(50) NOT NULL,
    payment_status ENUM('pending','completed','refunded') NOT NULL DEFAULT 'pending',
    FOREIGN KEY (order_id) REFERENCES `Order`(order_id)
);


-- SAMPLE DATA

INSERT INTO Customer (customer_name, customer_address, customer_email, customer_phone, credit_card, account_password) VALUES
('Ellie Thach',  '123 Maple St, Lees Summit, MO', 'elliethach@gmail.com',  '8165550101', '4111111111111111', MD5('pass1')),
('Jana Frady',      '456 Oak Ave, Wichita, KS',     'janafrady@gmail.com',    '3165550102', '4111111111112222', MD5('pass2')),
('Michelle Bui', '789 Pine Rd, Liberty, MO',       'michelle@gmail.com',  '9135550103', '4111111111113333', MD5('pass3'));

INSERT INTO Restaurant (restaurant_name, restaurant_address, restaurant_email, restaurant_phone, cuisine_type, restaurant_rating, account_password) VALUES
('In-N-Out',    '10 Main St, Lawrence, KS',  'info@innout.com',  '7855551001', 'American', 4.2, MD5('rpass1')),
('KuraSushi',   '22 Oak Ln, Lawrence, KS',   'hello@kurasushi.com',     '7855551002', 'Japanese', 4.7, MD5('rpass2')),
('Taco Bell',    '55 Elm Dr, Lawrence, KS',   'orders@tacobell.com','7855551003', 'Mexican',  4.0, MD5('rpass3'));

INSERT INTO Contractor (contractor_name, contractor_phone, years_employed, transportation, location, contractor_rating, account_password) VALUES
('Dave Driver',  '9135552001', 3, 'Car',    'Downtown Lawrence', 4.5, MD5('dpass1')),
('Eve Express',  '9135552002', 1, 'Bicycle','East Lawrence',     4.8, MD5('dpass2'));

-- Items for In-N-Out (restaurant_id = 1)
INSERT INTO Item (restaurant_id, item_name, item_price, item_category, is_available) VALUES
(1, 'Classic Burger',   8.99,  'Burgers',  1),
(1, 'Cheese Burger',    9.99,  'Burgers',  1),
(1, 'Bacon Burger',    11.49,  'Burgers',  1),
(1, 'French Fries',     3.49,  'Sides',    1),
(1, 'Onion Rings',      3.99,  'Sides',    1),
(1, 'Chocolate Shake',  4.99,  'Drinks',   1);

-- Items for KuraSushi (restaurant_id = 2)
INSERT INTO Item (restaurant_id, item_name, item_price, item_category, is_available) VALUES
(2, 'Salmon Roll',     12.99, 'Rolls',    1),
(2, 'Spicy Tuna Roll', 13.49, 'Rolls',    1),
(2, 'Edamame',          4.99, 'Appetizers',1),
(2, 'Miso Soup',        2.99, 'Soup',     1);

-- Items for Taco Bell (restaurant_id = 3)
INSERT INTO Item (restaurant_id, item_name, item_price, item_category, is_available) VALUES
(3, 'Beef Taco',        3.49, 'Tacos',   1),
(3, 'Chicken Burrito',  8.99, 'Burritos', 1),
(3, 'Guacamole Chips',  5.49, 'Sides',   1),
(3, 'Horchata',         2.99, 'Drinks',  1);

-- Sample orders
INSERT INTO `Order` (customer_id, restaurant_id, contractor_id, order_status, total_cost) VALUES
(1, 1, 1, 'completed', 22.47),
(2, 2, 2, 'out-for-delivery', 26.48),
(3, 3, NULL, 'in-process', 12.48);

INSERT INTO OrderItem (order_id, item_id, quantity) VALUES
(1, 1, 1), (1, 4, 2), (1, 6, 1),
(2, 7, 1), (2, 8, 1),
(3, 11,2), (3, 14,1);

INSERT INTO Payment (order_id, payment_date, payment_method, payment_status) VALUES
(1, NOW(), 'Visa',    'completed'),
(2, NOW(), 'PayPal',  'completed'),
(3, NOW(), 'Apple Pay','pending');
