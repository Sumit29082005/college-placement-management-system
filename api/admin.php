<?php
require_once __DIR__ . '/../includes/helpers.php';
require_role('admin');

$action = $_GET['action'] ?? '';
$pdo = db();

function valid_date(string $d): bool {
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}

switch ($action) {
    case 'stats':
        $one = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();
        $students = $one('SELECT COUNT(*) FROM students');
        $placed   = $one('SELECT COUNT(DISTINCT student_id) FROM applications WHERE status = "selected"');
        $pk = $pdo->query(
            'SELECT MAX(d.package_lpa) AS highest, AVG(d.package_lpa) AS average
             FROM applications a JOIN drives d ON d.id = a.drive_id WHERE a.status = "selected"'
        )->fetch();
        $dept = $pdo->query(
            'SELECT s.department, COUNT(DISTINCT s.id) AS total,
                    COUNT(DISTINCT CASE WHEN a.status = "selected" THEN s.id END) AS placed
             FROM students s LEFT JOIN applications a ON a.student_id = s.id
             GROUP BY s.department ORDER BY s.department'
        )->fetchAll();
        out([
            'students'      => $students,
            'companies'     => $one('SELECT COUNT(*) FROM companies'),
            'open_drives'   => $one('SELECT COUNT(*) FROM drives WHERE status = "open"'),
            'applications'  => $one('SELECT COUNT(*) FROM applications'),
            'placed'        => $placed,
            'placement_pct' => $students ? round($placed * 100 / $students, 1) : 0,
            'highest_lpa'   => (float)($pk['highest'] ?? 0),
            'average_lpa'   => round((float)($pk['average'] ?? 0), 2),
            'departments'   => $dept,
        ]);

    case 'students':
        out(['students' => $pdo->query(
            'SELECT s.id, u.name, u.email, s.roll_no, s.department, s.cgpa, s.backlogs, s.graduation_year, s.phone,
                    EXISTS(SELECT 1 FROM applications a WHERE a.student_id = s.id AND a.status = "selected") AS placed
             FROM students s JOIN users u ON u.id = s.user_id ORDER BY s.roll_no'
        )->fetchAll()]);

    case 'delete_student':
        $id = (int)(body()['id'] ?? 0);
        $pdo->prepare('DELETE u FROM users u JOIN students s ON s.user_id = u.id WHERE s.id = ?')->execute([$id]);
        out(['message' => 'Student removed']);

    case 'companies':
        out(['companies' => $pdo->query('SELECT * FROM companies ORDER BY name')->fetchAll()]);

    case 'add_company':
        $d = body();
        $name = clean($d['name'] ?? '');
        if ($name === '') fail('Company name is required');
        $pdo->prepare('INSERT INTO companies (name, industry, website, location, description) VALUES (?,?,?,?,?)')
            ->execute([$name, clean($d['industry'] ?? ''), clean($d['website'] ?? ''), clean($d['location'] ?? ''), clean($d['description'] ?? '')]);
        out(['message' => 'Company added'], 201);

    case 'delete_company':
        $pdo->prepare('DELETE FROM companies WHERE id = ?')->execute([(int)(body()['id'] ?? 0)]);
        out(['message' => 'Company deleted']);

    case 'drives':
        out(['drives' => $pdo->query(
            'SELECT d.*, c.name AS company, COUNT(a.id) AS applicants
             FROM drives d JOIN companies c ON c.id = d.company_id
             LEFT JOIN applications a ON a.drive_id = d.id
             GROUP BY d.id, c.name ORDER BY d.deadline DESC'
        )->fetchAll()]);

    case 'add_drive':
        $d = body();
        $title = clean($d['job_title'] ?? '');
        $deadline = clean($d['deadline'] ?? '');
        $driveDate = clean($d['drive_date'] ?? '');
        if ($title === '' || !(int)($d['company_id'] ?? 0)) fail('Company and job title are required');
        if (!valid_date($deadline) || !valid_date($driveDate)) fail('Enter valid dates');
        $depts = clean($d['allowed_departments'] ?? '') ?: 'ALL';
        $pdo->prepare(
            'INSERT INTO drives (company_id, job_title, package_lpa, location, description, min_cgpa, max_backlogs,
                                 allowed_departments, deadline, drive_date)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            (int)$d['company_id'], $title, (float)($d['package_lpa'] ?? 0), clean($d['location'] ?? ''),
            clean($d['description'] ?? ''), (float)($d['min_cgpa'] ?? 0), (int)($d['max_backlogs'] ?? 0),
            $depts, $deadline, $driveDate,
        ]);
        out(['message' => 'Drive created'], 201);

    case 'toggle_drive':
        $pdo->prepare('UPDATE drives SET status = IF(status = "open", "closed", "open") WHERE id = ?')
            ->execute([(int)(body()['id'] ?? 0)]);
        out(['message' => 'Drive status changed']);

    case 'delete_drive':
        $pdo->prepare('DELETE FROM drives WHERE id = ?')->execute([(int)(body()['id'] ?? 0)]);
        out(['message' => 'Drive deleted']);

    case 'applications':
    case 'export_csv':
        $driveId = (int)($_GET['drive_id'] ?? 0);
        $sql = 'SELECT a.id, a.status, a.applied_at, u.name, s.roll_no, s.department, s.cgpa, s.backlogs,
                       u.email, s.phone, c.name AS company, d.job_title, d.package_lpa
                FROM applications a
                JOIN students s ON s.id = a.student_id
                JOIN users u ON u.id = s.user_id
                JOIN drives d ON d.id = a.drive_id
                JOIN companies c ON c.id = d.company_id';
        $args = [];
        if ($driveId) { $sql .= ' WHERE a.drive_id = ?'; $args[] = $driveId; }
        $stmt = $pdo->prepare($sql . ' ORDER BY a.applied_at DESC');
        $stmt->execute($args);
        $rows = $stmt->fetchAll();

        if ($action === 'applications') out(['applications' => $rows]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="applications.csv"');
        $f = fopen('php://output', 'w');
        fputcsv($f, ['ID','Status','Applied At','Name','Roll No','Department','CGPA','Backlogs','Email','Phone','Company','Job Title','Package LPA']);
        foreach ($rows as $r) {
            // Guard against CSV/formula injection when opened in Excel
            $r = array_map(fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v, $r);
            fputcsv($f, array_values($r));
        }
        exit;

    case 'update_status':
        $d = body();
        $allowed = ['applied', 'shortlisted', 'interviewed', 'selected', 'rejected'];
        $status = clean($d['status'] ?? '');
        if (!in_array($status, $allowed, true)) fail('Invalid status');
        $pdo->prepare('UPDATE applications SET status = ? WHERE id = ?')->execute([$status, (int)($d['id'] ?? 0)]);
        out(['message' => 'Status updated']);

    default:
        fail('Unknown action', 404);
}
