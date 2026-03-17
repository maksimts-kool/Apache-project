-- Настройка кодировки для импорта
SET NAMES utf8mb4;

-- Создание таблиц
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE emojis (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    symbol       TEXT         NOT NULL,
    name         VARCHAR(100) NOT NULL,
    category     VARCHAR(50)  NOT NULL,
    tags         VARCHAR(255),
    description  TEXT,
    is_anonymous TINYINT(1)   DEFAULT 0,
    user_id      INT,
    downloads    INT          DEFAULT 0,
    likes        INT          DEFAULT 0,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE emoji_likes (
    user_id  INT NOT NULL,
    emoji_id INT NOT NULL,
    PRIMARY KEY (user_id, emoji_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (emoji_id) REFERENCES emojis(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Добавление тестовых данных (Seed data)
INSERT INTO users (username, email, password) VALUES
    ('kawaii_admin', 'admin@kawaiiemoji.dev', '$2y$10$SJdp0z.YEYKJzhULf/HdFuVT2EanNf5xi8ff/dmW29TW4g7KJgK0S');

INSERT INTO emojis (symbol, name, category, tags, user_id) VALUES
    ('(づ｡◕‿‿◕｡)づ', 'Big Hug',     'kawaii', 'hug,cute,love',  1),
    ('(◕‿◕✿)',       'Flower Smile', 'kawaii', 'smile,flower',   1),
    ('(ノಠ益ಠ)ノ彡',  'Rage Flip',   'funny',  'rage,angry,flip', 1);

-- Создание пользователя приложения и выдача прав (SELECT, INSERT, UPDATE, DELETE)
CREATE USER IF NOT EXISTS 'kawaii_app'@'%' IDENTIFIED BY 'app_password_123';
GRANT SELECT, INSERT, UPDATE, DELETE ON kawaiiemoji_db.* TO 'kawaii_app'@'%';
FLUSH PRIVILEGES;