<?php
require_once 'includes/functions.php';
require_staff();

$id = (int)($_GET['id'] ?? 0);

function load_enrollment(PDO $pdo, $id) {
    $stmt = $pdo->prepare(
        "SELECT e.*, s.first_name, s.middle_name, s.last_name, s.classification, s.year_level, s.email,
                p.program_code, p.program_name, b.block_code, t.school_year, t.semester,
                rs.full_name AS verifier
           FROM enrollments e
           JOIN students s        ON s.student_id = e.student_id
           JOIN programs p        ON p.program_id = s.program_id
           JOIN academic_terms t  ON t.term_id = e.term_id
      LEFT JOIN blocks b          ON b.block_id = e.block_id
      LEFT JOIN registrar_staff rs ON rs.staff_id = e.verified_by
          WHERE e.enrollment_id = ?"
    );
    $stmt->execute([$id]);
    return $stmt->fetch();
}

$enr = load_enrollment($pdo, $id);
if (!$enr) {
    flash('error', 'Enrollment not found.');
    redirect('enrollments.php');
}

// ----- Actions -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_subject') {
        $err = add_subject($pdo, $id, (int)$_POST['offering_id']);
        $err ? flash('error', $err) : flash('success', 'Section added to the enrollment.');
    }

    if ($action === 'drop_subject') {
        $pdo->prepare("DELETE FROM enrollment_details WHERE enrollment_detail_id = ? AND enrollment_id = ?")
            ->execute([(int)$_POST['detail_id'], $id]);
        flash('success', 'Section dropped. The section count was updated.');
    }

    if ($action === 'verify' && $enr['status'] === 'Pending') {
        $pdo->prepare("UPDATE enrollments SET status = 'Verified', verified_by = ? WHERE enrollment_id = ?")
            ->execute([current_staff_id(), $id]);
        flash('success', 'Enrollment verified.');
    }

    if ($action === 'enroll' && $enr['status'] === 'Verified') {
        $pdo->prepare("UPDATE enrollments SET status = 'Enrolled' WHERE enrollment_id = ?")->execute([$id]);
        flash('success', 'Student is now officially enrolled.');
    }

    if ($action === 'back_to_pending') {
        $pdo->prepare("UPDATE enrollments SET status = 'Pending', verified_by = NULL WHERE enrollment_id = ?")->execute([$id]);
        flash('info', 'Enrollment moved back to Pending.');
    }

    redirect('enrollment_view.php?id=' . $id);
}

// ----- Data for the page -----
$sections = group_by_section(enrollment_schedule($pdo, $id));
$total_units = array_sum(array_map(function ($s) { return (float)$s['units']; }, $sections));
$cor = cor_status($pdo, $id);

// Sections the student can still add.
$sql = "SELECT o.offering_id, o.section_code, o.max_capacity, o.current_enrolled,
               sub.subject_code, sub.subject_title, b.block_code
          FROM subject_offerings o
          JOIN subjects sub ON sub.subject_id = o.subject_id
     LEFT JOIN blocks b ON b.block_id = o.block_id
         WHERE o.term_id = ?
           AND o.offering_id NOT IN (SELECT offering_id FROM enrollment_details WHERE enrollment_id = ?)";
$params = [$enr['term_id'], $id];
if ($enr['classification'] === 'Regular') {
    $sql .= " AND o.block_id = ?";
    $params[] = $enr['block_id'];
}
$sql .= " ORDER BY sub.subject_code, o.section_code";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$available = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT c.*, rs.full_name FROM cor_records c
       JOIN registrar_staff rs ON rs.staff_id = c.printed_by
      WHERE c.enrollment_id = ? ORDER BY c.printed_at DESC"
);
$stmt->execute([$id]);
$cors = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM verification_requests WHERE enrollment_id = ? ORDER BY date_filed DESC");
$stmt->execute([$id]);
$requests = $stmt->fetchAll();

$page_title = 'Enrollment #' . $id;
$active = 'enrollments';
include 'includes/header.php';
?>
<div class="page-head">
    <div>
        <h1><?= h($enr['last_name'] . ', ' . $enr['first_name'] . ' ' . $enr['middle_name']) ?></h1>
        <p class="subtitle">Enrollment #<?= h($id) ?> &middot; <?= h($enr['school_year'] . ' ' . $enr['semester']) ?></p>
    </div>
    <div class="actions">
        <?= status_badge($enr['status']) ?>
        <?php if ($enr['status'] === 'Pending'): ?>
            <form method="post" class="inline"><input type="hidden" name="action" value="verify">
                <button class="btn" <?= $sections ? '' : 'disabled title="Add at least one subject first"' ?>>Verify Enrollment</button></form>
        <?php elseif ($enr['status'] === 'Verified'): ?>
            <form method="post" class="inline"><input type="hidden" name="action" value="enroll">
                <button class="btn">Mark as Enrolled</button></form>
            <form method="post" class="inline"><input type="hidden" name="action" value="back_to_pending">
                <button class="btn btn-light">Back to Pending</button></form>
        <?php endif; ?>
        <a class="btn btn-light" href="cor.php?id=<?= h($id) ?>">Print COR</a>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h2>Student and enrollment</h2>
        <dl class="kv">
            <dt>Student ID</dt><dd><?= h($enr['student_id']) ?></dd>
            <dt>Program</dt><dd><?= h($enr['program_code'] . ' - ' . $enr['program_name']) ?></dd>
            <dt>Year level</dt><dd><?= h($enr['year_level']) ?></dd>
            <dt>Classification</dt><dd><?= status_badge($enr['classification']) ?></dd>
            <dt>Block</dt><dd><?= h($enr['block_code'] ?? 'None (irregular)') ?></dd>
            <dt>Enrollment date</dt><dd><?= fmt_date($enr['enrollment_date']) ?></dd>
            <dt>Verified by</dt><dd><?= h($enr['verifier'] ?? '-') ?></dd>
            <dt>Last changed</dt><dd><?= fmt_datetime($enr['last_modified']) ?></dd>
        </dl>
    </div>
    <div class="card">
        <h2>Certificate of Registration</h2>
        <p>Latest COR: <span class="badge badge-<?= h($cor['class']) ?>"><?= h($cor['label']) ?></span></p>
        <?php if ($cor['class'] === 'outdated'): ?>
            <div class="alert alert-error">The enrollment changed after the last COR was printed. Print a new COR so it matches the portal.</div>
        <?php endif; ?>
        <table>
            <tr><th>COR No.</th><th>Printed</th><th>By</th></tr>
            <?php if (!$cors): ?><tr><td colspan="3" class="empty">No COR printed yet.</td></tr><?php endif; ?>
            <?php foreach ($cors as $c): ?>
                <tr><td><?= h($c['cor_number']) ?></td><td><?= fmt_datetime($c['printed_at']) ?></td><td><?= h($c['full_name']) ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<div class="card">
    <h2>Subjects and schedule (<?= count($sections) ?> <?= count($sections) === 1 ? 'subject' : 'subjects' ?>, <?= h($total_units) ?> units)</h2>
    <div class="table-wrap">
    <table>
        <tr><th>Subject</th><th>Title</th><th class="num">Units</th><th>Section</th><th>Schedule</th><th></th></tr>
        <?php if (!$sections): ?><tr><td colspan="6" class="empty">No subjects yet. Add a section below.</td></tr><?php endif; ?>
        <?php foreach ($sections as $s): ?>
        <tr>
            <td class="nowrap"><?= h($s['subject_code']) ?></td>
            <td><?= h($s['subject_title']) ?></td>
            <td class="num"><?= h($s['units']) ?></td>
            <td class="nowrap"><?= h($s['section_code']) ?></td>
            <td class="nowrap"><?php foreach ($s['meetings'] as $m): ?><?= h(meeting_text($m)) ?><br><?php endforeach; ?></td>
            <td>
                <?php if ($enr['status'] !== 'Enrolled'): ?>
                <form method="post" class="inline" onsubmit="return confirm('Drop this section?');">
                    <input type="hidden" name="action" value="drop_subject">
                    <input type="hidden" name="detail_id" value="<?= h($s['enrollment_detail_id']) ?>">
                    <button class="btn btn-danger btn-sm">Drop</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>

    <?php if ($enr['status'] !== 'Enrolled'): ?>
    <form method="post" class="toolbar" style="margin-top:16px;">
        <input type="hidden" name="action" value="add_subject">
        <div style="flex:1; min-width:280px;">
            <label for="offering_id">Add a section</label>
            <select id="offering_id" name="offering_id" required>
                <option value="">Select a section</option>
                <?php foreach ($available as $a): $left = $a['max_capacity'] - $a['current_enrolled']; ?>
                    <option value="<?= h($a['offering_id']) ?>">
                        <?= h($a['subject_code'] . ' - ' . $a['subject_title'] . ' | ' . $a['section_code']
                            . ' | ' . ($left > 0 ? "$left slot(s) left" : 'FULL')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="hint">
                <?= $enr['classification'] === 'Regular'
                    ? 'Regular student: only sections of the assigned block are listed.'
                    : 'Irregular student: any section offered this term can be added.' ?>
            </div>
        </div>
        <div><button class="btn" type="submit">Add Section</button></div>
    </form>
    <?php else: ?>
        <p class="hint">This student is officially enrolled. Move the enrollment back to Pending to change subjects.</p>
        <form method="post" class="inline"><input type="hidden" name="action" value="back_to_pending">
            <button class="btn btn-light btn-sm">Back to Pending</button></form>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Verification requests</h2>
    <table>
        <tr><th>#</th><th>Concern</th><th>Filed</th><th>Status</th></tr>
        <?php if (!$requests): ?><tr><td colspan="4" class="empty">No requests from this student.</td></tr><?php endif; ?>
        <?php foreach ($requests as $r): ?>
            <tr><td><?= h($r['request_id']) ?></td><td><?= h($r['concern']) ?></td>
                <td><?= fmt_datetime($r['date_filed']) ?></td><td><?= status_badge($r['request_status']) ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>
<?php include 'includes/footer.php'; ?>
