-- Strathosphere Database Schema
-- MySQL 8.0+ compatible

CREATE DATABASE IF NOT EXISTS strathosphere CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE strathosphere;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) DEFAULT NULL,
    login_streak INT DEFAULT 0,
    last_login_date DATE DEFAULT NULL,
    last_streak_reward_date DATE DEFAULT NULL,
    role ENUM('student','admin') DEFAULT 'student',
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    points INT DEFAULT 0,
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'general',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    lat DECIMAL(10,8) NOT NULL,
    lng DECIMAL(11,8) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'building',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    location_id INT,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    category VARCHAR(50),
    cost INT DEFAULT 0,
    points INT DEFAULT 0,
    organizer VARCHAR(100),
    image VARCHAR(500),
    max_attendees INT DEFAULT 100,
    status ENUM('upcoming','ongoing','completed','cancelled') DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
);

CREATE TABLE event_attendees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_registration (event_id, user_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    ticket_code VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('free','paid','cancelled') DEFAULT 'free',
    phone_number VARCHAR(20) DEFAULT NULL,
    payment_reference VARCHAR(40) DEFAULT NULL,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(50),
    file_path VARCHAR(255),
    file_size VARCHAR(20),
    file_type VARCHAR(10),
    uploaded_by INT,
    downloads INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount INT NOT NULL,
    reason VARCHAR(255),
    type ENUM('earned','redeemed') DEFAULT 'earned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    priority ENUM('low','medium','high') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (name, email, password, phone_number, role, status, points) VALUES
('System Administrator', 'system.administrator@strathmore.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+254700000000', 'admin', 'approved', 0);

INSERT INTO locations (name, category, lat, lng, description, icon) VALUES
('Student Centre', 'academic', -1.30950000, 36.81450000, 'Main administrative and student services hub', 'building'),
('Strathmore Library', 'academic', -1.31000000, 36.81500000, 'Main library with 24-hour reading rooms', 'book'),
('Auditorium', 'events', -1.30900000, 36.81400000, 'Main auditorium seating 800 people', 'theater'),
('Cafeteria & Dining Hall', 'dining', -1.31050000, 36.81550000, 'Student dining with multiple cuisine options', 'utensils'),
('Sports Complex', 'sports', -1.30850000, 36.81350000, 'Gym, basketball court, and swimming pool', 'dumbbell'),
('ICT Labs', 'academic', -1.30980000, 36.81480000, 'Computer laboratories and innovation hub', 'computer'),
('University Chapel', 'religious', -1.31020000, 36.81520000, 'Multi-denominational worship center', 'church'),
('Main Parking', 'services', -1.30880000, 36.81380000, 'Student and visitor parking area', 'parking'),
('Science Block', 'academic', -1.30920000, 36.81420000, 'Laboratories for science and engineering', 'flask'),
('Business School', 'academic', -1.31050000, 36.81450000, 'Strathmore Business School building', 'briefcase');

INSERT INTO events (title, description, location_id, event_date, event_time, category, cost, points, organizer, image, max_attendees) VALUES
('Tech Expo 2026', 'Annual technology exhibition showcasing innovative student projects in AI, IoT, and software development.', 3, '2026-06-25', '10:00:00', 'academic', 0, 50, 'School of Computing', 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=600', 200),
('Campus Music Festival', 'Live performances from student bands and guest artists. Food stalls and cultural exhibitions included.', 5, '2026-06-28', '18:00:00', 'social', 200, 100, 'Student Council', 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=600', 500),
('Career & Internship Fair', 'Meet recruiters from top companies. Bring your CV for on-site interviews.', 1, '2026-06-22', '09:00:00', 'career', 0, 75, 'Career Services Office', 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=600', 300),
('Research Symposium', 'Postgraduate students present their research findings. Open to all faculties.', 2, '2026-06-20', '14:00:00', 'academic', 0, 60, 'Research Office', 'https://images.unsplash.com/photo-1531482615713-2afd69097998?w=600', 150),
('Entrepreneurship Bootcamp', 'Weekend intensive on startup creation. Mentorship from successful alumni entrepreneurs.', 10, '2026-07-05', '08:00:00', 'career', 500, 150, 'Strathmore Business School', 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=600', 100);

INSERT INTO documents (title, category, file_path, file_size, file_type, uploaded_by, downloads) VALUES
('Student Handbook 2026', 'policy', 'uploads/handbook.pdf', '2.4 MB', 'pdf', 1, 1240),
('Academic Calendar 2026', 'academic', 'uploads/calendar.pdf', '1.1 MB', 'pdf', 1, 3560),
('Club Registration Guide', 'guide', 'uploads/clubs.pdf', '850 KB', 'pdf', 1, 890),
('Campus Map (Printable)', 'map', 'uploads/map.pdf', '3.2 MB', 'pdf', 1, 2100),
('IT Usage Policy', 'policy', 'uploads/it_policy.pdf', '1.5 MB', 'pdf', 1, 670),
('Library Services Guide', 'guide', 'uploads/library.pdf', '920 KB', 'pdf', 1, 540);

INSERT INTO announcements (title, content, priority) VALUES
('System Launch', 'Welcome to Strathosphere! The new integrated campus navigation and events platform is now live.', 'high'),
('Tech Expo Registration', 'Register now for Tech Expo 2026 and earn 50 engagement points!', 'medium');

-- -----------------------------------------------------------------------------
-- Consolidated upgrade section (for existing databases)
-- Run this file as a single source of truth for both new setups and upgrades.
-- -----------------------------------------------------------------------------

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS status ENUM('pending','approved','rejected') DEFAULT 'pending' AFTER role;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) DEFAULT NULL AFTER password;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS login_streak INT DEFAULT 0 AFTER phone_number,
    ADD COLUMN IF NOT EXISTS last_login_date DATE DEFAULT NULL AFTER login_streak,
    ADD COLUMN IF NOT EXISTS last_streak_reward_date DATE DEFAULT NULL AFTER last_login_date;

UPDATE users
SET status = 'approved'
WHERE status IS NULL OR status = '';

UPDATE users
SET email = 'system.administrator@strathmore.edu'
WHERE email = 'admin@strathmore.edu';

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'general',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) DEFAULT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(40) DEFAULT NULL AFTER phone_number;