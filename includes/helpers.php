<?php
require_once __DIR__ . '/../config/db.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
header('Content-Type: application/json; charset=utf-8');

function out($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function fail(string $msg, int $code = 400): void {
    out(['error' => $msg], $code);
}

function body(): array {
    $raw = json_decode(file_get_contents('php://input'), true);
    return is_array($raw) ? $raw : $_POST;
}

function clean($v): string {
    return trim(strip_tags((string)$v));
}

function require_role(string $role): void {
    if (empty($_SESSION['user'])) fail('Please log in first', 401);
    if ($_SESSION['user']['role'] !== $role) fail('Access denied', 403);
}

/** Returns [bool eligible, string reason] */
function eligibility(array $drive, array $student): array {
    if ((float)$student['cgpa'] < (float)$drive['min_cgpa'])
        return [false, 'Minimum CGPA ' . $drive['min_cgpa'] . ' required'];
    if ((int)$student['backlogs'] > (int)$drive['max_backlogs'])
        return [false, 'Maximum ' . $drive['max_backlogs'] . ' backlog(s) allowed'];
    $allowed = array_map('trim', explode(',', $drive['allowed_departments']));
    if (!in_array('ALL', $allowed, true) && !in_array($student['department'], $allowed, true))
        return [false, 'Your department is not eligible'];
    return [true, ''];
}

set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage());
    fail('Server error. Check PHP error log.', 500);
});
