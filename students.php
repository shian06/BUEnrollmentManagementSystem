<?php
require_once 'includes/functions.php';
require_staff();

$search = trim($_GET['q'] ?? '');
$type   = $_GET['type'] ?? '';

$sql = "SELECT s.*, p.program_code
          FROM students s
          JOIN programs p ON p.program_id = s.program_id
         WHERE 1 = 1";
$params = [];
if ($search !== '') {
    $sql .= " AND (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
if (in_array($type, ['Regular', 'Irregular'], true)) {
    $sql .= " AND s.classification = ?";
    $params[] = $type;
}
$sql .= " ORDER BY s.last_name, s.first_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$page_title = 'Students';
$active = 'students';
include 'includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Students</h1>
        <p class="subtitle">All registered students, in alphabetical order.</p>
    </div>
    <a class="btn" href="student_form.php">+ Register Student</a>
</div>

<div class="card">
    <form class="toolbar" method="get">
        <div>
            <label for="q">Search</label>
            <input type="text" id="q" name="q" value="<?= h($search) ?>" placeholder="Student ID or name">
        </div>
        <div>
            <label for="type">Classification</label>
            <select id="type" name="type">
                <option value="">All</option>
                <option <?= $type === 'Regular' ? 'selected' : '' ?>>Regular</option>
                <option <?= $type === 'Irregular' ? 'selected' : '' ?>>Irregular</option>
            </select>
        </div>
        <div class="actions">
            <button class="btn btn-light" type="submit">Filter</button>
            <a href="students.php">Clear</a>
        </div>
    </form>

    <div class="table-wrap">
    <table>
        <tr>
            <th>Student ID</th><th>Name</th><th>Program</th><th>Year</th>
            <th>Classification</th><th>Email</th><th>Contact</th><th></th>
        </tr>
        <?php if (!$students): ?>
            <tr><td colspan="8" class="empty">No students found.</td></tr>
        <?php endif; ?>
        <?php foreach ($students as $s): ?>
        <tr>
            <td><?= h($s['student_id']) ?></td>
            <td><?= h($s['last_name'] . ', ' . $s['first_name'] . ' ' . $s['middle_name']) ?></td>
            <td><?= h($s['program_code']) ?></td>
            <td><?= h($s['year_level']) ?></td>
            <td><?= status_badge($s['classification']) ?></td>
            <td><?= h($s['email']) ?></td>
            <td><?= h($s['contact_number']) ?></td>
            <td class="actions">
                <a class="btn btn-light btn-sm" href="student_form.php?id=<?= urlencode($s['student_id']) ?>">Edit</a>
                <a class="btn btn-sm" href="enrollment_new.php?student_id=<?= urlencode($s['student_id']) ?>">Enroll</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
