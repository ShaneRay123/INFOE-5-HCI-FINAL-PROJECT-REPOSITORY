-- Create database
CREATE DATABASE IF NOT EXISTS syu;
USE syu;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    course_year VARCHAR(50),
    section VARCHAR(50),
    profile_photo VARCHAR(255) DEFAULT 'default.jpg',
    birthday DATE,
    address TEXT,
    role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Assessments table
CREATE TABLE IF NOT EXISTS assessments (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Questions table
CREATE TABLE IF NOT EXISTS questions (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    assessment_id INT,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'text', 'scale') NOT NULL,
    options TEXT,
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE
);

-- Student assessments table
CREATE TABLE IF NOT EXISTS student_assessments (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    assessment_id INT,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    submitted_at DATETIME,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (assessment_id) REFERENCES assessments(id)
);

-- Appointments table
CREATE TABLE IF NOT EXISTS appointments (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    counselor_id INT,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('pending', 'accepted', 'declined', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (counselor_id) REFERENCES users(id)
);

-- Responses table
CREATE TABLE IF NOT EXISTS responses (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    student_assessment_id INT,
    question_id INT,
    response_text TEXT,
    FOREIGN KEY (student_assessment_id) REFERENCES student_assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id)
);

-- Default admin account
-- Username: admin
-- Password: admin123 (hashed)
INSERT INTO users (username, password, full_name, email, role) 
VALUES ('admin', '$2y$10$jLd4q4XhYE72v.zD7AY.UuwqQUvP.BjnKA0y0jg2cHjJi3W/NM4VK', 'System Administrator', 'admin@gmail.com', 'admin');
