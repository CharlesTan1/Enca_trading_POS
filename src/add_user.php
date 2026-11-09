<?php
require_once 'config/database.php';

function addUser($username, $password, $full_name, $role = 'staff') {
    global $pdo;
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $hashed_password, $full_name, $role]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];
    
    if (addUser($username, $password, $full_name, $role)) {
        $message = "User <strong>$username</strong> added successfully!";
        $messageType = 'success';
    } else {
        $message = "Failed to add user. Username may already exist.";
        $messageType = 'error';
    }
}

$users = $pdo->query("SELECT user_id, username, full_name, role, created_at FROM users ORDER BY user_id")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add User - Enca Trading</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js" defer></script>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="main-content">
        <h1 class="page-title">🔐 Add New User</h1>
        <p class="page-subtitle">Create staff or admin accounts</p>

        <?php if ($message): ?>
            <div class="alert <?= $messageType ?>"><?= $message ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="post">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required placeholder="e.g., staff2">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="text" name="password" required placeholder="e.g., staff123">
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required placeholder="e.g., Juan Dela Cruz">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="staff">Staff (Cashier)</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary">Add User</button>
                    <a href="dashboard.php" class="btn btn-outline">Back to Dashboard</a>
                </div>
            </form>
        </div>

        <h3>Existing Users</h3>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Created</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['user_id'] ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['full_name']) ?></td>
                            <td><span class="badge <?= $u['role'] == 'admin' ? 'badge-primary' : 'badge-info' ?>"><?= $u['role'] ?></span></td>
                            <td><?= date('Y-m-d H:i', strtotime($u['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>