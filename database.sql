CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_id BIGINT UNIQUE,
    name VARCHAR(255),
    username VARCHAR(255),
    language VARCHAR(10),
    points INT DEFAULT 0,
    joined_at DATETIME
);

CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_date DATE,
    text TEXT,
    options TEXT,
    correct_index INT,
    explanation TEXT
);

CREATE TABLE results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT,
    question_id INT,
    answered_at DATETIME,
    is_correct BOOLEAN
);
