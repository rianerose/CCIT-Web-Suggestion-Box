<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('DEFAULT_ADMIN_USERNAME')) {
    define('DEFAULT_ADMIN_USERNAME', 'admin');
}

if (!defined('DEFAULT_ADMIN_FULL_NAME')) {
    define('DEFAULT_ADMIN_FULL_NAME', 'System Administrator');
}

if (!defined('DEFAULT_ADMIN_PASSWORD_HASH')) {
    define('DEFAULT_ADMIN_PASSWORD_HASH', '$2y$10$V.R2qDe2uSfN6MYfRomsmu9Po5x10zrG4lVTmP7bBPJXM8ENWeSyC');
}

/**
 * @return array<string, mixed>
 */
function get_hosting_overrides(): array
{
    static $overrides = null;

    if (is_array($overrides)) {
        return $overrides;
    }

    $overrides = [];
    $candidates = [
        __DIR__ . '/config.hosting.php',
        __DIR__ . '/config.local.php',
    ];

    foreach ($candidates as $file) {
        if (!is_readable($file)) {
            continue;
        }

        $data = include $file;

        if (is_array($data)) {
            $overrides = array_merge($overrides, $data);
        }
    }

    return $overrides;
}

function resolve_config_value(string $primaryKey, array $aliases, string $default): string
{
    $overrides = get_hosting_overrides();
    $keys = array_merge([$primaryKey], $aliases);

    foreach ($keys as $key) {
        if (array_key_exists($key, $overrides)) {
            $value = $overrides[$key];

            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            $stringValue = trim((string) $value);
            if ($stringValue !== '') {
                return $stringValue;
            }
        }

        $envValue = getenv($key);
        if ($envValue !== false) {
            $trimmed = trim((string) $envValue);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        if (array_key_exists($key, $_ENV)) {
            $envEntry = $_ENV[$key];
            if ($envEntry !== null) {
                if (is_bool($envEntry)) {
                    return $envEntry ? '1' : '0';
                }

                $stringValue = trim((string) $envEntry);
                if ($stringValue !== '') {
                    return $stringValue;
                }
            }
        }

        if (array_key_exists($key, $_SERVER)) {
            $serverEntry = $_SERVER[$key];
            if ($serverEntry !== null) {
                if (is_bool($serverEntry)) {
                    return $serverEntry ? '1' : '0';
                }

                $stringValue = trim((string) $serverEntry);
                if ($stringValue !== '') {
                    return $stringValue;
                }
            }
        }

        if (defined($key)) {
            $constantValue = constant($key);

            if ($constantValue === null) {
                continue;
            }

            if (is_bool($constantValue)) {
                return $constantValue ? '1' : '0';
            }

            $stringValue = trim((string) $constantValue);
            if ($stringValue !== '') {
                return $stringValue;
            }
        }
    }

    return $default;
}

function sanitize_username(string $username): string
{
    return strtolower(trim($username));
}

function sanitize_full_name(string $fullName): string
{
    $normalized = preg_replace('/\s+/', ' ', trim($fullName));

    return $normalized === null ? trim($fullName) : $normalized;
}

function get_db_connection(): \PDO
{
    static $pdo = null;

    if ($pdo instanceof \PDO) {
        return $pdo;
    }

    $host = resolve_config_value('DB_HOST', ['MYSQL_HOST', 'DATABASE_HOST', 'SQL_HOST'], '127.0.0.1');
    $port = resolve_config_value('DB_PORT', ['MYSQL_PORT', 'DATABASE_PORT', 'SQL_PORT'], '3306');
    $database = resolve_config_value('DB_NAME', ['MYSQL_DB', 'MYSQL_DATABASE', 'DATABASE_NAME'], 'my_app');
    $username = resolve_config_value('DB_USER', ['MYSQL_USER', 'MYSQL_USERNAME', 'DATABASE_USER'], 'root');
    $password = resolve_config_value('DB_PASSWORD', ['MYSQL_PASS', 'MYSQL_PASSWORD', 'DATABASE_PASSWORD'], '');
    $charset = resolve_config_value('DB_CHARSET', ['MYSQL_CHARSET'], 'utf8mb4');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset);

    $options = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new \PDO($dsn, $username, $password, $options);
    ensure_schema($pdo);

    return $pdo;
}

function ensure_schema(\PDO $pdo): void
{
    ensure_users_table_exists($pdo);
    ensure_suggestions_table_exists($pdo);
    ensure_suggestion_replies_table_exists($pdo);
    ensure_default_admin_exists($pdo);
}

function ensure_users_table_exists(\PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (\n            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n            username VARCHAR(190) NOT NULL UNIQUE,\n            full_name VARCHAR(190) NOT NULL,\n            role ENUM('admin','student') NOT NULL DEFAULT 'student',\n            password VARCHAR(255) NOT NULL,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function ensure_suggestions_table_exists(\PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS suggestions (\n            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n            student_id INT UNSIGNED NOT NULL,\n            title VARCHAR(190) NOT NULL,\n            content TEXT NOT NULL,\n            is_anonymous TINYINT(1) NOT NULL DEFAULT 0,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n            CONSTRAINT fk_suggestions_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function ensure_suggestion_replies_table_exists(\PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS suggestion_replies (\n            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n            suggestion_id INT UNSIGNED NOT NULL,\n            admin_id INT UNSIGNED NOT NULL,\n            message TEXT NOT NULL,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n            CONSTRAINT fk_replies_suggestion FOREIGN KEY (suggestion_id) REFERENCES suggestions(id) ON DELETE CASCADE,\n            CONSTRAINT fk_replies_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function ensure_default_admin_exists(\PDO $pdo): void
{
    $username = sanitize_username(DEFAULT_ADMIN_USERNAME);

    $statement = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $statement->execute(['username' => $username]);

    if ($statement->fetchColumn()) {
        return;
    }

    $insert = $pdo->prepare('INSERT INTO users (username, full_name, role, password) VALUES (:username, :full_name, :role, :password)');
    $insert->execute([
        'username' => $username,
        'full_name' => DEFAULT_ADMIN_FULL_NAME,
        'role' => 'admin',
        'password' => DEFAULT_ADMIN_PASSWORD_HASH,
    ]);
}

/**
 * @return array{id: int, username: string, full_name: string, role: string, password: string, created_at: string}|null
 */
function find_user(string $username): ?array
{
    $pdo = get_db_connection();
    $sql = 'SELECT id, username, full_name, role, password, created_at FROM users WHERE username = :username LIMIT 1';
    $statement = $pdo->prepare($sql);
    $statement->execute(['username' => sanitize_username($username)]);

    $user = $statement->fetch();

    return $user === false ? null : $user;
}

/**
 * @return array{id: int, username: string, full_name: string, role: string, created_at: string}
 */
function normalize_user_row(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'username' => (string) $user['username'],
        'full_name' => (string) $user['full_name'],
        'role' => (string) $user['role'],
        'created_at' => (string) $user['created_at'],
    ];
}

/**
 * @return array{success: bool, error?: string, user?: array{id: int, username: string, full_name: string, role: string, created_at: string}}
 */
function register_user(string $username, string $password, string $fullName): array
{
    $username = sanitize_username($username);
    $fullName = sanitize_full_name($fullName);

    if ($username === '') {
        return ['success' => false, 'error' => 'Username is required.'];
    }

    if ($fullName === '') {
        return ['success' => false, 'error' => 'Full name is required.'];
    }

    if (strlen($password) < 6) {
        return ['success' => false, 'error' => 'Password must be at least 6 characters.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $pdo = get_db_connection();
        $sql = 'INSERT INTO users (username, full_name, role, password) VALUES (:username, :full_name, :role, :password)';
        $statement = $pdo->prepare($sql);
        $statement->execute([
            'username' => $username,
            'full_name' => $fullName,
            'role' => 'student',
            'password' => $hash,
        ]);

        $userId = (int) $pdo->lastInsertId();

        $userRow = get_user_by_id($userId);
        if ($userRow === null) {
            $userRow = find_user($username);
        }

        if ($userRow !== null) {
            return ['success' => true, 'user' => normalize_user_row($userRow)];
        }
    } catch (\PDOException $exception) {
        if (isset($exception->errorInfo[1]) && (int) $exception->errorInfo[1] === 1062) {
            return ['success' => false, 'error' => 'Username already exists.'];
        }

        error_log('Failed to register user: ' . $exception->getMessage());
        return ['success' => false, 'error' => 'An unexpected error occurred.'];
    }

    return ['success' => true];
}

/**
 * @return array{id: int, username: string, full_name: string, role: string, password: string, created_at: string}|null
 */
function get_user_by_id(int $userId): ?array
{
    $pdo = get_db_connection();
    $statement = $pdo->prepare('SELECT id, username, full_name, role, password, created_at FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $userId]);

    $user = $statement->fetch();

    return $user === false ? null : $user;
}

/**
 * @return array{id: int, username: string, full_name: string, role: string, created_at: string}|null
 */
function authenticate_user(string $username, string $password): ?array
{
    $user = find_user($username);
    if ($user === null) {
        return null;
    }

    if (!password_verify($password, $user['password'])) {
        return null;
    }

    return normalize_user_row($user);
}

function ensure_logged_in(): void
{
    if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
        header('Location: ../login/index.php');
        exit;
    }
}

function ensure_admin(): void
{
    ensure_logged_in();

    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo 'Forbidden: Administrator access required.';
        exit;
    }
}

/**
 * @return array{success: bool, error?: string, suggestion_id?: int}
 */
function create_suggestion(int $studentId, string $title, string $content, bool $displayIdentity): array
{
    $title = trim($title);
    $content = trim($content);

    if ($title === '') {
        return ['success' => false, 'error' => 'Title is required.'];
    }

    if ($content === '') {
        return ['success' => false, 'error' => 'Suggestion details are required.'];
    }

    $pdo = get_db_connection();

    $statement = $pdo->prepare('INSERT INTO suggestions (student_id, title, content, is_anonymous) VALUES (:student_id, :title, :content, :is_anonymous)');
    $statement->execute([
        'student_id' => $studentId,
        'title' => $title,
        'content' => $content,
        'is_anonymous' => $displayIdentity ? 0 : 1,
    ]);

    return ['success' => true, 'suggestion_id' => (int) $pdo->lastInsertId()];
}

/**
 * @return array{success: bool, error?: string}
 */
function update_suggestion(int $suggestionId, int $studentId, string $title, string $content, bool $displayIdentity): array
{
    $title = trim($title);
    $content = trim($content);

    if ($title === '') {
        return ['success' => false, 'error' => 'Title is required.'];
    }

    if ($content === '') {
        return ['success' => false, 'error' => 'Suggestion details are required.'];
    }

    $pdo = get_db_connection();

    $exists = $pdo->prepare('SELECT student_id FROM suggestions WHERE id = :id LIMIT 1');
    $exists->execute(['id' => $suggestionId]);

    $row = $exists->fetch();

    if ($row === false || (int) $row['student_id'] !== $studentId) {
        return ['success' => false, 'error' => 'Suggestion not found or access denied.'];
    }

    $update = $pdo->prepare('UPDATE suggestions SET title = :title, content = :content, is_anonymous = :is_anonymous WHERE id = :id');
    $update->execute([
        'id' => $suggestionId,
        'title' => $title,
        'content' => $content,
        'is_anonymous' => $displayIdentity ? 0 : 1,
    ]);

    return ['success' => true];
}

/**
 * @return array{success: bool, error?: string}
 */
function delete_suggestion_for_student(int $suggestionId, int $studentId): array
{
    $pdo = get_db_connection();

    $delete = $pdo->prepare('DELETE FROM suggestions WHERE id = :id AND student_id = :student_id');
    $delete->execute([
        'id' => $suggestionId,
        'student_id' => $studentId,
    ]);

    if ($delete->rowCount() === 0) {
        return ['success' => false, 'error' => 'Suggestion not found or access denied.'];
    }

    return ['success' => true];
}

/**
 * @return array{success: bool, error?: string}
 */
function delete_suggestion_for_admin(int $suggestionId): array
{
    $pdo = get_db_connection();

    $delete = $pdo->prepare('DELETE FROM suggestions WHERE id = :id');
    $delete->execute(['id' => $suggestionId]);

    if ($delete->rowCount() === 0) {
        return ['success' => false, 'error' => 'Suggestion not found.'];
    }

    return ['success' => true];
}

/**
 * @return array{success: bool, error?: string}
 */
function delete_suggestion_reply(int $replyId, ?int $adminId = null, bool $allowAnyAdmin = false): array
{
    $pdo = get_db_connection();

    $conditions = 'id = :id';
    $params = ['id' => $replyId];

    if (!$allowAnyAdmin) {
        $conditions .= ' AND admin_id = :admin_id';
        $params['admin_id'] = $adminId;
    }

    $delete = $pdo->prepare(sprintf('DELETE FROM suggestion_replies WHERE %s', $conditions));
    $delete->execute($params);

    if ($delete->rowCount() === 0) {
        return ['success' => false, 'error' => 'Reply not found or access denied.'];
    }

    return ['success' => true];
}

/**
 * @return array{success: bool, error?: string}
 */
function add_suggestion_reply(int $suggestionId, int $adminId, string $message): array
{
    $message = trim($message);

    if ($message === '') {
        return ['success' => false, 'error' => 'Reply message is required.'];
    }

    $pdo = get_db_connection();

    $suggestionExists = $pdo->prepare('SELECT 1 FROM suggestions WHERE id = :id LIMIT 1');
    $suggestionExists->execute(['id' => $suggestionId]);

    if (!$suggestionExists->fetchColumn()) {
        return ['success' => false, 'error' => 'Suggestion not found.'];
    }

    $statement = $pdo->prepare('INSERT INTO suggestion_replies (suggestion_id, admin_id, message) VALUES (:suggestion_id, :admin_id, :message)');
    $statement->execute([
        'suggestion_id' => $suggestionId,
        'admin_id' => $adminId,
        'message' => $message,
    ]);

    return ['success' => true];
}

/**
 * @return array<int, array{id: int, title: string, content: string, is_anonymous: bool, created_at: string, replies: array<int, array{id: int, message: string, created_at: string, admin_name: string}>>>
 */
function get_student_suggestions(int $studentId): array
{
    $pdo = get_db_connection();

    $sql = 'SELECT 
                s.id AS suggestion_id,
                s.title,
                s.content,
                s.is_anonymous,
                s.created_at AS suggestion_created_at,
                r.id AS reply_id,
                r.message AS reply_message,
                r.created_at AS reply_created_at,
                admin.full_name AS admin_full_name
            FROM suggestions s
            LEFT JOIN suggestion_replies r ON r.suggestion_id = s.id
            LEFT JOIN users admin ON admin.id = r.admin_id
            WHERE s.student_id = :student_id
            ORDER BY s.created_at DESC, r.created_at ASC';

    $statement = $pdo->prepare($sql);
    $statement->execute(['student_id' => $studentId]);

    $rows = $statement->fetchAll();

    return format_suggestions_with_replies($rows, false);
}

/**
 * @return array<int, array{id: int, title: string, content: string, is_anonymous: bool, created_at: string, student_name: string, student_username: string, replies: array<int, array{id: int, message: string, created_at: string, admin_name: string}>>>
 */
function get_all_suggestions_for_admin(): array
{
    $pdo = get_db_connection();

    $sql = 'SELECT 
                s.id AS suggestion_id,
                s.title,
                s.content,
                s.is_anonymous,
                s.created_at AS suggestion_created_at,
                student.full_name AS student_full_name,
                student.username AS student_username,
                r.id AS reply_id,
                r.message AS reply_message,
                r.created_at AS reply_created_at,
                admin.full_name AS admin_full_name
            FROM suggestions s
            INNER JOIN users student ON student.id = s.student_id
            LEFT JOIN suggestion_replies r ON r.suggestion_id = s.id
            LEFT JOIN users admin ON admin.id = r.admin_id
            ORDER BY s.created_at DESC, r.created_at ASC';

    $rows = $pdo->query($sql)->fetchAll();

    return format_suggestions_with_replies($rows, true);
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function format_suggestions_with_replies(array $rows, bool $includeStudent): array
{
    $suggestions = [];

    foreach ($rows as $row) {
        $suggestionId = (int) $row['suggestion_id'];

        if (!isset($suggestions[$suggestionId])) {
            $suggestions[$suggestionId] = [
                'id' => $suggestionId,
                'title' => (string) $row['title'],
                'content' => (string) $row['content'],
                'is_anonymous' => (bool) $row['is_anonymous'],
                'created_at' => (string) $row['suggestion_created_at'],
                'replies' => [],
            ];

            if ($includeStudent) {
                $suggestions[$suggestionId]['student_name'] = (string) $row['student_full_name'];
                $suggestions[$suggestionId]['student_username'] = (string) $row['student_username'];
            }
        }

        if (!empty($row['reply_id'])) {
            $suggestions[$suggestionId]['replies'][] = [
                'id' => (int) $row['reply_id'],
                'message' => (string) $row['reply_message'],
                'created_at' => (string) $row['reply_created_at'],
                'admin_name' => (string) ($row['admin_full_name'] ?? 'Administrator'),
            ];
        }
    }

    return array_values($suggestions);
}

