<?php
require_once __DIR__ . '/../includes/helpers.php';

$action = $_GET['action'] ?? '';
$pdo = db();

switch ($action) {
    case 'login':
        $d = body();
        $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ?');
        $stmt->execute([strtolower(clean($d['email'] ?? ''))]);
        $u = $stmt->fetch();
        if (!$u || !password_verify($d['password'] ?? '', $u['password_hash']))
            fail('Invalid email or password', 422);
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $u['name'], 'role' => $u['role']];
        out(['user' => $_SESSION['user']]);

    case 'register':
        $d = body();
        $name  = clean($d['name'] ?? '');
        $email = strtolower(clean($d['email'] ?? ''));
        $pass  = (string)($d['password'] ?? '');
        $roll  = strtoupper(clean($d['roll_no'] ?? ''));
        $dept  = clean($d['department'] ?? '');
        $cgpa  = (float)($d['cgpa'] ?? 0);
        $back  = (int)($d['backlogs'] ?? 0);
        $year  = (int)($d['graduation_year'] ?? 0);
        $phone = clean($d['phone'] ?? '');

        if ($name === '' || $roll === '' || $dept === '') fail('Name, roll number and department are required');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Enter a valid email');
        if (strlen($pass) < 6) fail('Password must be at least 6 characters');
        if ($cgpa < 0 || $cgpa > 10) fail('CGPA must be between 0 and 10');
        if ($back < 0) fail('Backlogs cannot be negative');
        if ($year < 2000 || $year > 2100) fail('Enter a valid graduation year');

        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,"student")')
                ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            $uid = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO students (user_id, roll_no, department, cgpa, backlogs, graduation_year, phone)
                           VALUES (?,?,?,?,?,?,?)')
                ->execute([$uid, $roll, $dept, $cgpa, $back, $year, $phone]);
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') fail('Email or roll number already registered', 409);
            throw $e;
        }
        out(['message' => 'Registration successful. Please log in.'], 201);

    case 'logout':
        $_SESSION = [];
        session_destroy();
        out(['message' => 'Logged out']);

    case 'me':
        out(['user' => $_SESSION['user'] ?? null]);

    default:
        fail('Unknown action', 404);
}
