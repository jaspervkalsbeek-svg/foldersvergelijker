<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin toevoegen – Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
<?php
require_once '../include/auth.php';
require_once '../include/db.php';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username         = trim($_POST['username'] ?? '');
    $password         = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (!$username || !$password || !$password_confirm) {
        $error = 'Vul alle velden in.';
    } elseif (strlen($password) < 8) {
        $error = 'Wachtwoord moet minimaal 8 tekens bevatten.';
    } elseif ($password !== $password_confirm) {
        $error = 'Wachtwoorden komen niet overeen.';
    } else {
        try {
            // Check if username already exists
            $check = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
            $check->execute([$username]);
            if ($check->fetch()) {
                $error = 'Gebruikersnaam is al in gebruik.';
            } else {
                // Hash the password — never store plain text
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare('INSERT INTO admins (username, password) VALUES (?, ?)');
                $stmt->execute([$username, $hashed]);

                $success = 'Admin <strong>' . htmlspecialchars($username) . '</strong> succesvol aangemaakt.';
            }
        } catch (PDOException $e) {
            $error = 'Fout bij opslaan: ' . $e->getMessage();
        }
    }
}
?>

<aside class="sidebar">
    <div class="sidebar-logo">Admin Panel<span>Spik &amp; Span</span></div>
    <div class="nav-label">Beheer</div>
    <a href="index.php"     class="nav-item"><span class="icon">🏠</span> Dashboard</a>
    <a href="add.php"       class="nav-item"><span class="icon">🎪</span> Evenementen</a>
    <a href="add2.php"      class="nav-item"><span class="icon">🎟️</span> Ticket types</a>
    <a href="add3.php"      class="nav-item"><span class="icon">🏷️</span> Kortingscodes</a>
    <a href="overview.php"  class="nav-item"><span class="icon">📦</span> Bestellingen</a>
    <a href="view.php"      class="nav-item"><span class="icon">📋</span> Overzicht</a>
    <a href="add_admin.php" class="nav-item active"><span class="icon">👤</span> Admin toevoegen</a>
    <div class="sidebar-footer"><a href="../public/festivals.php">← Terug naar site</a></div>
</aside>

<main class="main">
    <a href="index.php" class="back">← Terug naar dashboard</a>

    <div class="page-header">
        <h1>Admin toevoegen</h1>
        <p>Maak een nieuw admin account aan</p>
    </div>

    <?php if ($success): ?><div class="alert success"><?= $success ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST">
        <div class="section">
            <div class="section-title">Account gegevens</div>

            <div class="field">
                <label>Gebruikersnaam *</label>
                <input type="text" name="username" required autocomplete="off"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <div class="row">
                <div class="field">
                    <label>Wachtwoord *</label>
                    <input type="password" name="password" required autocomplete="new-password">
                    <div class="hint">Minimaal 8 tekens</div>
                </div>
                <div class="field">
                    <label>Wachtwoord bevestigen *</label>
                    <input type="password" name="password_confirm" required autocomplete="new-password">
                </div>
            </div>
        </div>

        <button type="submit" class="submit-btn">Admin aanmaken →</button>
    </form>
</main>

</body>
</html>