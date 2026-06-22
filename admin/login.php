<?php
// admin/login.php
require_once '../include/db.php';
session_start();

// If already logged in, go straight to dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password'])) {
                // Login successful
                session_regenerate_id(true); // prevents session fixation attacks
                $_SESSION['admin_id']       = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];

                header("Location: index.php");
                exit();
            } else {
                $error = "Gebruikersnaam of wachtwoord onjuist.";
            }
        } catch (PDOException $e) {
            $error = "Er is een fout opgetreden. Probeer het opnieuw.";
            // TODO: In production, log $e->getMessage() to a file instead of showing it
        }
    } else {
        $error = "Vul alle velden in.";
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="adminstyle.css">
    <style>
        body {
            display:         flex;
            justify-content: center;
            align-items:     center;
            min-height:      100vh;
        }

        .login-box {
            background:    var(--card);
            border:        1px solid var(--border);
            border-radius: 16px;
            padding:       48px 40px;
            width:         100%;
            max-width:     420px;
        }

        .login-logo {
            font-family:    'Bebas Neue', sans-serif;
            font-size:      2rem;
            color:          var(--yellow);
            letter-spacing: 2px;
            margin-bottom:  4px;
        }

        .login-logo span {
            display:        block;
            font-family:    'DM Sans', sans-serif;
            font-size:      0.72rem;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color:          var(--text-muted);
            margin-bottom:  32px;
        }

        .login-back {
            display:         block;
            text-align:      center;
            margin-top:      20px;
            font-size:       0.82rem;
            color:           var(--text-muted);
            text-decoration: none;
            transition:      color var(--transition);
        }

        .login-back:hover {
            color: var(--yellow);
        }
    </style>
</head>
<body>

<div class="login-box">
    <!-- TODO: Update project name -->
    <div class="login-logo">
        Admin Panel
        <span>Spik &amp; Span</span>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="field">
            <label>Gebruikersnaam</label>
            <input type="text" name="username" placeholder="gebruikersnaam" required autofocus
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="field">
            <label>Wachtwoord</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="submit-btn">Inloggen →</button>
    </form>

    <!-- TODO: Update href to your public landing page -->
    <a href="../public/index.php" class="login-back">← Terug naar site</a>
</div>

</body>
</html>