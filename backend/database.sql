-- ==========================================================
-- Learning Management System (Student Management System)
-- Production Ready MySQL Database Schema
-- Database: lms_student_db
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `lms_student_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `lms_student_db`;

-- 1. Students Table
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `enrollments`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_code` VARCHAR(20) NOT NULL UNIQUE,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `phone` VARCHAR(20) NOT NULL,
    `gender` ENUM('Male', 'Female', 'Other') NOT NULL DEFAULT 'Male',
    `dob` DATE NOT NULL,
    `department` VARCHAR(50) NOT NULL,
    `semester` INT NOT NULL DEFAULT 1,
    `status` ENUM('Active', 'Inactive', 'Suspended') NOT NULL DEFAULT 'Active',
    `address` VARCHAR(255) DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Courses Table
CREATE TABLE `courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_code` VARCHAR(20) NOT NULL UNIQUE,
    `title` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `credits` INT NOT NULL DEFAULT 3,
    `instructor_name` VARCHAR(100) NOT NULL,
    `department` VARCHAR(50) NOT NULL,
    `semester` INT NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Enrollments Table
CREATE TABLE `enrollments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `enrollment_date` DATE NOT NULL,
    `status` ENUM('Enrolled', 'Completed', 'Dropped') NOT NULL DEFAULT 'Enrolled',
    `grade` ENUM('A+', 'A', 'B+', 'B', 'C', 'D', 'F', 'Pending') NOT NULL DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_enrollment_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_enrollment_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY `unique_student_course` (`student_id`, `course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Attendance Table
CREATE TABLE `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `attendance_date` DATE NOT NULL,
    `status` ENUM('Present', 'Absent', 'Late', 'Excused') NOT NULL DEFAULT 'Present',
    `remarks` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_attendance_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY `unique_student_course_date` (`student_id`, `course_id`, `attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Announcements Table
CREATE TABLE `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(150) NOT NULL,
    `content` TEXT NOT NULL,
    `priority` ENUM('Low', 'Medium', 'High', 'Urgent') NOT NULL DEFAULT 'Medium',
    `target_department` VARCHAR(50) NOT NULL DEFAULT 'All',
    `posted_by` VARCHAR(100) NOT NULL DEFAULT 'Admin Office',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 6. Login Users Table (Admin + User)
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `full_name` VARCHAR(100) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'user') NOT NULL,
    `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Sample Seed Data
-- ==========================================================

-- Default login accounts
-- Admin: admin / Admin@123
-- User:   user  / User@123
INSERT INTO `users` (`username`, `full_name`, `password_hash`, `role`, `status`) VALUES
('admin', 'Academic Administrator', '$2y$12$VNlQysGm5CK0IQo1bnZLuunK91bwFTPstNxaNbuxHLSjXcqpgUDf6', 'admin', 'Active'),
('user', 'Student User', '$2y$12$0glClqxxQvuNRJ3KfcxVwOzBnd9z0yTCgm.tllozpaSs0DBGZLEVi', 'user', 'Active');


INSERT INTO `students` (`student_code`, `first_name`, `last_name`, `email`, `phone`, `gender`, `dob`, `department`, `semester`, `status`, `address`) VALUES
('STU-2026-001', 'Aarav', 'Sharma', 'aarav.sharma@campus.edu', '+91 98765 43210', 'Male', '2004-05-15', 'Computer Science', 4, 'Active', '120 Park Avenue, Block C'),
('STU-2026-002', 'Diya', 'Patel', 'diya.patel@campus.edu', '+91 98765 43211', 'Female', '2004-08-22', 'Computer Science', 4, 'Active', '45 Green Ridge Road'),
('STU-2026-003', 'Rohan', 'Verma', 'rohan.verma@campus.edu', '+91 98765 43212', 'Male', '2003-11-10', 'Information Technology', 6, 'Active', '89 Sunrise Blvd'),
('STU-2026-004', 'Ananya', 'Iyer', 'ananya.iyer@campus.edu', '+91 98765 43213', 'Female', '2005-02-18', 'Data Science', 2, 'Active', '24 Lakeview Apt, Flat 3B'),
('STU-2026-005', 'Kabir', 'Mehta', 'kabir.mehta@campus.edu', '+91 98765 43214', 'Male', '2004-07-09', 'Electrical Engineering', 4, 'Inactive', '12 Orchid Heights'),
('STU-2026-006', 'Sneha', 'Nair', 'sneha.nair@campus.edu', '+91 98765 43215', 'Female', '2003-09-30', 'Mechanical Engineering', 6, 'Active', '56 Silicon Town, Sector 4');

INSERT INTO `courses` (`course_code`, `title`, `description`, `credits`, `instructor_name`, `department`, `semester`) VALUES
('CS101', 'Database Management Systems', 'Relational database models, SQL, transactions, indexing, and normalization techniques.', 4, 'Dr. Rajesh Khanna', 'Computer Science', 4),
('CS202', 'Web Application Development', 'Full-stack web principles with modern client-side frameworks and PHP/MySQL backends.', 3, 'Prof. Shalini Roy', 'Computer Science', 4),
('IT301', 'Cloud Computing & DevOps', 'Virtualization, microservices, containerization with Docker, and CI/CD pipelines.', 3, 'Dr. Vikram Sen', 'Information Technology', 6),
('DS105', 'Applied Data Science & Python', 'Data exploration, pandas, statistical modeling, and machine learning foundations.', 4, 'Prof. Neha Deshmukh', 'Data Science', 2),
('EE204', 'Signals and Linear Systems', 'Continuous and discrete time signals, Fourier transform, and system filtering.', 3, 'Dr. Amitav Ghosh', 'Electrical Engineering', 4);

INSERT INTO `enrollments` (`student_id`, `course_id`, `enrollment_date`, `status`, `grade`) VALUES
(1, 1, '2026-01-15', 'Enrolled', 'Pending'),
(1, 2, '2026-01-15', 'Enrolled', 'A'),
(2, 1, '2026-01-15', 'Enrolled', 'A+'),
(2, 2, '2026-01-16', 'Enrolled', 'Pending'),
(3, 3, '2026-01-10', 'Enrolled', 'B+'),
(4, 4, '2026-01-18', 'Enrolled', 'Pending'),
(5, 5, '2026-01-12', 'Dropped', 'Pending'),
(6, 1, '2026-01-20', 'Completed', 'A');

INSERT INTO `attendance` (`student_id`, `course_id`, `attendance_date`, `status`, `remarks`) VALUES
(1, 1, '2026-09-15', 'Present', 'On time'),
(2, 1, '2026-09-15', 'Present', 'Active in lab'),
(6, 1, '2026-09-15', 'Absent', 'Medical leave'),
(1, 1, '2026-09-16', 'Present', 'Submitted assignment'),
(2, 1, '2026-09-16', 'Late', 'Arrived 10 mins late'),
(6, 1, '2026-09-16', 'Present', 'Back from leave'),
(1, 2, '2026-09-16', 'Present', 'Project discussion'),
(2, 2, '2026-09-16', 'Present', 'Sprint demo');

INSERT INTO `announcements` (`title`, `content`, `priority`, `target_department`, `posted_by`) VALUES
('Mid-Semester Minor Project Review Date', 'The first evaluation for all minor project submissions is scheduled for next Friday. Keep your repositories updated.', 'Urgent', 'All', 'Dean of Academics'),
('DBMS Lab Practical Examination', 'Database practical viva and query examination will be conducted in Computer Lab 3 at 10:00 AM.', 'High', 'Computer Science', 'Dr. Rajesh Khanna'),
('Campus Hackathon 2026 Registrations Open', 'Annual 36-hour hackathon registrations are live. Prizes worth $5000 and internship offers.', 'Medium', 'All', 'Student Council'),
('Cloud Lab Maintenance Scheduled', 'Cloud sandbox will experience a brief 1-hour downtime on Saturday 2:00 AM for server upgrades.', 'Low', 'Information Technology', 'IT Support Desk');
