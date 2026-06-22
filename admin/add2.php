<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- TODO: Update page title -->
    <title>Ticket type toevoegen - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- TODO: Update stylesheet href -->
    <link rel="stylesheet" href="adminstyle.css">
</head>
<body>
<?php
session_start();
require_once '../include/auth.php';
include_once '../include/db.php';
$success = '';
$error   = '';

// Fetch parent records for the <select> dropdown.
// TODO: Replace table name, id column and display column; update ORDER BY if needed
$parents = $pdo->query('SELECT id, name FROM events ORDER BY start_date ASC')->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TODO: Declare a variable for every POST field you need, e.g.:
    // $event_id      = (int)$_POST['event_id'];
    // $name          = trim($_POST['name']);
    // $price         = (float)$_POST['price'];
    // $max_per_order = (int)($_POST['max_per_order'] ?: 8);
    // $max_available = $_POST['max_available'] !== '' ? (int)$_POST['max_available'] : null;

    // TODO: Add every required field to this check
    if (!$event_id || !$name || !isset($price)) {
        $error = 'Vul alle verplichte velden in.';
    } else {
        try {
            // TODO: Replace table name and column list; add one ? per column
            $stmt = $pdo->prepare('INSERT INTO ticket_type_tb (name, price, max_per_order, max_available, event_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
            // TODO: Pass variables in the same order as the columns above
            $stmt->execute([$name, $price, $max_per_order, $max_available, $event_id]);
            // TODO: Update the success message entity name
            $success = 'Ticket type <strong>' . htmlspecialchars($name) . '</strong> succesvol toegevoegd!';
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
    <a href="add.php"      class="nav-item"><span class="icon">🎪</span> Evenementen</a>
    <a href="add2.php"     class="nav-item active"><span class="icon">🎟️</span> Ticket types</a>
    <a href="add3.php"     class="nav-item"><span class="icon">🏷️</span> Kortingscodes</a>
    <a href="overview.php" class="nav-item"><span class="icon">📦</span> Bestellingen</a>
    <div class="sidebar-footer"><a href="../public/festivals.php">← Terug naar site</a></div>
</aside>

<main class="main">
    <a href="index.php" class="back">← Terug naar dashboard</a>

    <div class="page-header">
        <!-- TODO: Update heading and subtitle -->
        <h1>Ticket type toevoegen</h1>
        <p>Voeg een ticket type toe aan een bestaand evenement</p>
    </div>

    <?php if ($success): ?><div class="alert success"><?= $success ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST">
        <div class="section">
            <!-- TODO: Update section title -->
            <div class="section-title">Ticket type gegevens</div>

            <!-- Parent record dropdown (e.g. which event this ticket belongs to) -->
            <div class="field">
                <!-- TODO: Update label to match the parent entity -->
                <label>Evenement *</label>
                <select name="event_id" required>
                    <option value="">Selecteer evenement</option>
                    <!-- TODO: $parents is fetched at the top; update the display column if needed -->
                    <?php foreach ($parents as $p): ?>
                        <option value="<?= $p['id'] ?>"
                            <?= (($_POST['event_id'] ?? '') == $p['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- TODO: Add/remove fields to match your table columns -->
            <div class="field">
                <label>Naam *</label>
                <input type="text" name="name" placeholder="Normaal / Junior / Senior" required
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>

            <div class="row">
                <div class="field">
                    <label>Prijs (€) *</label>
                    <input type="number" name="price" placeholder="35.00" step="0.01" min="0" required
                           value="<?= $_POST['price'] ?? '' ?>">
                </div>
                <div class="field">
                    <label>Max per bestelling</label>
                    <input type="number" name="max_per_order" placeholder="8" min="1"
                           value="<?= $_POST['max_per_order'] ?? '8' ?>">
                </div>
            </div>

            <div class="field">
                <label>Max beschikbaar (leeg = onbeperkt)</label>
                <input type="number" name="max_available" placeholder="500" min="0"
                       value="<?= $_POST['max_available'] ?? '' ?>">
            </div>
        </div>

        <!-- TODO: Update button label -->
        <button type="submit" class="submit-btn">Ticket type toevoegen →</button>
    </form>
</main>
</body>
</html>