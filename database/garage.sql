-- ============================================================
--  garage.sql  —  Complete Database Schema
--  Project   : Garage Management System
--  Database  : garage_management
--  Engine    : InnoDB
--  Charset   : utf8mb4 / utf8mb4_unicode_ci
--  PHP Ver   : 8+  |  Connectivity: PDO Prepared Statements
-- ============================================================
--
--  HOW TO IMPORT (phpMyAdmin / XAMPP):
--  1. Start Apache + MySQL in the XAMPP Control Panel.
--  2. Open  http://localhost/phpmyadmin
--  3. Click "New" in the left sidebar.
--  4. Type  garage_management  →  click "Create".
--  5. Click the "garage_management" database in the sidebar.
--  6. Click the "Import" tab at the top.
--  7. Click "Choose File" → select this file (garage.sql).
--  8. Scroll down → click "Import".
--  9. You should see "Import has been successfully finished."
-- ============================================================

-- ────────────────────────────────────────────────────────────
--  Create & Select the Database
-- ────────────────────────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS `garage_management`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `garage_management`;

-- ────────────────────────────────────────────────────────────
--  Safety: disable FK checks while dropping / recreating
-- ────────────────────────────────────────────────────────────
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `service_history`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `vehicles`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `login_logs`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  TABLE 1 : users
--  Purpose  : All system accounts — admin, staff, and customer
--             portals.  Passwords stored as bcrypt hashes only.
-- ============================================================
CREATE TABLE `users` (
    `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100)     NOT NULL                   COMMENT 'Full name of the user',
    `email`      VARCHAR(150)     NOT NULL                   COMMENT 'Unique login email',
    `password`   VARCHAR(255)     NOT NULL                   COMMENT 'bcrypt hash — never plain text',
    `role`       ENUM('admin','staff','customer')
                                  NOT NULL DEFAULT 'staff'   COMMENT 'Access level',
    `phone`      VARCHAR(20)      DEFAULT NULL               COMMENT 'Contact number',
    `created_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Email must be unique (used as login identifier)
    UNIQUE  KEY `uq_users_email`  (`email`),

    -- Role is queried on every authentication check
    INDEX       `idx_users_role`  (`role`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='System user accounts (admin / staff / customer)';


-- ============================================================
--  TABLE 2 : login_logs
--  Purpose  : Audit trail of every login / logout event.
--             Useful for the admin panel and security reviews.
-- ============================================================
CREATE TABLE `login_logs` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED  NOT NULL                    COMMENT 'FK → users.id',
    `login_time`  DATETIME      NOT NULL                    COMMENT 'When the session started',
    `logout_time` DATETIME      DEFAULT NULL                COMMENT 'NULL until the user logs out',
    `ip_address`  VARCHAR(45)   DEFAULT NULL                COMMENT 'Supports IPv4 and IPv6',
    `status`      ENUM('success','failed','logged_in','logged_out')
                                NOT NULL DEFAULT 'logged_in' COMMENT 'Login session status',

    PRIMARY KEY (`id`),

    -- Most queries filter by user or by time
    INDEX `idx_loginlogs_user`  (`user_id`),
    INDEX `idx_loginlogs_time`  (`login_time`),

    CONSTRAINT `fk_loginlogs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE       -- remove logs if user is deleted
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracks every login and logout event per user';


-- ============================================================
--  TABLE 2.2 : login_attempts
--  Purpose   : Persistent server-side rate-limiting and brute-force
--              defense. Tracks failed attempts by email and IP.
-- ============================================================
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `email`        VARCHAR(150)  NOT NULL                    COMMENT 'Normalized account email',
    `ip_address`   VARCHAR(45)   NOT NULL                    COMMENT 'Client IP address (IPv4/IPv6)',
    `attempted_at` DATETIME      NOT NULL                    COMMENT 'Timestamp of failed attempt',

    PRIMARY KEY (`id`),

    INDEX `idx_attempts_email_time` (`email`, `attempted_at`),
    INDEX `idx_attempts_ip_time`    (`ip_address`, `attempted_at`),
    INDEX `idx_attempts_time`       (`attempted_at`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Persistent login rate-limiting tracking';


-- ============================================================
--  TABLE 2.5 : activity_logs
--  Purpose  : Audit trail of user actions across the system.
-- ============================================================
CREATE TABLE `activity_logs` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED  NOT NULL                    COMMENT 'FK → users.id',
    `role`        ENUM('admin','staff','customer') NOT NULL COMMENT 'Role of the user performing the action',
    `action`      VARCHAR(50)   NOT NULL                    COMMENT 'Type of action (e.g. BOOK_SERVICE, UPDATE_STATUS)',
    `entity_type` VARCHAR(50)   DEFAULT NULL                COMMENT 'Type of entity modified (e.g. vehicle, booking)',
    `entity_id`   INT UNSIGNED  DEFAULT NULL                COMMENT 'ID of the entity modified',
    `description` TEXT          DEFAULT NULL                COMMENT 'Human readable description',
    `ip_address`  VARCHAR(45)   DEFAULT NULL                COMMENT 'Supports IPv4 and IPv6',
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    INDEX `idx_activitylogs_user`  (`user_id`),
    INDEX `idx_activitylogs_role`  (`role`),
    INDEX `idx_activitylogs_entity` (`entity_type`, `entity_id`),
    INDEX `idx_activitylogs_time`  (`created_at`),

    CONSTRAINT `fk_activitylogs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracks user activities across the system';



-- ============================================================
--  TABLE 3 : customers
--  Purpose  : Garage customer profiles.
--             May be linked to a portal account (users.id),
--             but walk-in customers can exist without one.
-- ============================================================
CREATE TABLE `customers` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED  DEFAULT NULL               COMMENT 'FK → users.id (NULL = walk-in)',
    `name`       VARCHAR(100)  NOT NULL                   COMMENT 'Customer full name',
    `phone`      VARCHAR(20)   NOT NULL                   COMMENT 'Primary contact number',
    `email`      VARCHAR(150)  DEFAULT NULL               COMMENT 'Optional contact email',
    `address`    TEXT          DEFAULT NULL               COMMENT 'Full address',
    `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Phone is used for quick customer lookup at the counter
    INDEX `idx_customers_phone`   (`phone`),

    -- Email is searched when looking up returning customers
    INDEX `idx_customers_email`   (`email`),

    -- Linking portal account to customer record
    INDEX `idx_customers_user_id` (`user_id`),

    CONSTRAINT `fk_customers_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE SET NULL      -- keep customer record if portal account is deleted
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Garage customer profiles (walk-in or portal-linked)';


-- ============================================================
--  TABLE 4 : vehicles
--  Purpose  : Vehicles owned by customers.
--             Each vehicle belongs to exactly one customer.
-- ============================================================
CREATE TABLE `vehicles` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `customer_id`    INT UNSIGNED  NOT NULL                  COMMENT 'FK → customers.id',
    `vehicle_number` VARCHAR(30)   NOT NULL                  COMMENT 'Registration / plate number',
    `vehicle_type`   ENUM('car','motorcycle','truck','van','bus','other')
                                   NOT NULL DEFAULT 'car'    COMMENT 'Category of vehicle',
    `brand`          VARCHAR(50)   NOT NULL                  COMMENT 'Manufacturer e.g. Toyota',
    `model`          VARCHAR(50)   NOT NULL                  COMMENT 'Model name e.g. Corolla',
    `year`           YEAR          NOT NULL                  COMMENT 'Manufacturing year',
    `fuel_type`      ENUM('petrol','diesel','electric','hybrid','cng','other')
                                   NOT NULL DEFAULT 'petrol' COMMENT 'Fuel / energy type',
    `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                             ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- vehicle_number is searched often (at drop-off / pickup)
    -- Not UNIQUE across the whole table because data-entry mistakes
    -- happen; uniqueness is enforced at the application layer.
    INDEX `idx_vehicles_number`      (`vehicle_number`),

    -- Filter vehicles by customer
    INDEX `idx_vehicles_customer_id` (`customer_id`),

    CONSTRAINT `fk_vehicles_customer`
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)
        ON DELETE CASCADE       -- delete vehicles if customer is removed
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Customer-owned vehicles registered in the system';


-- ============================================================
--  TABLE 5 : services
--  Purpose  : Service catalogue — each row is one type of
--             service the garage offers (e.g. Oil Change).
-- ============================================================
CREATE TABLE `services` (
    `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `service_name`   VARCHAR(100)    NOT NULL                COMMENT 'Display name of the service',
    `description`    TEXT            DEFAULT NULL            COMMENT 'Detailed description',
    `price`          DECIMAL(10, 2)  NOT NULL DEFAULT 0.00   COMMENT 'Standard price in local currency',
    `estimated_time` DECIMAL(5, 2)   DEFAULT NULL            COMMENT 'Estimated hours to complete',
    `status`         ENUM('active','inactive')
                                     NOT NULL DEFAULT 'active' COMMENT 'Whether service is bookable',
    `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                              ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Staff filter by active services when creating bookings
    INDEX `idx_services_status` (`status`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Garage service catalogue with pricing';


-- ============================================================
--  TABLE 6 : bookings
--  Purpose  : Service appointments linking a customer's
--             vehicle to a specific service on a given date.
-- ============================================================
CREATE TABLE `bookings` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `customer_id`  INT UNSIGNED  NOT NULL                   COMMENT 'FK → customers.id',
    `vehicle_id`   INT UNSIGNED  NOT NULL                   COMMENT 'FK → vehicles.id',
    `service_id`   INT UNSIGNED  NOT NULL                   COMMENT 'FK → services.id',
    `booking_date` DATE          NOT NULL                   COMMENT 'Scheduled service date',
    `booking_time` TIME          DEFAULT NULL               COMMENT 'Scheduled time slot (optional)',
    `status`       ENUM('pending','confirmed','in_progress','completed','cancelled')
                                 NOT NULL DEFAULT 'pending' COMMENT 'Lifecycle status',
    `remarks`      TEXT          DEFAULT NULL               COMMENT 'Customer notes or staff comments',
    `service_address` TEXT       DEFAULT NULL               COMMENT 'Service location address',
    `area`         VARCHAR(100)  DEFAULT NULL               COMMENT 'Area / locality',
    `city`         VARCHAR(100)  DEFAULT NULL               COMMENT 'City name',
    `state`        VARCHAR(100)  DEFAULT NULL               COMMENT 'State / province',
    `pincode`      VARCHAR(20)   DEFAULT NULL               COMMENT 'Postal / ZIP code',
    `staff_id`     INT UNSIGNED  DEFAULT NULL               COMMENT 'FK → users.id (assigned staff member)',
    `rejection_reason` TEXT      DEFAULT NULL               COMMENT 'Reason for rejection (if cancelled by staff)',
    `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Common query patterns: filter by customer, status, or date
    INDEX `idx_bookings_customer_id`  (`customer_id`),
    INDEX `idx_bookings_vehicle_id`   (`vehicle_id`),
    INDEX `idx_bookings_service_id`   (`service_id`),
    INDEX `idx_bookings_status`       (`status`),
    INDEX `idx_bookings_date`         (`booking_date`),

    -- Composite index for dashboard queries: status + date range
    INDEX `idx_bookings_status_date`  (`status`, `booking_date`),

    CONSTRAINT `fk_bookings_customer`
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_bookings_vehicle`
        FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_bookings_service`
        FOREIGN KEY (`service_id`) REFERENCES `services`(`id`)
        ON DELETE RESTRICT      -- prevent deleting a service that has bookings
        ON UPDATE CASCADE,

    -- Staff member assigned to this booking (nullable)
    INDEX `idx_bookings_staff_id`   (`staff_id`),

    CONSTRAINT `fk_bookings_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`)
        ON DELETE SET NULL      -- keep booking if staff member is deleted
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Service bookings: customer + vehicle + service + date';


-- ============================================================
--  TABLE 7 : service_history
--  Purpose  : Permanent record of work actually performed.
--             Created when a booking reaches 'completed'.
--             Separate from bookings so the booking can be
--             deleted without losing the service record.
-- ============================================================
CREATE TABLE `service_history` (
    `id`                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `booking_id`          INT UNSIGNED    DEFAULT NULL              COMMENT 'FK → bookings.id (NULL if booking deleted)',
    `vehicle_id`          INT UNSIGNED    NOT NULL                  COMMENT 'FK → vehicles.id',
    `service_description` TEXT            NOT NULL                  COMMENT 'What was done (may differ from catalogue)',
    `service_cost`        DECIMAL(10, 2)  NOT NULL DEFAULT 0.00     COMMENT 'Actual amount charged',
    `mechanic_name`       VARCHAR(100)    DEFAULT NULL              COMMENT 'Name of the mechanic who performed the work',
    `service_date`        DATE            NOT NULL                  COMMENT 'Date the work was completed',
    `remarks`             TEXT            DEFAULT NULL              COMMENT 'Any additional notes',
    `created_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Vehicle history queries are the most common read pattern
    INDEX `idx_history_vehicle_id`  (`vehicle_id`),
    INDEX `idx_history_booking_id`  (`booking_id`),
    INDEX `idx_history_date`        (`service_date`),

    CONSTRAINT `fk_history_booking`
        FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`)
        ON DELETE SET NULL      -- keep history even if booking is deleted
        ON UPDATE CASCADE,

    CONSTRAINT `fk_history_vehicle`
        FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Permanent record of completed services per vehicle';


-- ============================================================
--  DEFAULT DATA
-- ============================================================

-- ── Default Admin Account ────────────────────────────────────
--  Email    : admin@garage.com
--  Password : Admin@1234
--
--  The hash below was generated with:
--      password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost' => 12])
--
--  PHP verifies it with:
--      password_verify($_POST['password'], $row['password'])   → true
--
--  IMPORTANT: Change this password immediately after first login.
-- ─────────────────────────────────────────────────────────────
INSERT INTO `users` (`name`, `email`, `password`, `role`, `phone`) VALUES
(
    'System Administrator',
    'admin@garage.com',
    '$2y$12$Ob/v7BQNOksew5lrHWve5Os5LKe4kUlFAq1yjFBWOfOWwalmiEXuC',
    'admin',
    '9000000000'
);

-- ── Sample Services Catalogue ────────────────────────────────
INSERT INTO `services`
    (`service_name`, `description`, `price`, `estimated_time`, `status`)
VALUES
    ('General Service',
     'Comprehensive multi-point vehicle inspection, engine tune-up, fluid check, and filter replacement.',
     2499.00, 3.50, 'active'),

    ('Oil Change',
     'Drain old engine oil, replace oil filter, and refill with fresh synthetic/mineral engine oil.',
     499.00, 1.00, 'active'),

    ('Brake Service',
     'Inspect brake pads, rotors, caliper servicing, brake fluid flush, and performance testing.',
     899.00, 2.00, 'active'),

    ('Engine Repair',
     'Complete diagnostic scan, cylinder head inspection, gasket replacement, and engine component repairs.',
     4999.00, 6.00, 'active'),

    ('AC Service',
     'Check AC compressor, leak detection, refrigerant gas recharge, and cabin air filter replacement.',
     1299.00, 2.50, 'active'),

    ('Battery Replacement',
     'Battery load testing, terminals cleaning, alternator output check, and new battery installation.',
     599.00, 0.50, 'active'),

    ('Wheel Alignment',
     'Four-wheel computerized alignment check and precision adjustment to manufacturer specifications.',
     699.00, 1.00, 'active'),

    ('Car Wash',
     'High-pressure exterior foam wash, underbody cleaning, interior vacuuming, and dashboard polish.',
     350.00, 1.00, 'active');


-- ============================================================
--  END OF SCHEMA
-- ============================================================
