<?php
require_once 'includes/session.php';
requireAdmin();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Add
if (isset($_POST['add'])) {
    $name = trim($_POST['supplier_name']);
    $contact = $_POST['contact_person'] ?: null;
    $phone = $_POST['phone'] ?: null;
    $email = $_POST['email'] ?: null;
    $address = $_POST['address'] ?: null;
    $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $contact, $phone, $email, $address]);
    header('Location: suppliers.php?msg=added');
    exit;
}
// Edit
if (isset($_POST['edit'])) {
    $id = $_POST['supplier_id'];
    $name = trim($_POST['supplier_name']);
    $contact = $_POST['contact_person'] ?: null;
    $phone = $_POST['phone'] ?: null;
    $email = $_POST['email'] ?: null;
    $address = $_POST['address'] ?: null;
    $stmt = $pdo->prepare("UPDATE suppliers SET supplier_name=?, contact_person=?, phone=?, email=?, address=? WHERE supplier_id=?");
    $stmt->execute([$name, $contact, $phone, $email, $address, $id]);
    header('Location: suppliers.php?msg=updated');
    exit;
}
// Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
    $stmt->execute([$id]);
    header('Location: suppliers.php?msg=deleted');
    exit;
}

$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY supplier_name")->fetchAll();
$editSup = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editSup = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Suppliers - Enca Trading</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js" defer></script>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="main-content">
        <h1 class="page-title">🏢 Suppliers</h1>
        <p class="page-subtitle">Manage your product suppliers</p>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert success"><?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>

        <div class="form-container">
            <h3><?= $editSup ? '✏️ Edit Supplier' : '➕ Add New Supplier' ?></h3>
            <form method="post">
                <?php if ($editSup): ?>
                    <input type="hidden" name="supplier_id" value="<?= $editSup['supplier_id'] ?>">
                <?php endif; ?>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Supplier Name</label>
                        <input type="text" name="supplier_name" required value="<?= $editSup['supplier_name'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Contact Person</label>
                        <input type="text" name="contact_person" value="<?= $editSup['contact_person'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?= $editSup['phone'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= $editSup['email'] ?? '' ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Address</label>
                        <input type="text" name="address" value="<?= $editSup['address'] ?? '' ?>">
                    </div>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" name="<?= $editSup ? 'edit' : 'add' ?>" class="btn btn-primary"><?= $editSup ? 'Update' : 'Add' ?></button>
                    <?php if ($editSup): ?>
                        <a href="suppliers.php" class="btn btn-outline">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>Address</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $s): ?>
                        <tr>
                            <td><?= $s['supplier_id'] ?></td>
                            <td><?= htmlspecialchars($s['supplier_name']) ?></td>
                            <td><?= htmlspecialchars($s['contact_person'] ?? '') ?></td>
                            <td><?= htmlspecialchars($s['phone'] ?? '') ?></td>
                            <td><?= htmlspecialchars($s['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($s['address'] ?? '') ?></td>
                            <td>
                                <a href="?edit=<?= $s['supplier_id'] ?>" class="btn-edit">✏️ Edit</a>
                                <a href="?delete=<?= $s['supplier_id'] ?>" class="btn-delete" onclick="return confirm('Delete this supplier?')">🗑️ Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>