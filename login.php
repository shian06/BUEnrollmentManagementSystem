<?php
require_once 'includes/functions.php';

// Simple staff login: pick your account from registrar_staff.
// (The Week 2 design has no password field, so this is a selection only.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT staff_id, full_name FROM registrar_staff WHERE username = ?");
    $stmt->execute([trim($_POST['username'] ?? '')]);
    $staff = $stmt->fetch();
    if ($staff) {
        $_SESSION['staff_id']   = $staff['staff_id'];
        $_SESSION['staff_name'] = $staff['full_name'];
        flash('success', 'Welcome, ' . $staff['full_name'] . '.');
        redirect('index.php');
    }
    flash('error', 'Username not found.');
    redirect('login.php');
}

$staff_list = $pdo->query("SELECT username, full_name, position FROM registrar_staff ORDER BY full_name")->fetchAll();
$page_title = 'Registrar Login';
include 'includes/header.php';
?>
<div class="card login-box">
    <h1>Registrar Login</h1>
    <p class="subtitle">Sign in to manage student enrollment.</p>
    <form method="post">
        <label for="username">Staff account</label>
        <select name="username" id="username" required>
            <?php foreach ($staff_list as $s): ?>
                <option value="<?= h($s['username']) ?>"><?= h($s['full_name']) ?> (<?= h($s['position']) ?>)</option>
            <?php endforeach; ?>
        </select>
        <div class="form-actions">
            <button class="btn" type="submit">Log in</button>
            <a class="btn btn-light" href="portal.php">Student Portal</a>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
