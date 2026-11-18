<?php
require_once 'includes/functions.php';
require_staff();

$id      = $_GET['id'] ?? null;
$editing = $id !== null;
$student = [
    'student_id' => '', 'first_name' => '', 'middle_name' => '', 'last_name' => '',
    'email' => '', 'contact_number' => '', 'program_id' => '', 'year_level' => 1,
    'classification' => 'Regular',
];

if ($editing) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch();
    if (!$student) {
        flash('error', 'Student not found.');
        redirect('students.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'student_id'     => trim($_POST['student_id'] ?? ''),
        'first_name'     => trim($_POST['first_name'] ?? ''),
        'middle_name'    => trim($_POST['middle_name'] ?? '') ?: null,
        'last_name'      => trim($_POST['last_name'] ?? ''),
        'email'          => trim($_POST['email'] ?? ''),
        'contact_number' => trim($_POST['contact_number'] ?? '') ?: null,
        'program_id'     => (int)($_POST['program_id'] ?? 0),
        'year_level'     => (int)($_POST['year_level'] ?? 0),
        'classification' => $_POST['classification'] ?? 'Regular',
    ];
    $student = array_merge($student, $data);

    $errors = [];
    if ($data['student_id'] === '' || $data['first_name'] === '' || $data['last_name'] === '' || $data['email'] === '') {
        $errors[] = 'Student ID, first name, last name, and email are required.';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($data['program_id'] <= 0) {
        $errors[] = 'Select a program.';
    }
    if ($data['year_level'] < 1 || $data['year_level'] > 5) {
        $errors[] = 'Year level must be from 1 to 5.';
    }

    if (!$errors) {
        try {
            if ($editing) {
                $stmt = $pdo->prepare(
                    "UPDATE students SET first_name = ?, middle_name = ?, last_name = ?, email = ?,
                            contact_number = ?, program_id = ?, year_level = ?, classification = ?
                      WHERE student_id = ?"
                );
                $stmt->execute([
                    $data['first_name'], $data['middle_name'], $data['last_name'], $data['email'],
                    $data['contact_number'], $data['program_id'], $data['year_level'], $data['classification'],
                    $id,
                ]);
                flash('success', 'Student record updated.');
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO students (student_id, first_name, middle_name, last_name, email,
                                           contact_number, program_id, year_level, classification)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute(array_values($data));
                flash('success', 'Student registered.');
            }
            redirect('students.php');
        } catch (PDOException $e) {
            $errors[] = (int)($e->errorInfo[1] ?? 0) === 1062
                ? 'That Student ID or email is already registered.'
                : db_error_message($e);
        }
    }
}

$programs = $pdo->query("SELECT program_id, program_code, program_name FROM programs ORDER BY program_code")->fetchAll();

$page_title = $editing ? 'Edit Student' : 'Register Student';
$active = 'students';
include 'includes/header.php';
?>
<h1><?= $editing ? 'Edit Student' : 'Register Student' ?></h1>
<p class="subtitle"><?= $editing ? 'Update the student record.' : 'Add a new student before creating an enrollment.' ?></p>

<?php foreach ($errors ?? [] as $err): ?>
    <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<div class="card">
<form method="post">
    <div class="form-grid">
        <div>
            <label for="student_id">Student ID</label>
            <input id="student_id" name="student_id" value="<?= h($student['student_id']) ?>" placeholder="2026-00001" <?= $editing ? 'readonly' : 'required' ?>>
            <?php if ($editing): ?><div class="hint">The Student ID cannot be changed.</div><?php endif; ?>
        </div>
        <div>
            <label for="first_name">First name</label>
            <input id="first_name" name="first_name" value="<?= h($student['first_name']) ?>" required>
        </div>
        <div>
            <label for="middle_name">Middle name</label>
            <input id="middle_name" name="middle_name" value="<?= h($student['middle_name']) ?>">
        </div>
        <div>
            <label for="last_name">Last name</label>
            <input id="last_name" name="last_name" value="<?= h($student['last_name']) ?>" required>
        </div>
        <div>
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="<?= h($student['email']) ?>" required>
        </div>
        <div>
            <label for="contact_number">Contact number</label>
            <input id="contact_number" name="contact_number" value="<?= h($student['contact_number']) ?>" placeholder="09XXXXXXXXX">
        </div>
        <div>
            <label for="program_id">Program</label>
            <select id="program_id" name="program_id" required>
                <option value="">Select a program</option>
                <?php foreach ($programs as $p): ?>
                    <option value="<?= h($p['program_id']) ?>" <?= (string)$p['program_id'] === (string)$student['program_id'] ? 'selected' : '' ?>>
                        <?= h($p['program_code'] . ' - ' . $p['program_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="year_level">Year level</label>
            <select id="year_level" name="year_level">
                <?php for ($y = 1; $y <= 5; $y++): ?>
                    <option value="<?= $y ?>" <?= (int)$student['year_level'] === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label for="classification">Classification</label>
            <select id="classification" name="classification">
                <option <?= $student['classification'] === 'Regular' ? 'selected' : '' ?>>Regular</option>
                <option <?= $student['classification'] === 'Irregular' ? 'selected' : '' ?>>Irregular</option>
            </select>
            <div class="hint">Regular students follow a block. Irregular students pick open sections.</div>
        </div>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit"><?= $editing ? 'Save Changes' : 'Register Student' ?></button>
        <a class="btn btn-light" href="students.php">Cancel</a>
    </div>
</form>
</div>
<?php include 'includes/footer.php'; ?>
