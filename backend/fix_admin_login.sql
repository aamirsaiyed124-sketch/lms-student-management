-- Run this once if the database was imported before the Admin login fix.
-- Admin login: admin / Admin@123
USE lms_student_db;

UPDATE users
SET password_hash = '$2y$12$VNlQysGm5CK0IQo1bnZLuunK91bwFTPstNxaNbuxHLSjXcqpgUDf6',
    role = 'admin',
    status = 'Active'
WHERE username = 'admin';

-- Optional check:
SELECT id, username, role, status FROM users WHERE username IN ('admin', 'user');
