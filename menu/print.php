<?php
require_once __DIR__ . '/../includes/session.php';

$current_week = isset($_GET['week']) ? (int)$_GET['week'] : date('W');
$current_year = date('Y');

$weekStart = new DateTime();
$weekStart->setISODate($current_year, $current_week, 1);
$weekEnd = new DateTime();
$weekEnd->setISODate($current_year, $current_week, 7);
$dateRange = $weekStart->format('d M') . ' – ' . $weekEnd->format('d M Y');

$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$day_labels   = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

$meal_types = ['Breakfast', 'Second Breakfast', 'Lunch', 'Afternoon Snack', 'Dinner'];

$meal_colors = [
    'Breakfast'        => '#cfe2ff',
    'Second Breakfast' => '#e2d9f3',
    'Lunch'            => '#d1e7dd',
    'Afternoon Snack'  => '#fff3cd',
    'Dinner'           => '#f8d7da',
];

$menu_stmt = $conn->prepare("
    SELECT mp.day, mp.meal_type, r.name as recipe_name
    FROM menu_planner mp
    JOIN recipes r ON mp.recipe_id = r.id
    WHERE mp.week = ? AND mp.year = ? AND mp.user_id = ?
    ORDER BY mp.day, mp.meal_type");
$menu_stmt->bind_param("iii", $current_week, $current_year, $_SESSION['user_id']);
$menu_stmt->execute();

$menu_data = [];
foreach ($menu_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $menu_data[$row['day']][$row['meal_type']][] = $row['recipe_name'];
}
$menu_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Menu — Week <?= $current_week ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 10mm;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.5pt;
            color: #111;
        }

        /* ── Toolbar (visible en pantalla, oculto al imprimir) ── */
        .toolbar {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .toolbar button {
            background: #0d6efd;
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 7px 18px;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .toolbar button:hover { background: #0b5ed7; }
        .toolbar a {
            color: #6c757d;
            text-decoration: none;
            font-size: 13px;
        }
        .toolbar a:hover { text-decoration: underline; }

        /* ── Page content ── */
        .page {
            padding: 6mm 4mm;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 5mm;
            border-bottom: 2px solid #333;
            padding-bottom: 3mm;
        }
        .page-header h1 {
            font-size: 15pt;
            font-weight: 700;
        }
        .page-header .week-info {
            text-align: right;
            font-size: 9pt;
            color: #555;
            line-height: 1.5;
        }
        .page-header .week-info strong {
            font-size: 11pt;
            color: #111;
        }

        /* ── Table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        thead th {
            font-size: 7.5pt;
            font-weight: 700;
            text-align: center;
            padding: 3px 2px;
            border: 1px solid #999;
        }
        thead th.day-header {
            background: #e9ecef;
            width: 13mm;
        }

        tbody td {
            border: 1px solid #bbb;
            vertical-align: top;
            padding: 3px 4px;
            height: 28mm;
        }
        tbody td.day-cell {
            background: #e9ecef;
            text-align: center;
            vertical-align: middle;
            font-weight: 700;
            font-size: 8pt;
            line-height: 1.4;
            width: 13mm;
        }
        tbody td.day-cell .day-date {
            display: block;
            font-weight: 400;
            font-size: 7pt;
            color: #555;
        }
        tbody tr.weekend td.day-cell {
            background: #e8d5f5;
        }
        tbody tr.weekend td {
            background: #faf5ff;
        }

        .recipe-entry {
            display: block;
            font-size: 7.5pt;
            line-height: 1.4;
            padding: 1px 0;
        }
        .recipe-entry::before {
            content: '• ';
            color: #888;
        }
        .empty {
            color: #ccc;
            font-size: 7pt;
            font-style: italic;
        }

        /* ── Footer ── */
        .page-footer {
            margin-top: 4mm;
            text-align: right;
            font-size: 7pt;
            color: #aaa;
            border-top: 1px solid #ddd;
            padding-top: 2mm;
        }

        @media print {
            .toolbar { display: none; }
            .page { padding: 0; }
        }
    </style>
</head>
<body>

<!-- Toolbar -->
<div class="toolbar">
    <button onclick="window.print()">🖨️ Print / Save as PDF</button>
    <a href="<?= BASE_URL ?>/menu/?week=<?= $current_week ?>">← Back to Menu Planner</a>
</div>

<!-- A4 content -->
<div class="page">

    <div class="page-header">
        <h1>🍽️ Weekly Menu</h1>
        <div class="week-info">
            <strong>Week <?= $current_week ?></strong><br>
            <?= $dateRange ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="day-header"></th>
                <?php foreach ($meal_types as $meal): ?>
                    <th style="background-color:<?= $meal_colors[$meal] ?>;"><?= $meal ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($days_of_week as $i => $day):
                $isWeekend = in_array($day, ['Saturday', 'Sunday']);
                $date = (clone $weekStart)->modify("+{$i} days");
            ?>
                <tr class="<?= $isWeekend ? 'weekend' : '' ?>">
                    <td class="day-cell">
                        <?= $day_labels[$i] ?>
                        <span class="day-date"><?= $date->format('d M') ?></span>
                    </td>
                    <?php foreach ($meal_types as $meal): ?>
                        <td>
                            <?php if (!empty($menu_data[$day][$meal])): ?>
                                <?php foreach ($menu_data[$day][$meal] as $name): ?>
                                    <span class="recipe-entry"><?= htmlspecialchars($name) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="empty">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="page-footer">
        Printed on <?= date('d M Y') ?> · Recipe Planner
    </div>

</div>
</body>
</html>
