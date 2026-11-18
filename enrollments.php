<?php
require_once 'includes/functions.php';
require_staff();

$term   = active_term($pdo);
$status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT e.*, s.classification,
               CONCAT(s.last_name, ', ', s.first_name) AS student_name,
               b.block_code,
               (SELECT COUNT(*) FROM enrollment_details d WHERE d.enrollment_id = e.enrollment_id) AS subject_count,
               (SELECT COALESCE(SUM(sub.units), 0)
                  FROM enrollment_details d
                  JOIN subject_offerings o ON o.offering_id = d.offering_id
                  JOIN subjects sub ON sub.subject_id = o.subject_id
                 WHERE d.enrollment_id = e.enrollment_id) AS total_units
          FROM enrollments e
          JOIN students s ON s.student_id = e.student_id
     LEFT JOIN blocks b ON b.block_id = e.block_id
         WHERE e.term_id = ?";
$params = [$term['term_id'] ?? 0];
if (in_array($status, ['Pending', 'Verified', 'Enrolled'], true)) {
    $sql .= " AND e.status = ?";
    $params[] = $status;
}
if ($search !== '') {
    $sql .= " AND (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
$sql .= " ORDER BY FIELD(e.status, 'Pending', 'Verified', 'Enrolled'), e.enrollment_date, student_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$page_title = 'Enrollments';
$active = 'enrollments';
include 'includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Enrollments</h1>
        <p class="subtitle"><?= h($term ? $term['school_year'] . ' ' . $term['semester'] : '') ?></p>
    </div>
    <a class="btn" href="enrollment_new.php">+ New Enrollment</a>
</div>

<div class="card">
    <form class="toolbar" method="get">
        <div>
            <label for="q">Search</label>
            <input id="q" name="q" value="<?= h($search) ?>" placeholder="Student ID or name">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All</option>
                <?php foreach (['Pending', 'Verified', 'Enrolled'] as $st): ?>
                    <option <?= $status === $st ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="actions">
            <button class="btn btn-light" type="submit">Filter</button>
            <a href="enrollments.php">Clear</a>
        </div>
    </form>

    <div class="table-wrap">
    <table>
        <tr>
            <th>ID</th><th>Student</th><th>Type</th><th>Block</th><th class="num">Subjects</th>
            <th class="num">Units</th><th>Date</th><th>Status</th><th>COR</th><th></th>
        </tr>
        <?php if (!$rows): ?>
            <tr><td colspan="10" class="empty">No enrollments found.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): $cor = cor_status($pdo, $r['enrollment_id']); ?>
        <tr>
            <td><?= h($r['enrollment_id']) ?></td>
            <td><?= h($r['student_name']) ?><br><small><?= h($r['student_id']) ?></small></td>
            <td><?= status_badge($r['classification']) ?></td>
            <td><?= h($r['block_code'] ?? '-') ?></td>
            <td class="num"><?= h($r['subject_count']) ?></td>
            <td class="num"><?= h($r['total_units']) ?></td>
            <td><?= fmt_date($r['enrollment_date']) ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td><span class="badge badge-<?= h($cor['class']) ?>"><?= h($cor['label']) ?></span></td>
            <td><a class="btn btn-light btn-sm" href="enrollment_view.php?id=<?= h($r['enrollment_id']) ?>">Open</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
