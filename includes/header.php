<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'Recipe Planner') ?></title>
    <link rel="icon" href="<?= BASE_URL ?>/favicon_recipe.jpg" type="image/jpeg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= BASE_URL ?>/menu/">🍽️ Recipe Planner</a>
        <?php if (isset($_SESSION['user_id'])): ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/menu/">Menu Planner</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/recipes/">Recipe Library</a></li>
                <li class="nav-item"><a class="nav-link text-danger" href="<?= BASE_URL ?>/auth/logout.php">Logout</a></li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</nav>
<div class="container mt-4">
