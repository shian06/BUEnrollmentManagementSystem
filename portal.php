<?php
require_once 'includes/functions.php';

// Student portal: a student signs in with their Student ID and email.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_id = ? AND email = ?");
        $stmt->execute([trim($_POST['student_id'] ?? ''), trim($_POST['email'] ?? '')]);
        $sid = $stmt->fetchColumn();
        if ($sid) {
            $_SESSION['portal_student'] = $sid;
        } else {
            flash('error', 'Student ID and email do not match our records.');
        }
        redirect('portal.php');
    }

    if ($action === 'logout') {
        unset($_SESSION['portal_student']);
        redirect('portal.php');
    }

    if ($action === 'file_request' && !empty($_SESSION['portal_student'])) {
        $concern = trim($_POST['concern'] ?? '');
        $enrollment_id = (int)($_POST['enrollment_id'] ?? 0);
        // Make sure the enrollment belongs to the signed-in student.
        $check = $pdo->prepare("SELECT 1 FROM enrollments WHERE enrollment_id = ? AND student_id = ?");
        $check->execute([$enrollment_id, $_SESSION['portal_student']]);
        if ($concern === '' || !$check->fetchColumn()) {
            flash('error', 'Please describe the problem.');
        } else {
            $pdo->prepare("INSERT INTO verification_requests (enrollment_id, concern) VALUES (?, ?)")
                ->execute([$enrollment_id, mb_substr($concern, 0, 255)]);
            flash('success', 'Your request was sent to the registrar.');
        }
        redirect('portal.php');
    }
}

$portal = true;
$page_title = 'Student Portal';

$student_id = $_SESSION['portal_student'] ?? null;
if (!$student_id) {
    include 'includes/header.php';
    ?>
    <div class="card login-box">
        <h1>Student Portal</h1>
        <p class="subtitle">View your enrollment status and class schedule.</p>
        <form method="post">
            <input type="hidden" name="action" value="login">
            <label for="sid">Student ID</label>
            <input id="sid" name="student_id" placeholder="2023-00123" required>
            <label for="email" style="margin-top:12px;">University email</label>
            <input id="email" type="email" name="email" placeholder="you@bicol-u.edu.ph" required>
            <div class="form-actions"><button class="btn" type="submit">View My Enrollment</button></div>
        </form>
    </div>
    <?php
    include 'includes/footer.php';
    exit;
}

$stmt = $pdo->prepare(
    "SELECT s.*, p.program_code, p.program_name FROM students s
       JOIN programs p ON p.program_id = s.program_id WHERE s.student_id = ?"
);
$stmt->execute([$student_id]);
$student = $stmt->fetch();

$term = active_term($pdo);
$stmt = $pdo->prepare(
    "SELECT e.*, b.block_code FROM enrollments e
  LEFT JOIN blocks b ON b.block_id = e.block_id
      WHERE e.student_id = ? AND e.term_id = ?"
);
$stmt->execute([$student_id, $term['term_id'] ?? 0]);
$enr = $stmt->fetch();

$sections = $enr ? group_by_section(enrollment_schedule($pdo, $enr['enrollment_id'])) : [];
$total_units = array_sum(array_map(function ($s) { return (float)$s['units']; }, $sections));
$cor = $enr ? cor_status($pdo, $enr['enrollment_id']) : null;

$requests = [];
if ($enr) {
    $stmt = $pdo->prepare("SELECT * FROM verification_requests WHERE enrollment_id = ? ORDER BY date_filed DESC");
    $stmt->execute([$enr['enrollment_id']]);
    $requests = $stmt->fetchAll();
}

include 'includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Hi, <?= h($student['first_name']) ?>!</h1>
        <p class="subtitle"><?= h($student['student_id'] . ' · ' . $student['program_code'] . ' ' . $student['year_level'] . ' · ' . $student['classification']) ?></p>
    </div>
    <form method="post"><input type="hidden" name="action" value="logout"><button class="btn btn-light">Sign out</button></form>
</div>

<?php if (!$enr): ?>
    <div class="card"><p>You have no enrollment for <?= h($term['school_year'] . ' ' . $term['semester']) ?> yet. Please see the registrar.</p></div>
<?php else: ?>
<div class="stats">
    <div class="stat"><div class="stat-label">Enrollment status</div><div class="stat-value" style="font-size:20px;"><?= status_badge($enr['status']) ?></div></div>
    <div class="stat"><div class="stat-label">Block</div><div class="stat-value" style="font-size:20px;"><?= h($enr['block_code'] ?? 'Irregular') ?></div></div>
    <div class="stat"><div class="stat-label">Subjects / Units</div><div class="stat-value" style="font-size:20px;"><?= count($sections) ?> / <?= h($total_units) ?></div></div>
    <div class="stat"><div class="stat-label">Latest COR</div><div class="stat-value" style="font-size:20px;"><span class="badge badge-<?= h($cor['class']) ?>"><?= h($cor['label']) ?></span></div></div>
</div>

<?php if ($cor['class'] === 'outdated'): ?>
    <div class="alert alert-error">Your schedule changed after your COR was printed. Please ask the registrar for an updated COR.</div>
<?php endif; ?>

<div class="card">
    <h2>My class schedule, <?= h($term['school_year'] . ' ' . $term['semester']) ?></h2>
    <div class="table-wrap">
    <table>
        <tr><th>Subject</th><th>Title</th><th class="num">Units</th><th>Section</th><th>Schedule / Room</th></tr>
        <?php if (!$sections): ?><tr><td colspan="5" class="empty">No subjects yet.</td></tr><?php endif; ?>
        <?php foreach ($sections as $s): ?>
        <tr>
            <td class="nowrap"><?= h($s['subject_code']) ?></td>
            <td><?= h($s['subject_title']) ?></td>
            <td class="num"><?= h($s['units']) ?></td>
            <td class="nowrap"><?= h($s['section_code']) ?></td>
            <td class="nowrap"><?php foreach ($s['meetings'] as $m): ?><?= h(meeting_text($m)) ?><br><?php endforeach; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h2>Report a problem</h2>
        <p class="hint" style="margin-top:0;">Does your schedule here not match your printed COR? Tell the registrar.</p>
        <form method="post">
            <input type="hidden" name="action" value="file_request">
            <input type="hidden" name="enrollment_id" value="<?= h($enr['enrollment_id']) ?>">
            <textarea name="concern" rows="3" maxlength="255" required placeholder="Example: My COR says IT 116 is on Monday, but the portal shows Tuesday."></textarea>
            <div class="form-actions"><button class="btn" type="submit">Send to Registrar</button></div>
        </form>
    </div>
    <div class="card">
        <h2>My requests</h2>
        <table>
            <tr><th>Concern</th><th>Filed</th><th>Status</th></tr>
            <?php if (!$requests): ?><tr><td colspan="3" class="empty">No requests yet.</td></tr><?php endif; ?>
            <?php foreach ($requests as $r): ?>
                <tr><td><?= h($r['concern']) ?></td><td><?= fmt_date($r['date_filed']) ?></td><td><?= status_badge($r['request_status']) ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
