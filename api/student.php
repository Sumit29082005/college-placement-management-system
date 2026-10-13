<?php
require_once __DIR__ . '/../includes/helpers.php';
require_role('student');

$action = $_GET['action'] ?? '';
$pdo = db();

$stmt = $pdo->prepare('SELECT s.*, u.name, u.email FROM students s JOIN users u ON u.id = s.user_id WHERE s.user_id = ?');
$stmt->execute([$_SESSION['user']['id']]);
$me = $stmt->fetch();
if (!$me) fail('Student profile not found', 404);

switch ($action) {
    case 'profile':
        out(['profile' => $me]);

    case 'update_profile':
        $d = body();
        $pdo->prepare('UPDATE students SET phone = ?, skills = ?, resume_link = ? WHERE id = ?')
            ->execute([clean($d['phone'] ?? ''), clean($d['skills'] ?? ''), clean($d['resume_link'] ?? ''), $me['id']]);
        out(['message' => 'Profile updated']);

    case 'drives':
        $rows = $pdo->query(
            'SELECT d.*, c.name AS company, c.industry, c.location AS company_location
             FROM drives d JOIN companies c ON c.id = d.company_id
             ORDER BY d.status = "open" DESC, d.deadline ASC'
        )->fetchAll();
        $applied = $pdo->prepare('SELECT drive_id FROM applications WHERE student_id = ?');
        $applied->execute([$me['id']]);
        $appliedIds = array_map('intval', array_column($applied->fetchAll(), 'drive_id'));
        foreach ($rows as &$r) {
            [$ok, $why] = eligibility($r, $me);
            $r['eligible'] = $ok;
            $r['reason']   = $why;
            $r['applied']  = in_array((int)$r['id'], $appliedIds, true);
            $r['expired']  = $r['deadline'] < date('Y-m-d');
        }
        unset($r);
        out(['drives' => $rows]);

    case 'apply':
        $driveId = (int)(body()['drive_id'] ?? 0);
        $q = $pdo->prepare('SELECT * FROM drives WHERE id = ?');
        $q->execute([$driveId]);
        $drive = $q->fetch();
        if (!$drive) fail('Drive not found', 404);
        if ($drive['status'] !== 'open' || $drive['deadline'] < date('Y-m-d')) fail('Applications are closed for this drive');
        [$ok, $why] = eligibility($drive, $me);
        if (!$ok) fail($why, 403);
        try {
            $pdo->prepare('INSERT INTO applications (drive_id, student_id) VALUES (?,?)')->execute([$driveId, $me['id']]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') fail('You have already applied', 409);
            throw $e;
        }
        out(['message' => 'Application submitted'], 201);

    case 'applications':
        $q = $pdo->prepare(
            'SELECT a.id, a.status, a.applied_at, d.job_title, d.package_lpa, d.drive_date, c.name AS company
             FROM applications a
             JOIN drives d ON d.id = a.drive_id
             JOIN companies c ON c.id = d.company_id
             WHERE a.student_id = ? ORDER BY a.applied_at DESC'
        );
        $q->execute([$me['id']]);
        out(['applications' => $q->fetchAll()]);

    default:
        fail('Unknown action', 404);
}
