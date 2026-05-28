CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `full_name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `partner1` VARCHAR(100),
  `partner2` VARCHAR(100),
  `event_date` DATE NOT NULL,
  `venue` VARCHAR(255),
  `total_budget` DECIMAL(12,2) DEFAULT 0,
  `status` ENUM('planning','confirmed','completed') NOT NULL DEFAULT 'planning',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_event_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `category` ENUM('venue','catering','decor','photo','music','documents','transport','beauty','honeymoon','other') NOT NULL,
  `due_date` DATE,
  `priority` ENUM('low','medium','high') DEFAULT 'medium',
  `status` ENUM('not_started','in_progress','done') DEFAULT 'not_started',
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_task_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `budget_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255),
  `planned_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `actual_amount` DECIMAL(12,2) DEFAULT 0,
  `paid_date` DATE,
  `vendor_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_budget_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `guests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100),
  `phone` VARCHAR(20),
  `side` ENUM('bride','groom','mutual') NOT NULL DEFAULT 'mutual',
  `category` ENUM('family','friends','colleagues','other') DEFAULT 'friends',
  `rsvp_status` ENUM('pending','invited','confirmed','declined') DEFAULT 'pending',
  `dietary_notes` TEXT,
  `needs_transfer` TINYINT(1) DEFAULT 0,
  `table_number` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_guest_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vendors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `company_name` VARCHAR(200) NOT NULL,
  `category` ENUM('photo','video','florist','catering','music','decor','transport','beauty','venue','other') NOT NULL,
  `contact_name` VARCHAR(100),
  `phone` VARCHAR(20),
  `email` VARCHAR(100),
  `website` VARCHAR(255),
  `contract_amount` DECIMAL(12,2) DEFAULT 0,
  `deposit_paid` DECIMAL(12,2) DEFAULT 0,
  `status` ENUM('considering','booked','deposit_paid','fully_paid','cancelled') DEFAULT 'considering',
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_vendor_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `budget_items`
  ADD CONSTRAINT `fk_budget_vendor`
  FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE SET NULL;
