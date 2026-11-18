<?php
require_once 'includes/functions.php';
require_staff();

$term = active_term($pdo);
if (!$term) {
    flash('error', 'There is no active academic term. Set one term as active first.');
    redirect('enrollments.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? '';
    $block_id   = ($_POST['block_id'] ?? '') !== '' ? (int)$_POST['block_id'] : null;

    $stmt = $pdo->prepare("SELECT classification, program_id FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $stu = $stmt->fetch();
    $classification = $stu['classification'] ?? null;

    if (!$classification) {
        flash('error', 'Please select a student.');
        redirect('enrollment_new.php');
    }
    if ($classification === 'Irregular') {
        $block_id = null; // irregular students pick open sections instead
    } else {
        // The block must belong to the student's program and this term.
        $chk = $pdo->prepare("SELECT 1 FROM blocks WHERE block_id = ? AND program_id = ? AND term_id = ?");
        $chk->execute([$block_id, $stu['program_id'], $term['term_id']]);
        if (!$chk->fetchColumn()) {
            flash('error', "Select a block from the student's own program for this term.");
            redirect('enrollment_new.php?student_id=' . urlencode($student_id));
        }
    }

    try {
        // Step 2 of the workflow: create the enrollment record (status = Pending).
        $stmt = $pdo->prepare(
            "INSERT INTO enrollments (student_id, term_id, block_id, enrollment_date, status)
             VALUES (?, ?, ?, CURDATE(), 'Pending')"
        );
        $stmt->execute([$student_id, $term['term_id'], $block_id]);
        $enrollment_id = $pdo->lastInsertId();

        // Step 3: a Regular student automatically gets all sections of the block.
        if ($classification === 'Regular') {
            $sections = $pdo->prepare("SELECT offering_id, section_code FROM subject_offerings WHERE block_id = ? AND term_id = ?");
            $sections->execute([$block_id, $term['term_id']]);
            $added = 0;
            foreach ($sections->fetchAll() as $sec) {
                $err = add_subject($pdo, $enrollment_id, $sec['offering_id']);
                if ($err) {
                    flash('error', $sec['section_code'] . ': ' . $err);
                } else {
                    $added++;
                }
            }
            flash('success', "Enrollment created. $added block section(s) were added automatically.");
        } else {
            flash('success', 'Enrollment created. Add the open sections the student selected.');
        }
        redirect('enrollment_view.php?id=' . $enrollment_id);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1062) {
            flash('error', 'This student already has an enrollment for this term.');
        } else {
            flash('error', db_error_message($e));
        }
        redirect('enrollment_new.php?student_id=' . urlencode($student_id));
    }
}

// Students who do not have an enrollment in the active term yet.
$stmt = $pdo->prepare(
    "SELECT s.student_id, s.first_name, s.last_name, s.classification, s.program_id, s.year_level
       FROM students s
      WHERE s.student_id NOT IN (SELECT student_id FROM enrollments WHERE term_id = ?)
   ORDER BY s.last_name, s.first_name"
);
$stmt->execute([$term['term_id']]);
$students = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT b.block_id, b.block_code, b.program_id, b.year_level,
            (SELECT COUNT(*) FROM subject_offerings o WHERE o.block_id = b.block_id) AS section_count
       FROM blocks b WHERE b.term_id = ? ORDER BY b.block_code"
);
$stmt->execute([$term['term_id']]);
$blocks = $stmt->fetchAll();

$selected = $_GET['student_id'] ?? '';

$page_title = 'New Enrollment';
$active = 'enrollments';
include 'includes/header.php';
?>
<h1>New Enrollment</h1>
<p class="subtitle">Term: <b><?= h($term['school_year'] . ' ' . $term['semester']) ?></b>. The enrollment starts as <b>Pending</b>.</p>

<div class="card">
<form method="post">
    <div class="form-grid">
        <div>
            <label for="student_id">Student</label>
            <select id="student_id" name="student_id" required>
                <option value="">Select a student</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?= h($s['student_id']) ?>"
                            data-class="<?= h($s['classification']) ?>"
                            data-program="<?= h($s['program_id']) ?>"
                            <?= $selected === $s['student_id'] ? 'selected' : '' ?>>
                        <?= h($s['last_name'] . ', ' . $s['first_name'] . ' (' . $s['student_id'] . ') - ' . $s['classification']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="hint">Only students without an enrollment this term are listed. <a href="student_form.php">Register a new student</a></div>
        </div>
        <div id="block-field">
            <label for="block_id">Block</label>
            <select id="block_id" name="block_id">
                <option value="">Select a block</option>
                <?php foreach ($blocks as $b): ?>
                    <option value="<?= h($b['block_id']) ?>" data-program="<?= h($b['program_id']) ?>">
                        <?= h($b['block_code'] . ' (' . $b['section_count'] . ' sections)') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="hint">All sections of the block are added automatically.</div>
        </div>
    </div>
    <div id="irregular-note" class="alert alert-info" style="display:none; margin-top:14px;">
        Irregular student: no block is needed. After saving, add the open sections the student selected.
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Create Enrollment</button>
        <a class="btn btn-light" href="enrollments.php">Cancel</a>
    </div>
</form>
</div>

<script>
// Show the block field only for Regular students.
const studentSelect = document.getElementById('student_id');
const blockField = document.getElementById('block-field');
const blockSelect = document.getElementById('block_id');
const note = document.getElementById('irregular-note');

function updateForm() {
    const opt = studentSelect.selectedOptions[0];
    const type = opt ? opt.dataset.class : '';
    const regular = type === 'Regular';
    blockField.style.display = type === 'Irregular' ? 'none' : '';
    note.style.display = type === 'Irregular' ? '' : 'none';
    blockSelect.required = regular;
    // Only show blocks from the student's program.
    for (const b of blockSelect.options) {
        if (!b.value) continue;
        b.hidden = opt && opt.dataset.program && b.dataset.program !== opt.dataset.program;
    }
}
studentSelect.addEventListener('change', updateForm);
updateForm();
</script>
<?php include 'includes/footer.php'; ?>
