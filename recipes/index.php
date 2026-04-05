<?php
require_once __DIR__ . '/../includes/session.php';

$selected_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$query = "
    SELECT r.id, r.name, r.difficulty, r.prep_time, r.cook_time, GROUP_CONCAT(c.name SEPARATOR ', ') as category_names
    FROM recipes r
    LEFT JOIN recipe_categories rc ON r.id = rc.recipe_id
    LEFT JOIN categories c ON rc.category_id = c.id
    WHERE r.user_id = ?";

$params = [$_SESSION['user_id']];
$param_types = "i";

if (!empty($selected_category)) {
    $query .= " AND rc.category_id = ?";
    $params[] = $selected_category;
    $param_types .= "i";
}

if (!empty($search_query)) {
    $query .= " AND r.name LIKE ?";
    $params[] = "%" . $search_query . "%";
    $param_types .= "s";
}

$query .= " GROUP BY r.id LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$total_query = "
    SELECT COUNT(DISTINCT r.id) AS total
    FROM recipes r
    LEFT JOIN recipe_categories rc ON r.id = rc.recipe_id
    WHERE r.user_id = ?";

$params_total = [$_SESSION['user_id']];
$param_types_total = "i";

if (!empty($selected_category)) {
    $total_query .= " AND rc.category_id = ?";
    $params_total[] = $selected_category;
    $param_types_total .= "i";
}

if (!empty($search_query)) {
    $total_query .= " AND r.name LIKE ?";
    $params_total[] = "%" . $search_query . "%";
    $param_types_total .= "s";
}

$total_stmt = $conn->prepare($total_query);
$total_stmt->bind_param($param_types_total, ...$params_total);
$total_stmt->execute();
$total_recipes = $total_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_recipes / $limit);

$categories_result = $conn->query("SELECT id, name FROM categories");

$pageTitle = 'Recipe Library';
include __DIR__ . '/../includes/header.php';
?>

<h2 class="text-center">📚 Recipe Library</h2>

<div class="d-grid gap-2 mb-4">
    <a href="<?= BASE_URL ?>/recipes/create.php" class="btn btn-success btn-lg">➕ Create New Recipe</a>
</div>

<form method="GET" action="<?= BASE_URL ?>/recipes/" class="row g-3 mb-4">
    <div class="col-md-4">
        <label for="category" class="form-label">Category</label>
        <select name="category" id="category" class="form-select">
            <option value="0">All Categories</option>
            <?php while ($row = $categories_result->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= $selected_category == $row['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="col-md-8">
        <label for="search" class="form-label">Search</label>
        <input type="text" name="search" id="search" class="form-control" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search by title">
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Name</th>
                <th>Categories</th>
                <th>Difficulty</th>
                <th>Prep Time (min)</th>
                <th>Cook Time (min)</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['category_names']) ?></td>
                    <td><?= htmlspecialchars($row['difficulty']) ?></td>
                    <td><?= $row['prep_time'] ?></td>
                    <td><?= $row['cook_time'] ?></td>
                    <td><a href="<?= BASE_URL ?>/recipes/details.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm">View Details</a></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<nav aria-label="Page navigation">
    <ul class="pagination justify-content-center">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page - 1 ?>&category=<?= $selected_category ?>&search=<?= urlencode($search_query) ?>">&laquo;</a>
        </li>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&category=<?= $selected_category ?>&search=<?= urlencode($search_query) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page + 1 ?>&category=<?= $selected_category ?>&search=<?= urlencode($search_query) ?>">&raquo;</a>
        </li>
    </ul>
</nav>

<?php include __DIR__ . '/../includes/footer.php'; ?>
