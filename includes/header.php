<?php
// Set $page_title and $active before including this file.
// Set $portal = true on the student portal page.
$page_title = $page_title ?? 'BU Enrollment Management System';
$active     = $active ?? '';
$portal     = $portal ?? false;
$nav = [
    'dashboard'   => ['index.php',       'Dashboard'],
    'students'    => ['students.php',    'Students'],
    'enrollments' => ['enrollments.php', 'Enrollments'],
    'sections'    => ['sections.php',    'Sections'],
    'requests'    => ['requests.php',    'Verification Requests'],
    'reports'     => ['reports.php',     'Reports'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($page_title) ?> | BU Enrollment</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar no-print">
    <div class="brand">
        <span class="brand-mark">BU</span>
        <div>
            <div class="brand-name">Enrollment Management System</div>
            <div class="brand-sub"><?= $portal ? 'Student Portal' : 'Registrar' ?></div>
        </div>
    </div>
    <div class="topbar-right">
        <?php if ($portal): ?>
            <a href="login.php" class="link-light">Registrar login</a>
        <?php elseif (!empty($_SESSION['staff_id'])): ?>
            <span class="user"><?= h($_SESSION['staff_name']) ?></span>
            <a href="logout.php" class="link-light">Log out</a>
        <?php endif; ?>
    </div>
</header>

<?php if (!$portal && !empty($_SESSION['staff_id'])): ?>
<nav class="sidenav no-print">
    <?php foreach ($nav as $key => [$href, $label]): ?>
        <a href="<?= $href ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
    <a href="portal.php" class="portal-link">Open Student Portal</a>
</nav>
<?php endif; ?>

<main class="<?= (!$portal && !empty($_SESSION['staff_id'])) ? 'with-nav' : 'full' ?>">
<?php if (!empty($_SESSION['flash'])): ?>
    <?php foreach ($_SESSION['flash'] as $f): ?>
        <div class="alert alert-<?= h($f['type']) ?> no-print"><?= h($f['message']) ?></div>
    <?php endforeach; unset($_SESSION['flash']); ?>
<?php endif; ?>
