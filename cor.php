<?php
require_once 'includes/functions.php';
require_staff();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT e.*, s.first_name, s.middle_name, s.last_name, s.classification, s.year_level,
            p.program_code, p.program_name, p.college, b.block_code, t.school_year, t.semester
       FROM enrollments e
       JOIN students s       ON s.student_id = e.student_id
       JOIN programs p       ON p.program_id = s.program_id
       JOIN academic_terms t ON t.term_id = e.term_id
  LEFT JOIN blocks b         ON b.block_id = e.block_id
      WHERE e.enrollment_id = ?"
);
$stmt->execute([$id]);
$enr = $stmt->fetch();
if (!$enr) {
    flash('error', 'Enrollment not found.');
    redirect('enrollments.php');
}

// Recording a COR: save it in cor_records with the next control number.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cor_records WHERE cor_number LIKE ?");
    $stmt->execute(["COR-$year-%"]);
    $next = (int)$stmt->fetchColumn() + 1;
    do {
        $cor_number = sprintf('COR-%s-%04d', $year, $next++);
        $exists = $pdo->prepare("SELECT 1 FROM cor_records WHERE cor_number = ?");
        $exists->execute([$cor_number]);
    } while ($exists->fetchColumn());

    $pdo->prepare("INSERT INTO cor_records (enrollment_id, cor_number, printed_at, printed_by) VALUES (?, ?, NOW(), ?)")
        ->execute([$id, $cor_number, current_staff_id()]);
    flash('success', "COR $cor_number recorded. Use your browser's print window to print it.");
    redirect("cor.php?id=$id&cor=" . $pdo->lastInsertId() . '&print=1');
}

$cor = null;
if (!empty($_GET['cor'])) {
    $stmt = $pdo->prepare(
        "SELECT c.*, rs.full_name FROM cor_records c
           JOIN registrar_staff rs ON rs.staff_id = c.printed_by
          WHERE c.cor_id = ? AND c.enrollment_id = ?"
    );
    $stmt->execute([(int)$_GET['cor'], $id]);
    $cor = $stmt->fetch();
}

$sections = group_by_section(enrollment_schedule($pdo, $id));
$total_units = array_sum(array_map(function ($s) { return (float)$s['units']; }, $sections));

$page_title = 'Certificate of Registration';
$active = 'enrollments';
include 'includes/header.php';
?>
<div class="page-head no-print">
    <div>
        <h1>Certificate of Registration</h1>
        <p class="subtitle">
            <?= $cor ? 'Recorded as ' . h($cor['cor_number']) . '.' : 'Preview. Recording the COR saves its control number, date, and the staff who printed it.' ?>
        </p>
    </div>
    <div class="actions">
        <a class="btn btn-light" href="enrollment_view.php?id=<?= h($id) ?>">Back to Enrollment</a>
        <?php if ($cor): ?>
            <button class="btn" onclick="window.print()">Print Again</button>
        <?php else: ?>
            <form method="post" class="inline">
                <button class="btn" <?= $sections ? '' : 'disabled' ?>>Record and Print COR</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($enr['status'] !== 'Enrolled'): ?>
    <div class="alert alert-info no-print">Note: this enrollment is still <b><?= h($enr['status']) ?></b>.</div>
<?php endif; ?>

<div class="cor-sheet">
    <div class="cor-head">
        <h1>BICOL UNIVERSITY</h1>
        <p><?= h($enr['college']) ?></p>
        <p>Office of the University Registrar</p>
    </div>
    <div class="cor-title">CERTIFICATE OF REGISTRATION</div>

    <div class="cor-meta">
        <div>Name: <b><?= h(strtoupper($enr['last_name']) . ', ' . $enr['first_name'] . ' ' . $enr['middle_name']) ?></b></div>
        <div>COR No.: <b><?= h($cor['cor_number'] ?? '(not yet recorded)') ?></b></div>
        <div>Student ID: <b><?= h($enr['student_id']) ?></b></div>
        <div>Term: <b><?= h($enr['school_year'] . ', ' . $enr['semester']) ?></b></div>
        <div>Program: <b><?= h($enr['program_code']) ?></b> &middot; Year <?= h($enr['year_level']) ?></div>
        <div>Block: <b><?= h($enr['block_code'] ?? 'Irregular') ?></b></div>
    </div>

    <table>
        <tr><th>Code</th><th>Descriptive Title</th><th class="num">Units</th><th>Section</th><th>Schedule / Room</th></tr>
        <?php foreach ($sections as $s): ?>
        <tr>
            <td class="nowrap"><?= h($s['subject_code']) ?></td>
            <td><?= h($s['subject_title']) ?></td>
            <td class="num"><?= h($s['units']) ?></td>
            <td class="nowrap"><?= h($s['section_code']) ?></td>
            <td class="nowrap"><?php foreach ($s['meetings'] as $m): ?><?= h(meeting_text($m)) ?><br><?php endforeach; ?></td>
        </tr>
        <?php endforeach; ?>
        <tr><td colspan="2" class="num"><b>Total units</b></td><td class="num"><b><?= h($total_units) ?></b></td><td colspan="2"></td></tr>
    </table>

    <p style="margin-top:14px;">
        Enrollment status: <b><?= h($enr['status']) ?></b>
        <?php if ($cor): ?> &middot; Printed <?= fmt_datetime($cor['printed_at']) ?><?php endif; ?>
    </p>

    <div class="cor-sign">
        <div><?= h(($enr['first_name'] . ' ' . $enr['last_name'])) ?><br><small>Student's Signature</small></div>
        <div><?= h($cor['full_name'] ?? '') ?><br><small>Registrar Staff</small></div>
    </div>
</div>

<?php if (!empty($_GET['print']) && $cor): ?>
<script>window.addEventListener('load', () => window.print());</script>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
