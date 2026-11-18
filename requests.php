<?php
require_once 'includes/functions.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resolve') {
    $pdo->prepare(
        "UPDATE verification_requests
            SET request_status = 'Resolved', resolved_by = ?, date_resolved = NOW()
          WHERE request_id = ? AND request_status = 'Pending'"
    )->execute([current_staff_id(), (int)$_POST['request_id']]);
    flash('success', 'Request marked as resolved.');
    redirect('requests.php' . (isset($_GET['show']) ? '?show=' . urlencode($_GET['show']) : ''));
}

$show = $_GET['show'] ?? 'Pending';
$sql = "SELECT v.*, e.enrollment_id, s.student_id,
               CONCAT(s.last_name, ', ', s.first_name) AS student_name,
               rs.full_name AS resolver
          FROM verification_requests v
          JOIN enrollments e ON e.enrollment_id = v.enrollment_id
          JOIN students s    ON s.student_id = e.student_id
     LEFT JOIN registrar_staff rs ON rs.staff_id = v.resolved_by";
$params = [];
if (in_array($show, ['Pending', 'Resolved'], true)) {
    $sql .= " WHERE v.request_status = ?";
    $params[] = $show;
}
$sql .= " ORDER BY v.request_status = 'Pending' DESC, v.date_filed";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$page_title = 'Verification Requests';
$active = 'requests';
include 'includes/header.php';
?>
<h1>Verification Requests</h1>
<p class="subtitle">Discrepancies reported by students, for example a portal schedule that doesn't match the printed COR.</p>

<div class="card">
    <div class="actions" style="margin-bottom:14px;">
        <?php foreach (['Pending', 'Resolved', 'All'] as $opt): ?>
            <a class="btn btn-sm <?= $show === $opt ? '' : 'btn-light' ?>" href="requests.php?show=<?= $opt ?>"><?= $opt ?></a>
        <?php endforeach; ?>
    </div>
    <div class="table-wrap">
    <table>
        <tr><th>#</th><th>Student</th><th>Concern</th><th>Filed</th><th>Status</th><th>Resolved by</th><th></th></tr>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">No requests here.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= h($r['request_id']) ?></td>
            <td><?= h($r['student_name']) ?><br><small><?= h($r['student_id']) ?></small></td>
            <td><?= h($r['concern']) ?></td>
            <td><?= fmt_datetime($r['date_filed']) ?></td>
            <td><?= status_badge($r['request_status']) ?></td>
            <td><?= h($r['resolver'] ?? '-') ?><?php if ($r['date_resolved']): ?><br><small><?= fmt_datetime($r['date_resolved']) ?></small><?php endif; ?></td>
            <td class="actions">
                <a class="btn btn-light btn-sm" href="enrollment_view.php?id=<?= h($r['enrollment_id']) ?>">Check Enrollment</a>
                <?php if ($r['request_status'] === 'Pending'): ?>
                <form method="post" class="inline">
                    <input type="hidden" name="action" value="resolve">
                    <input type="hidden" name="request_id" value="<?= h($r['request_id']) ?>">
                    <button class="btn btn-sm">Mark Resolved</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
