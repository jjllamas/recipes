<?php
require_once __DIR__ . '/../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/menu/');
    exit();
}

$week = (int)$_POST['week'];

$stmt = $conn->prepare("
    SELECT i.name AS ingredient_name,
           ri.quantity,
           ri.unit,
           r.name AS recipe_name
    FROM menu_planner mp
    JOIN recipe_ingredients ri ON mp.recipe_id = ri.recipe_id
    JOIN ingredients i ON ri.ingredient_id = i.id
    JOIN recipes r ON mp.recipe_id = r.id
    WHERE mp.week = ? AND mp.user_id = ?
    ORDER BY i.name");
$stmt->bind_param("ii", $week, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

// Normalize unit aliases
$unit_aliases = [
    'gr' => 'g', 'grs' => 'g', 'gramos' => 'g', 'gram' => 'g', 'grams' => 'g',
    'ml' => 'ml', 'mL' => 'ml', 'mililitros' => 'ml',
    'kg' => 'kg', 'kgs' => 'kg', 'kilogramos' => 'kg',
    'l' => 'l', 'lt' => 'l', 'litro' => 'l', 'litros' => 'l',
    'tbsp' => 'tbsp', 'cucharada' => 'tbsp', 'cucharadas' => 'tbsp',
    'tsp' => 'tsp', 'cucharadita' => 'tsp', 'cucharaditas' => 'tsp',
    'unidad' => 'ud', 'unidades' => 'ud', 'unit' => 'ud', 'units' => 'ud', 'u' => 'ud',
];

$shopping_list = [];
while ($row = $result->fetch_assoc()) {
    $name_key  = strtolower(trim($row['ingredient_name']));
    $unit_raw  = strtolower(trim($row['unit']));
    $unit_norm = $unit_aliases[$unit_raw] ?? $unit_raw;

    if (!isset($shopping_list[$name_key])) {
        $shopping_list[$name_key] = [
            'name'    => ucfirst(trim($row['ingredient_name'])),
            'amounts' => [],   // unit => total quantity
            'used_in' => [],
        ];
    }
    $shopping_list[$name_key]['amounts'][$unit_norm] = ($shopping_list[$name_key]['amounts'][$unit_norm] ?? 0) + $row['quantity'];
    $shopping_list[$name_key]['used_in'][$row['recipe_name']] = true;
}

// Format used_in as string
foreach ($shopping_list as &$item) {
    $item['used_in'] = implode(', ', array_keys($item['used_in']));
}
unset($item);

// Sort by name
uasort($shopping_list, fn($a, $b) => strcasecmp($a['name'], $b['name']));
$stmt->close();

// Group by first letter
$grouped = [];
foreach ($shopping_list as $item) {
    $letter = strtoupper(mb_substr($item['name'], 0, 1));
    $grouped[$letter][] = $item;
}
ksort($grouped);

$pageTitle = 'Shopping List — Week ' . $week;
$extraHead = '
<style>
    .item-row { cursor: pointer; transition: background .15s; }
    .item-row:hover { background: #f8f9fa; }
    .item-row.checked { background: #f0fdf4; }
    .item-row.checked .item-name,
    .item-row.checked .item-qty { text-decoration: line-through; color: #adb5bd; }
    .check-circle {
        width: 22px; height: 22px; border-radius: 50%;
        border: 2px solid #dee2e6; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        transition: all .15s;
    }
    .item-row.checked .check-circle {
        background: #22c55e; border-color: #22c55e; color: #fff;
    }
    .letter-group h6 { letter-spacing: .08em; }
    @media print {
        @page { size: A4 portrait; margin: 10mm 12mm; }
        .no-print { display: none !important; }
        nav, footer { display: none !important; }
        .container { max-width: 100% !important; padding: 0 !important; }
        .col-lg-7 { width: 100% !important; max-width: 100% !important; }

        h2 { font-size: 13pt !important; margin-bottom: 4mm !important; }

        /* Two-column layout */
        .print-columns { columns: 2; column-gap: 8mm; }
        .letter-group { break-inside: avoid; margin-bottom: 2mm !important; }
        .letter-group h6 { font-size: 7pt !important; margin-bottom: 1mm !important; padding-bottom: 0 !important; border-bottom: 0.5pt solid #ccc !important; }

        .item-row { padding: 1px 2px !important; gap: 4px !important; }
        .item-name { font-size: 8pt !important; font-weight: normal !important; }
        .item-qty  { font-size: 8pt !important; }
        .check-circle {
            width: 10px !important; height: 10px !important;
            border: 1pt solid #999 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .item-row.checked .check-circle {
            background: #22c55e !important;
            border-color: #22c55e !important;
        }
        .item-row.checked .item-name,
        .item-row.checked .item-qty { text-decoration: line-through; color: #aaa !important; }
    }
</style>';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-lg-7">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">🛒 Shopping List <span class="text-muted fs-5 fw-normal">Week <?= $week ?></span></h2>
    <div class="d-flex gap-2 no-print">
        <button class="btn btn-outline-secondary btn-sm" onclick="uncheckAll()">
            <i class="bi bi-arrow-counterclockwise"></i> Uncheck all
        </button>
        <button class="btn btn-outline-danger btn-sm" onclick="removeChecked()">
            <i class="bi bi-trash"></i> Remove checked
        </button>
        <button class="btn btn-outline-dark btn-sm" onclick="window.print()">
            <i class="bi bi-printer"></i> Print
        </button>
    </div>
</div>

<?php if (empty($shopping_list)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-cart-x" style="font-size:3rem;"></i>
        <p class="mt-3">No ingredients found for week <?= $week ?>.</p>
        <a href="<?= BASE_URL ?>/menu/" class="btn btn-primary btn-sm">Go to Menu Planner</a>
    </div>
<?php else: ?>

    <!-- Progress bar -->
    <div class="mb-4 no-print">
        <div class="d-flex justify-content-between small text-muted mb-1">
            <span id="progressLabel">0 of <?= count($shopping_list) ?> items checked</span>
            <span id="progressPct">0%</span>
        </div>
        <div class="progress" style="height:8px;">
            <div class="progress-bar bg-success" id="progressBar" style="width:0%;"></div>
        </div>
    </div>

    <div class="print-columns">
    <?php foreach ($grouped as $letter => $items): ?>
    <div class="letter-group mb-3">
        <h6 class="text-muted text-uppercase px-1 mb-1 border-bottom pb-1"><?= $letter ?></h6>
        <ul class="list-unstyled mb-0">
            <?php foreach ($items as $item): ?>
            <?php $id = 'item-' . md5($item['name']); ?>
            <?php
                $qty_parts = [];
                foreach ($item['amounts'] as $unit => $qty) {
                    $qty_parts[] = ($qty == (int)$qty ? (int)$qty : $qty) . ' <span class="text-muted">' . htmlspecialchars($unit) . '</span>';
                }
                $qty_display = implode(' · ', $qty_parts);
            ?>
            <li class="item-row d-flex align-items-center gap-3 px-2 py-2 rounded"
                id="<?= $id ?>" onclick="toggleItem('<?= $id ?>')">
                <div class="check-circle">
                    <i class="bi bi-check2" style="font-size:.85rem;"></i>
                </div>
                <div class="flex-grow-1">
                    <span class="item-name fw-semibold"><?= htmlspecialchars($item['name']) ?></span>
                    <small class="text-muted ms-2 no-print" title="<?= htmlspecialchars($item['used_in']) ?>">
                        <i class="bi bi-info-circle"></i>
                    </small>
                </div>
                <span class="item-qty text-end text-nowrap"><?= $qty_display ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endforeach; ?>
    </div><!-- /print-columns -->

    <div class="text-center mt-4 no-print">
        <a href="<?= BASE_URL ?>/menu/?week=<?= $week ?>" class="btn btn-outline-secondary btn-sm">← Back to Menu</a>
    </div>

<?php endif; ?>
</div>
</div>

<script>
const TOTAL = <?= count($shopping_list) ?>;

function toggleItem(id) {
    const row = document.getElementById(id);
    row.classList.toggle('checked');
    saveState();
    updateProgress();
}

function uncheckAll() {
    document.querySelectorAll('.item-row.checked').forEach(r => r.classList.remove('checked'));
    saveState();
    updateProgress();
}

function removeChecked() {
    document.querySelectorAll('.item-row.checked').forEach(r => {
        const group = r.closest('.letter-group');
        r.remove();
        // Remove the letter group if no items left
        if (group && group.querySelectorAll('.item-row').length === 0) {
            group.remove();
        }
    });
    saveState();
    updateProgress();
}

function updateProgress() {
    const total   = document.querySelectorAll('.item-row').length;
    const checked = document.querySelectorAll('.item-row.checked').length;
    const pct = total ? Math.round(checked / total * 100) : 0;
    document.getElementById('progressBar').style.width = pct + '%';
    document.getElementById('progressLabel').textContent = checked + ' of ' + total + ' items checked';
    document.getElementById('progressPct').textContent = pct + '%';
}

function saveState() {
    const state = {};
    document.querySelectorAll('.item-row').forEach(r => {
        state[r.id] = r.classList.contains('checked');
    });
    sessionStorage.setItem('shopping_<?= $week ?>', JSON.stringify(state));
}

function loadState() {
    const saved = sessionStorage.getItem('shopping_<?= $week ?>');
    if (!saved) return;
    const state = JSON.parse(saved);
    Object.entries(state).forEach(([id, checked]) => {
        if (checked) document.getElementById(id)?.classList.add('checked');
    });
    updateProgress();
}

loadState();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
