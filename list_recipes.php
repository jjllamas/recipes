<?php
include 'includes/db_connect.php';
include 'web_session_start.php';

// Parámetros de filtro.
$selected_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search_query = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$limit = 10; // Número de recetas por página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Construcción de la consulta SQL con filtros
$query = "
    SELECT r.id, r.name, r.difficulty, r.prep_time, r.cook_time, GROUP_CONCAT(c.name SEPARATOR ', ') as category_names
    FROM recipes r
    LEFT JOIN recipe_categories rc ON r.id = rc.recipe_id
    LEFT JOIN categories c ON rc.category_id = c.id
    WHERE r.user_id = ?";

// Aplicar filtros
$params = [$_SESSION['user_id']];
$param_types = "i";

// Filtro de categoría
if (!empty($selected_category)) {
    $query .= " AND rc.category_id = ?";
    $params[] = $selected_category;
    $param_types .= "i";
}

// Filtro de búsqueda en nombre
if (!empty($search_query)) {
    $query .= " AND r.name LIKE ?";
    $params[] = "%" . $search_query . "%";
    $param_types .= "s";
}

// Agrupar por receta para evitar duplicados
$query .= " GROUP BY r.id";

// Añadir paginación al final de la consulta
$query .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

// Preparar y ejecutar la consulta
$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Contar el número total de recetas filtradas para la paginación
$total_query = "
    SELECT COUNT(DISTINCT r.id) AS total
    FROM recipes r
    LEFT JOIN recipe_categories rc ON r.id = rc.recipe_id
    LEFT JOIN categories c ON rc.category_id = c.id
    WHERE r.user_id = ?";

// Aplicar filtros a la consulta de conteo total
$params_total = [$_SESSION['user_id']];
$param_types_total = "i";

// Filtro de categoría
if (!empty($selected_category)) {
    $total_query .= " AND rc.category_id = ?";
    $params_total[] = $selected_category;
    $param_types_total .= "i";
}

// Filtro de búsqueda en nombre
if (!empty($search_query)) {
    $total_query .= " AND r.name LIKE ?";
    $params_total[] = "%" . $search_query . "%";
    $param_types_total .= "s";
}

$total_stmt = $conn->prepare($total_query);
$total_stmt->bind_param($param_types_total, ...$params_total);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_recipes = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_recipes / $limit);

// Obtener las categorías para el filtro
$categories_result = $conn->query("SELECT id, name FROM categories");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recipe Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <h2 class="text-center">📚 Recipe Library</h2>

    <!-- Botón para crear nueva receta -->
    <div class="d-grid gap-2 mb-4">
        <a href="create_recipe.php" class="btn btn-success btn-lg">➕ Create New Recipe</a>
    </div>

    <!-- Formulario de Filtros -->
    <form method="GET" action="list_recipes.php" class="row g-3 mb-4">
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

    <!-- Tabla de recetas -->
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
                        <td><?= htmlspecialchars($row['prep_time']) ?></td>
                        <td><?= htmlspecialchars($row['cook_time']) ?></td>
                        <td><a href="recipe_details.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm">View Details</a></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>&category=<?= $selected_category ?>&search=<?= $search_query ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&category=<?= $selected_category ?>&search=<?= $search_query ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>&category=<?= $selected_category ?>&search=<?= $search_query ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>

    <p class="mt-3 text-center">
        <a href="home.php" class="btn btn-secondary">Back to Home</a>
    </p>
</body>
</html>
