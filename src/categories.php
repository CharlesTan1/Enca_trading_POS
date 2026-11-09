<?php
require_once 'includes/session.php';
requireAdmin();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Add
if (isset($_POST['add'])) {
    $name = trim($_POST['category_name']);
    $desc = $_POST['description'] ?: null;
    $stmt = $pdo->prepare("INSERT INTO categories (category_name, description) VALUES (?, ?)");
    $stmt->execute([$name, $desc]);
    header('Location: categories.php?msg=added');
    exit;
}
// Edit
if (isset($_POST['edit'])) {
    $id = $_POST['category_id'];
    $name = trim($_POST['category_name']);
    $desc = $_POST['description'] ?: null;
    $stmt = $pdo->prepare("UPDATE categories SET category_name=?, description=? WHERE category_id=?");
    $stmt->execute([$name, $desc, $id]);
    header('Location: categories.php?msg=updated');
    exit;
}
// Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->execute([$id]);
    header('Location: categories.php?msg=deleted');
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name")->fetchAll();
$editCat = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE category_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editCat = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Categories - Enca Trading</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js" defer></script>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="main-content">
        <h1 class="page-title">📂 Categories</h1>
        <p class="page-subtitle">Organise your products by category</p>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert success"><?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>

        <div class="form-container">
            <h3><?= $editCat ? '✏️ Edit Category' : '➕ Add New Category' ?></h3>
            <form method="post">
                <?php if ($editCat): ?>
                    <input type="hidden" name="category_id" value="<?= $editCat['category_id'] ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" name="category_name" required value="<?= $editCat['category_name'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" value="<?= $editCat['description'] ?? '' ?>">
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" name="<?= $editCat ? 'edit' : 'add' ?>" class="btn btn-primary"><?= $editCat ? 'Update' : 'Add' ?></button>
                    <?php if ($editCat): ?>
                        <a href="categories.php" class="btn btn-outline">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Description</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td><?= $c['category_id'] ?></td>
                            <td><?= htmlspecialchars($c['category_name']) ?></td>
                            <td><?= htmlspecialchars($c['description'] ?? '') ?></td>
                            <td>
                                <a href="?edit=<?= $c['category_id'] ?>" class="btn-edit">✏️ Edit</a>
                                <a href="?delete=<?= $c['category_id'] ?>" class="btn-delete" onclick="return confirm('Delete this category?')">🗑️ Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>