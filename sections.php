<?php
require_once 'includes/functions.php';
require_staff();

$term = active_term($pdo);
$stmt = $pdo->prepare(
    "SELECT o.offering_id, o.section_code, o.max_capacity, o.current_enrolled,
            o.max_capacity - o.current_enrolled AS remaining,
            sub.subject_code, sub.subject_title, b.block_code
       FROM subject_offerings o
       JOIN subjects sub ON sub.subject_id = o.subject_id
  LEFT JOIN blocks b ON b.block_id = o.block_id
      WHERE o.term_id = ?
   ORDER BY remaining, o.section_code"
);
$stmt->execute([$term['term_id'] ?? 0]);
$rows = $stmt->fetchAll();

// Meeting times for every section, grouped by section.
$sched = [];
$q = $pdo->query(
    "SELECT cs.offering_id, cs.day_of_week, cs.start_time, cs.end_time, cs.class_type, r.room_code
       FROM class_schedules cs JOIN rooms r ON r.room_id = cs.room_id
   ORDER BY FIELD(cs.day_of_week, 'Mon','Tue','Wed','Thu','Fri','Sat'), cs.start_time"
);
foreach ($q as $m) {
    $sched[$m['offering_id']][] = $m;
}

$page_title = 'Sections';
$active = 'sections';
include 'includes/header.php';
?>
<h1>Sections and Capacity</h1>
<p class="subtitle">Every section offered this term, with enrolled students and remaining slots. Fullest sections first.</p>

<div class="card">
    <div class="table-wrap">
    <table>
        <tr>
            <th>Section</th><th>Subject</th><th>Block</th><th>Schedule</th>
            <th class="num">Capacity</th><th class="num">Enrolled</th><th class="num">Slots left</th><th>Status</th>
        </tr>
        <?php foreach ($rows as $r): $full = $r['remaining'] <= 0; ?>
        <tr>
            <td class="nowrap"><b><?= h($r['section_code']) ?></b></td>
            <td><?= h($r['subject_code'] . ' - ' . $r['subject_title']) ?></td>
            <td><?= h($r['block_code'] ?? 'Open section') ?></td>
            <td class="nowrap"><?php foreach ($sched[$r['offering_id']] ?? [] as $m): ?><?= h(meeting_text($m)) ?><br><?php endforeach; ?></td>
            <td class="num"><?= h($r['max_capacity']) ?></td>
            <td class="num"><?= h($r['current_enrolled']) ?></td>
            <td class="num"><?= h($r['remaining']) ?></td>
            <td><span class="badge badge-<?= $full ? 'full' : 'open' ?>"><?= $full ? 'FULL' : 'Open' ?></span></td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
