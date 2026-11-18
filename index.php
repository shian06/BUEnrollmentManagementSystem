<?php
require_once 'includes/functions.php';
require_staff();

$term = active_term($pdo);
$term_id = $term['term_id'] ?? 0;

$counts = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'Pending'), 0)  AS pending,
            COALESCE(SUM(status = 'Verified'), 0) AS verified,
            COALESCE(SUM(status = 'Enrolled'), 0) AS enrolled
       FROM enrollments WHERE term_id = ?"
);
$counts->execute([$term_id]);
$c = $counts->fetch();

$open_requests = $pdo->query("SELECT COUNT(*) FROM verification_requests WHERE request_status = 'Pending'")->fetchColumn();

$full_sections = $pdo->prepare("SELECT COUNT(*) FROM subject_offerings WHERE term_id = ? AND current_enrolled >= max_capacity");
$full_sections->execute([$term_id]);
$full = $full_sections->fetchColumn();

$recent = $pdo->prepare(
    "SELECT e.enrollment_id, e.enrollment_date, e.status, s.classification,
            CONCAT(s.last_name, ', ', s.first_name) AS student_name
       FROM enrollments e JOIN students s ON s.student_id = e.student_id
      WHERE e.term_id = ?
   ORDER BY e.last_modified DESC LIMIT 6"
);
$recent->execute([$term_id]);

$page_title = 'Dashboard';
$active = 'dashboard';
include 'includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p class="subtitle">
            Active term: <b><?= h($term ? $term['school_year'] . ' ' . $term['semester'] : 'None') ?></b>
            <?php if ($term): ?> &middot; Enrollment period <?= fmt_date($term['enrollment_start']) ?> to <?= fmt_date($term['enrollment_end']) ?><?php endif; ?>
        </p>
    </div>
    <a class="btn" href="enrollment_new.php">+ New Enrollment</a>
</div>

<div class="stats">
    <div class="stat"><div class="stat-label">Total enrollments</div><div class="stat-value"><?= (int)$c['total'] ?></div></div>
    <div class="stat"><div class="stat-label">Pending</div><div class="stat-value"><?= (int)$c['pending'] ?></div></div>
    <div class="stat"><div class="stat-label">Verified</div><div class="stat-value"><?= (int)$c['verified'] ?></div></div>
    <div class="stat"><div class="stat-label">Enrolled</div><div class="stat-value"><?= (int)$c['enrolled'] ?></div></div>
    <div class="stat"><div class="stat-label">Open requests</div><div class="stat-value"><?= (int)$open_requests ?></div></div>
    <div class="stat"><div class="stat-label">Full sections</div><div class="stat-value"><?= (int)$full ?></div></div>
</div>

<div class="card">
    <h2>Recently updated enrollments</h2>
    <div class="table-wrap">
    <table>
        <tr><th>ID</th><th>Student</th><th>Type</th><th>Date</th><th>Status</th><th></th></tr>
        <?php foreach ($recent as $r): ?>
        <tr>
            <td><?= h($r['enrollment_id']) ?></td>
            <td><?= h($r['student_name']) ?></td>
            <td><?= status_badge($r['classification']) ?></td>
            <td><?= fmt_date($r['enrollment_date']) ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td><a href="enrollment_view.php?id=<?= h($r['enrollment_id']) ?>">Open</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
