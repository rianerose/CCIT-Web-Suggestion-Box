-- SuggestionBox application schema
CREATE DATABASE IF NOT EXISTS `my_app`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `my_app`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `suggestion_replies`;
DROP TABLE IF EXISTS `suggestions`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(190) NOT NULL UNIQUE,
    `full_name` VARCHAR(190) NOT NULL,
    `role` ENUM('admin','student') NOT NULL DEFAULT 'student',
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `suggestions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(190) NOT NULL,
    `content` TEXT NOT NULL,
    `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_suggestions_student`
      FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)
      ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `suggestion_replies` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `suggestion_id` INT UNSIGNED NOT NULL,
    `admin_id` INT UNSIGNED NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_replies_suggestion`
      FOREIGN KEY (`suggestion_id`) REFERENCES `suggestions`(`id`)
      ON DELETE CASCADE,
    CONSTRAINT `fk_replies_admin`
      FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`)
      ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

INSERT INTO `users` (`username`, `full_name`, `role`, `password`)
VALUES
    ('admin', 'System Administrator', 'admin', '$2y$10$V.R2qDe2uSfN6MYfRomsmu9Po5x10zrG4lVTmP7bBPJXM8ENWeSyC');
