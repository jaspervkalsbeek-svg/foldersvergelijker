<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- TODO: Update page title -->
    <title>Evenement toevoegen - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- TODO: Update stylesheet href -->
    <link rel="stylesheet" href="adminstyle.css">
</head>
<body>
<?php
session_start();
include_once '../include/db.php';
require_once '../include/auth.php';
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TODO: Declare a variable for every POST field you need, e.g.:
    // $name     = trim($_POST['name']);
    // $desc     = trim($_POST['discription']);
    // $start    = $_POST['start_date'];
    // $end      = $_POST['end_date'];
    // $location = trim($_POST['location']);

    // TODO: Add every required field to this check
    if (!$name || !$start || !$location) {
        $error = 'Vul alle verplichte velden in.';
    } else {
        try {
            // TODO: Replace table name and column list; add one ? per column
            $stmt = $pdo->prepare('INSERT INTO events (name, discription, start_date, end_date, location) VALUES (?, ?, ?, ?, ?)');
            // TODO: Pass variables in the same order as the columns above
            $stmt->execute([$name, $desc, $start, $end ?: null, $location]);
            // TODO: Update the success message entity name
            $success = 'Evenement <strong>' . htmlspecialchars($name) . '</strong> succesvol toegevoegd!';
        } catch (PDOException $e) {
            $error = 'Fout bij opslaan: ' . $e->getMessage();
        }
    }
}
?>

<aside class="sidebar">
    <div class="sidebar-logo">Admin Panel<span>Spik &amp; Span</span></div>
    <div class="nav-label">Beheer</div>
    <a href="index.php"    class="nav-item"><span class="icon">🏠</span> Dashboard</a>
    <!-- TODO: Update nav labels and mark the current page as active -->
    <a href="add.php"      class="nav-item active"><span class="icon">🎪</span> Evenementen</a>
    <a href="add2.php"     class="nav-item"><span class="icon">🎟️</span> Ticket types</a>
    <a href="add3.php"     class="nav-item"><span class="icon">🏷️</span> Kortingscodes</a>
    <a href="overview.php" class="nav-item"><span class="icon">📦</span> Bestellingen</a>
    <div class="sidebar-footer"><a href="../public/festivals.php">← Terug naar site</a></div>
</aside>

<main class="main">
    <a href="index.php" class="back">← Terug naar dashboard</a>

    <div class="page-header">
        <!-- TODO: Update heading and subtitle -->
        <h1>Evenement toevoegen</h1>
        <p>Voeg een nieuw evenement toe aan de database</p>
    </div>

    <?php if ($success): ?><div class="alert success"><?= $success ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST">
        <div class="section">
            <div class="section-title">Evenement gegevens</div>

            <!-- TODO: Add/remove fields to match your table columns -->
            <div class="field">
                <label>Naam *</label>
                <input type="text" name="name" placeholder="Spik & Span XXL 2027" required
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>

            <div class="field">
                <label>Omschrijving</label>
                <textarea name="discription" placeholder="Beschrijving van het evenement..."><?= htmlspecialchars($_POST['discription'] ?? '') ?></textarea>
            </div>

            <div class="row">
                <div class="field">
                    <label>Startdatum *</label>
                    <input type="datetime-local" name="start_date" required
                           value="<?= $_POST['start_date'] ?? '' ?>">
                </div>
                <div class="field">
                    <label>Einddatum</label>
                    <input type="datetime-local" name="end_date"
                           value="<?= $_POST['end_date'] ?? '' ?>">
                </div>
            </div>

            <div class="field">
                <label>Locatie *</label>
                <input type="text" name="location" placeholder="Landgoed Kasteel Limbricht" required
                       value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
            </div>
        </div>

        <!-- TODO: Update button label -->
        <button type="submit" class="submit-btn">Evenement toevoegen →</button>
    </form>
</main>
</body>
</html>