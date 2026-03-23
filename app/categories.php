<?php

function getDefaultCategoryDefinitions(): array
{
    return [
        ['slug' => 'kawaii', 'emoji' => '✨', 'name' => 'Kawaii'],
        ['slug' => 'anime', 'emoji' => '🎌', 'name' => 'Anime'],
        ['slug' => 'funny', 'emoji' => '😂', 'name' => 'Funny'],
        ['slug' => 'sad', 'emoji' => '😭', 'name' => 'Sad'],
        ['slug' => 'love', 'emoji' => '💖', 'name' => 'Love'],
        ['slug' => 'angry', 'emoji' => '😡', 'name' => 'Angry'],
        ['slug' => 'animals', 'emoji' => '🐱', 'name' => 'Animals'],
    ];
}

function getAvailableCategoryEmojis(): array
{
    return ['✨', '🌸', '🎌', '😂', '😭', '💖', '😡', '🐱', '🔥', '🌙', '🎮', '🍀', '🦄', '🍓', '☀️', '⭐'];
}

function categoriesTableExists(PDO $pdo): bool
{
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'emoji_categories'");
        $exists = (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        $exists = false;
    }

    return $exists;
}

function getFallbackCategories(): array
{
    return array_map(static function (array $category): array {
        $category['is_default'] = 1;
        return $category;
    }, getDefaultCategoryDefinitions());
}

function buildCategoryLabel(array $category): string
{
    $emoji = trim((string) ($category['emoji'] ?? ''));
    $name = trim((string) ($category['name'] ?? ''));

    return trim($emoji . ' ' . $name);
}

function getAllCategories(PDO $pdo): array
{
    if (!categoriesTableExists($pdo)) {
        return getFallbackCategories();
    }

    $stmt = $pdo->query(
        "SELECT slug, emoji, name, is_default
         FROM emoji_categories
         ORDER BY is_default DESC,
                  FIELD(slug, 'kawaii', 'anime', 'funny', 'sad', 'love', 'angry', 'animals'),
                  created_at ASC,
                  name ASC"
    );

    return $stmt->fetchAll();
}

function getCategoryBySlug(PDO $pdo, string $slug): ?array
{
    if ($slug === '') {
        return null;
    }

    if (!categoriesTableExists($pdo)) {
        foreach (getFallbackCategories() as $category) {
            if ($category['slug'] === $slug) {
                return $category;
            }
        }

        return null;
    }

    $stmt = $pdo->prepare('SELECT slug, emoji, name, is_default FROM emoji_categories WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $category = $stmt->fetch();

    return $category ?: null;
}

function categoryExists(PDO $pdo, string $slug): bool
{
    return getCategoryBySlug($pdo, $slug) !== null;
}

function normalizeCategoryName(string $name): string
{
    return preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
}

function stringLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function isAllowedCategoryEmoji(string $emoji): bool
{
    return in_array($emoji, getAvailableCategoryEmojis(), true);
}

function createCustomCategory(PDO $pdo, string $emoji, string $name, int $userId): array
{
    if (!categoriesTableExists($pdo)) {
        throw new RuntimeException('Category storage is not initialized yet. Apply the database migration first.');
    }

    $emoji = preg_replace('/\s+/u', '', trim($emoji)) ?? trim($emoji);
    $name = normalizeCategoryName($name);

    if ($emoji === '' || $name === '') {
        throw new InvalidArgumentException('Emoji and category name are required.');
    }

    if (stringLength($emoji) > 16) {
        throw new InvalidArgumentException('Emoji field is too long.');
    }

    if (!isAllowedCategoryEmoji($emoji)) {
        throw new InvalidArgumentException('Please choose an emoji from the list.');
    }

    if (stringLength($name) > 30) {
        throw new InvalidArgumentException('Category name must be 30 characters or less.');
    }

    $duplicateStmt = $pdo->prepare('SELECT slug FROM emoji_categories WHERE name = ? LIMIT 1');
    $duplicateStmt->execute([$name]);
    if ($duplicateStmt->fetch()) {
        throw new InvalidArgumentException('A category with this name already exists.');
    }

    $slug = 'custom-' . bin2hex(random_bytes(6));

    $stmt = $pdo->prepare(
        'INSERT INTO emoji_categories (slug, emoji, name, is_default, created_by) VALUES (?, ?, ?, 0, ?)'
    );
    $stmt->execute([$slug, $emoji, $name, $userId]);

    return [
        'slug' => $slug,
        'emoji' => $emoji,
        'name' => $name,
        'is_default' => 0,
        'label' => buildCategoryLabel(['emoji' => $emoji, 'name' => $name]),
    ];
}
