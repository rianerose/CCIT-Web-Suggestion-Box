<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

ensure_logged_in();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/**
 * @param string $datetime
 */
function dashboard_format_datetime(string $datetime): string
{
    $timestamp = strtotime($datetime);

    if ($timestamp === false) {
        return $datetime;
    }

    return date('M j, Y g:i A', $timestamp);
}

/**
 * @param array<int, array<string, mixed>> $suggestions
 */
function map_suggestions_for_response(array $suggestions, bool $includeStudent): array
{
    $mapped = [];

    foreach ($suggestions as $suggestion) {
        $entry = [
            'id' => (int) $suggestion['id'],
            'content' => (string) $suggestion['content'],
            'is_anonymous' => (bool) $suggestion['is_anonymous'],
            'created_at' => (string) $suggestion['created_at'],
            'created_at_human' => dashboard_format_datetime((string) $suggestion['created_at']),
            'replies' => [],
        ];

        if ($includeStudent) {
            $entry['student_name'] = (string) $suggestion['student_name'];
            $entry['student_username'] = (string) $suggestion['student_username'];
        }

        foreach ($suggestion['replies'] as $reply) {
            $entry['replies'][] = [
                'id' => (int) $reply['id'],
                'message' => (string) $reply['message'],
                'created_at' => (string) $reply['created_at'],
                'created_at_human' => dashboard_format_datetime((string) $reply['created_at']),
                'admin_name' => (string) $reply['admin_name'],
            ];
        }

        $mapped[] = $entry;
    }

    return $mapped;
}

try {
    /** @var array{id: int, role: string} $currentUser */
    $currentUser = $_SESSION['user'];
    $isAdmin = $currentUser['role'] === 'admin';

    if ($isAdmin) {
        $requestedFilter = normalize_admin_suggestion_filter($_GET['filter'] ?? null);
        $suggestions = get_all_suggestions_for_admin($requestedFilter);
        $payload = [
            'success' => true,
            'role' => 'admin',
            'filter' => $requestedFilter,
            'suggestions' => map_suggestions_for_response($suggestions, true),
        ];
    } else {
        $suggestions = get_student_suggestions((int) $currentUser['id']);
        $payload = [
            'success' => true,
            'role' => 'student',
            'suggestions' => map_suggestions_for_response($suggestions, false),
        ];
    }

    $payload['server_time'] = date('c');

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        throw new RuntimeException('Unable to encode response as JSON.');
    }

    echo $json;
} catch (Throwable $throwable) {
    http_response_code(500);
    $errorResponse = [
        'success' => false,
        'error' => 'Unable to fetch suggestions at this time.',
    ];

    if (defined('APP_DEBUG') && APP_DEBUG) {
        $errorResponse['details'] = $throwable->getMessage();
    }

    $json = json_encode($errorResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        echo '{"success":false,"error":"Unexpected error."}';
        exit;
    }

    echo $json;
}

exit;
