<?php
require_once 'includes/functions.php';
require_staff();

$term = active_term($pdo);
$term_id = $term['term_id'] ?? 0;
$report = $_GET['r'] ?? 'daily';

$reports = [
    'daily'     => 'Daily Enrollment Total',
    'capacity'  => 'Block / Section Capacity',
    'irregular' => 'Irregular Students\' Schedules',
    'pending'   => 'Pending Verification',
];
if (!isset($reports[$report])) {
    $report = 'daily';
}

if ($report === 'daily') {
    $stmt = $pdo->prepare(
        "SELECT e.enrollment_date,
                COUNT(*) AS total,
                SUM(s.classification = 'Regular')   AS regular,
                SUM(s.classification = 'Irregular') AS irregular,
                SUM(e.status = 'Enrolled') AS enrolled
           FROM enrollments e JOIN students s ON s.student_id = e.student_id
          WHERE e.term_id = ?
       GROUP BY e.enrollment_date ORDER BY e.enrollment_date"
    );
    $stmt->execute([$term_id]);
    $rows = $stmt->fetchAll();
}

if ($report === 'capacity') {
    $stmt = $pdo->prepare(
        "SELECT IFNULL(b.block_code, 'Open sections') AS block,
                o.section_code, sub.subject_code, o.max_capacity, o.current_enrolled,
                o.max_capacity - o.current_enrolled AS remaining
           FROM subject_offerings o
           JOIN subjects sub ON sub.subject_id = o.subject_id
      LEFT JOIN blocks b ON b.block_id = o.block_id
          WHERE o.term_id = ?
       ORDER BY b.block_code IS NULL, b.block_code, o.section_code"
    );
    $stmt->execute([$term_id]);
    $rows = $stmt->fetchAll();
}

if ($report === 'irregular') {
    $stmt = $pdo->prepare(
        "SELECT CONCAT(s.last_name, ', ', s.first_name) AS student_name, s.student_id,
                sub.subject_code, o.section_code, cs.day_of_week, cs.start_time, cs.end_time, r.room_code
           FROM enrollments e
           JOIN students s            ON s.student_id = e.student_id
           JOIN enrollment_details d  ON d.enrollment_id = e.enrollment_id
           JOIN subject_offerings o   ON o.offering_id = d.offering_id
           JOIN subjects sub          ON sub.subject_id = o.subject_id
           JOIN class_schedules cs    ON cs.offering_id = o.offering_id
           JOIN rooms r               ON r.room_id = cs.room_id
          WHERE s.classification = 'Irregular' AND e.term_id = ?
       ORDER BY student_name, FIELD(cs.day_of_week, 'Mon','Tue','Wed','Thu','Fri','Sat'), cs.start_time"
    );
    $stmt->execute([$term_id]);
    $rows = $stmt->fetchAll();
}

if ($report === 'pending') {
    $stmt = $pdo->prepare(
        "SELECT e.enrollment_id, CONCAT(s.last_name, ', ', s.first_name) AS student_name,
                s.classification, e.enrollment_date, e.status,
                (SELECT COUNT(*) FROM verification_requests v
                  WHERE v.enrollment_id = e.enrollment_id AND v.request_status = 'Pending') AS open_requests
           FROM enrollments e JOIN students s ON s.student_id = e.student_id
          WHERE e.term_id = ? AND e.status <> 'Enrolled'
       ORDER BY e.enrollment_date, student_name"
    );
    $stmt->execute([$term_id]);
    $rows = $stmt->fetchAll();
}

$page_title = 'Reports';
$active = 'reports';
include 'includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Reports</h1>
        <p class="subtitle"><?= h($term ? $term['school_year'] . ' ' . $term['semester'] : '') ?> &middot; Generated <?= date('M j, Y g:i A') ?></p>
    </div>
    <button class="btn btn-light no-print" onclick="window.print()">Print Report</button>
</div>

<div class="actions no-print" style="margin-bottom:16px;">
    <?php foreach ($reports as $key => $label): ?>
        <a class="btn btn-sm <?= $report === $key ? '' : 'btn-light' ?>" href="reports.php?r=<?= $key ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2><?= h($reports[$report]) ?></h2>
    <div class="table-wrap">
    <table>
    <?php if ($report === 'daily'): ?>
        <tr><th>Date</th><th class="num">Total</th><th class="num">Regular</th><th class="num">Irregular</th><th class="num">Enrolled</th></tr>
        <?php $sum = 0; foreach ($rows as $r): $sum += $r['total']; ?>
            <tr><td><?= fmt_date($r['enrollment_date']) ?></td><td class="num"><?= h($r['total']) ?></td>
                <td class="num"><?= h($r['regular']) ?></td><td class="num"><?= h($r['irregular']) ?></td><td class="num"><?= h($r['enrolled']) ?></td></tr>
        <?php endforeach; ?>
        <tr><td><b>Total</b></td><td class="num"><b><?= $sum ?></b></td><td colspan="3"></td></tr>

    <?php elseif ($report === 'capacity'): ?>
        <tr><th>Block</th><th>Section</th><th>Subject</th><th class="num">Capacity</th><th class="num">Enrolled</th><th class="num">Remaining</th><th>Status</th></tr>
        <?php foreach ($rows as $r): ?>
            <tr><td><?= h($r['block']) ?></td><td><?= h($r['section_code']) ?></td><td><?= h($r['subject_code']) ?></td>
                <td class="num"><?= h($r['max_capacity']) ?></td><td class="num"><?= h($r['current_enrolled']) ?></td>
                <td class="num"><?= h($r['remaining']) ?></td>
                <td><span class="badge badge-<?= $r['remaining'] <= 0 ? 'full' : 'open' ?>"><?= $r['remaining'] <= 0 ? 'FULL' : 'Open' ?></span></td></tr>
        <?php endforeach; ?>

    <?php elseif ($report === 'irregular'): ?>
        <tr><th>Student</th><th>Subject</th><th>Section</th><th>Day</th><th>Time</th><th>Room</th></tr>
        <?php if (!$rows): ?><tr><td colspan="6" class="empty">No irregular students with subjects yet.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr><td><?= h($r['student_name']) ?><br><small><?= h($r['student_id']) ?></small></td><td><?= h($r['subject_code']) ?></td>
                <td><?= h($r['section_code']) ?></td><td><?= h($r['day_of_week']) ?></td>
                <td><?= fmt_time($r['start_time']) ?> - <?= fmt_time($r['end_time']) ?></td><td><?= h($r['room_code']) ?></td></tr>
        <?php endforeach; ?>

    <?php else: ?>
        <tr><th>ID</th><th>Student</th><th>Type</th><th>Date</th><th>Status</th><th class="num">Open requests</th><th class="no-print"></th></tr>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">Every enrollment is complete.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr><td><?= h($r['enrollment_id']) ?></td><td><?= h($r['student_name']) ?></td><td><?= status_badge($r['classification']) ?></td>
                <td><?= fmt_date($r['enrollment_date']) ?></td><td><?= status_badge($r['status']) ?></td>
                <td class="num"><?= h($r['open_requests']) ?></td>
                <td class="no-print"><a href="enrollment_view.php?id=<?= h($r['enrollment_id']) ?>">Open</a></td></tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
