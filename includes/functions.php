<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

// Escape text before showing it on a page.
function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Messages that show once at the top of the next page.
function flash($type, $message) {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function redirect($url) {
    header("Location: $url");
    exit;
}

// Turn a database error into a short message for the user.
// Errors raised by our triggers and stored procedure (SIGNAL) carry
// their own readable text, so we show that text directly.
function db_error_message(PDOException $e) {
    $info = $e->errorInfo;
    if (isset($info[1]) && (int)$info[1] === 1644) {
        return $info[2];
    }
    if (isset($info[1]) && (int)$info[1] === 1062) {
        return 'That record already exists (duplicate entry).';
    }
    if (isset($info[1]) && (int)$info[1] === 1451) {
        return 'This record is still used by other records, so it cannot be deleted.';
    }
    return 'Database error: ' . ($info[2] ?? $e->getMessage());
}

// Pages for registrar staff call this so they require a login.
function require_staff() {
    if (empty($_SESSION['staff_id'])) {
        redirect('login.php');
    }
}

function current_staff_id() {
    return $_SESSION['staff_id'] ?? null;
}

function active_term(PDO $pdo) {
    return $pdo->query("SELECT * FROM academic_terms WHERE is_active = TRUE LIMIT 1")->fetch();
}

function status_badge($status) {
    $class = strtolower(str_replace(' ', '-', (string)$status));
    return '<span class="badge badge-' . h($class) . '">' . h($status) . '</span>';
}

function fmt_time($time) {
    return $time ? date('g:i A', strtotime($time)) : '';
}

function fmt_date($date) {
    return $date ? date('M j, Y', strtotime($date)) : '';
}

function fmt_datetime($dt) {
    return $dt ? date('M j, Y g:i A', strtotime($dt)) : '';
}

// Adds a section to an enrollment by calling the sp_add_subject stored
// procedure. Returns null on success, or the error message.
// If the database has no sp_add_subject procedure yet, the same checks
// are done here in PHP and the row is inserted directly.
function add_subject(PDO $pdo, $enrollment_id, $offering_id) {
    try {
        $stmt = $pdo->prepare("CALL sp_add_subject(?, ?)");
        $stmt->execute([$enrollment_id, $offering_id]);
        // The procedure returns the updated schedule; read past it.
        do {
            try { $stmt->fetchAll(); } catch (PDOException $ignored) { break; }
        } while ($stmt->nextRowset());
        $stmt->closeCursor();
        return null;
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1305) { // 1305 = procedure does not exist
            return db_error_message($e);
        }
    }

    // Fallback without the stored procedure.
    $stmt = $pdo->prepare(
        "SELECT s.classification, e.block_id, e.term_id
           FROM enrollments e JOIN students s ON s.student_id = e.student_id
          WHERE e.enrollment_id = ?"
    );
    $stmt->execute([$enrollment_id]);
    $enr = $stmt->fetch();
    $stmt = $pdo->prepare("SELECT block_id, term_id, current_enrolled, max_capacity FROM subject_offerings WHERE offering_id = ?");
    $stmt->execute([$offering_id]);
    $off = $stmt->fetch();
    if (!$enr) return 'Enrollment record not found.';
    if (!$off) return 'Section not found.';
    if ((int)$off['term_id'] !== (int)$enr['term_id']) return "This section is not offered in the student's enrollment term.";
    if ($enr['classification'] === 'Regular' && (string)$off['block_id'] !== (string)$enr['block_id']) {
        return 'Regular students can only take sections of their assigned block.';
    }
    if ((int)$off['current_enrolled'] >= (int)$off['max_capacity']) {
        return 'This section is already full. Choose another section.';
    }
    try {
        $pdo->prepare("INSERT INTO enrollment_details (enrollment_id, offering_id) VALUES (?, ?)")
            ->execute([$enrollment_id, $offering_id]);
        return null;
    } catch (PDOException $e) {
        return db_error_message($e);
    }
}

// The class schedule of one enrollment (used by the enrollment page,
// the COR, and the student portal, so all three always match).
function enrollment_schedule(PDO $pdo, $enrollment_id) {
    $stmt = $pdo->prepare(
        "SELECT d.enrollment_detail_id, o.offering_id, o.section_code,
                sub.subject_code, sub.subject_title, sub.units,
                cs.day_of_week, cs.start_time, cs.end_time, cs.class_type, r.room_code
           FROM enrollment_details d
           JOIN subject_offerings o ON o.offering_id = d.offering_id
           JOIN subjects sub        ON sub.subject_id = o.subject_id
      LEFT JOIN class_schedules cs  ON cs.offering_id = o.offering_id
      LEFT JOIN rooms r             ON r.room_id = cs.room_id
          WHERE d.enrollment_id = ?
       ORDER BY sub.subject_code, FIELD(cs.day_of_week, 'Mon','Tue','Wed','Thu','Fri','Sat'), cs.start_time"
    );
    $stmt->execute([$enrollment_id]);
    return $stmt->fetchAll();
}

// Groups schedule rows by section, so each subject shows once with all
// of its meeting times.
function group_by_section(array $rows) {
    $out = [];
    foreach ($rows as $r) {
        $key = $r['offering_id'];
        if (!isset($out[$key])) {
            $out[$key] = $r + ['meetings' => []];
        }
        if ($r['day_of_week']) {
            $out[$key]['meetings'][] = $r;
        }
    }
    return $out;
}

function meeting_text(array $m) {
    return $m['day_of_week'] . ' ' . fmt_time($m['start_time']) . '-' . fmt_time($m['end_time'])
         . ' (' . $m['room_code'] . ($m['class_type'] === 'Laboratory' ? ', Lab' : '') . ')';
}

// Is the newest COR for this enrollment older than the last change?
function cor_status(PDO $pdo, $enrollment_id) {
    $stmt = $pdo->prepare(
        "SELECT c.cor_number, c.printed_at, e.last_modified
           FROM enrollments e
      LEFT JOIN cor_records c ON c.enrollment_id = e.enrollment_id
          WHERE e.enrollment_id = ?
       ORDER BY c.printed_at DESC
          LIMIT 1"
    );
    $stmt->execute([$enrollment_id]);
    $row = $stmt->fetch();
    if (!$row || !$row['cor_number']) {
        return ['label' => 'Not printed', 'class' => 'pending', 'row' => $row];
    }
    if (strtotime($row['printed_at']) < strtotime($row['last_modified'])) {
        return ['label' => 'Outdated - reprint', 'class' => 'outdated', 'row' => $row];
    }
    return ['label' => 'Valid', 'class' => 'valid', 'row' => $row];
}
