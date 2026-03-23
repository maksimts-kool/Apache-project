CREATE TABLE IF NOT EXISTS emoji_categories (
    slug        VARCHAR(80) PRIMARY KEY,
    emoji       VARCHAR(16) NOT NULL,
    name        VARCHAR(50) NOT NULL UNIQUE,
    is_default  TINYINT(1)  NOT NULL DEFAULT 0,
    created_by  INT NULL,
    created_at  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO emoji_categories (slug, emoji, name, is_default, created_by)
VALUES
    ('kawaii', '✨', 'Kawaii', 1, NULL),
    ('anime', '🎌', 'Anime', 1, NULL),
    ('funny', '😂', 'Funny', 1, NULL),
    ('sad', '😭', 'Sad', 1, NULL),
    ('love', '💖', 'Love', 1, NULL),
    ('angry', '😡', 'Angry', 1, NULL),
    ('animals', '🐱', 'Animals', 1, NULL)
ON DUPLICATE KEY UPDATE
    emoji = VALUES(emoji),
    name = VALUES(name),
    is_default = VALUES(is_default);
