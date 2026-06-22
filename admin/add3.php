<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- TODO: Update page title -->
    <title>Kortingscode toevoegen - Admin</title>
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
    // $event_id    = (int)$_POST['event_id'];
    // $name        = trim($_POST['name']);
    // $couponcode  = trim($_POST['couponcode']);
    // $korting_eur = $_POST['korting_eur'] !== '' ? (float)$_POST['korting_eur'] : null;
    // $korting_pct = $_POST['korting_pct'] !== '' ? (float)$_POST['korting_pct'] : null;
    // $min_age     = $_POST['min_age']     !== '' ? (int)$_POST['min_age']        : null;
    // $max_age     = $_POST['max_age']     !== '' ? (int)$_POST['max_age']        : null;

    // TODO: Add every required field to this check
    if (!$name || !$couponcode) {
        $error = 'Naam en kortingscode zijn verplicht.';
    } elseif ($korting_eur === null && $korting_pct === null) {
        // TODO: Remove this elseif if your entity doesn't have this mutual-exclusion rule
        $error = 'Vul minimaal één kortingsbedrag in (euro of percentage).';
    } else {
        try {
            // TODO: Replace table name and column list; add one ? per column
            // FIX: event_id added to INSERT (was collected in the form but missing from the query)
            $stmt = $pdo->prepare('INSERT INTO coupons_tb (name, korting_eur, `korting_%`, min_age, max_age, couponcode, event_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
            // TODO: Pass variables in the same order as the columns above
            $stmt->execute([$name, $korting_eur, $korting_pct, $min_age, $max_age, $couponcode, $event_id]);
            // TODO: Update the success message entity name
            $success = 'Kortingscode <strong>' . htmlspecialchars($couponcode) . '</strong> succesvol aangemaakt!';
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
    <a href="add2.php"     class="nav-item"><span class="icon">🎟️</span> Ticket types</a>
    <a href="add3.php"     class="nav-item active"><span class="icon">🏷️</span> Kortingscodes</a>
    <a href="overview.php" class="nav-item"><span class="icon">📦</span> Bestellingen</a>
    <div class="sidebar-footer"><a href="../public/festivals.php">← Terug naar site</a></div>
</aside>

<main class="main">
    <a href="index.php" class="back">← Terug naar dashboard</a>

    <div class="page-header">
        <!-- TODO: Update heading and subtitle -->
        <h1>Kortingscode toevoegen</h1>
        <p>Maak een nieuwe kortingscode aan</p>
    </div>

    <?php if ($success): ?><div class="alert success"><?= $success ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST">
        <div class="section">
            <!-- TODO: Update section title -->
            <div class="section-title">Kortingscode gegevens</div>

            <!-- Parent record dropdown -->
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
            <div class="row">
                <div class="field">
                    <label>Naam *</label>
                    <input type="text" name="name" placeholder="Black Friday" required
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Kortingscode *</label>
                    <input type="text" name="couponcode" placeholder="BLACKFRIDAY25" required
                           value="<?= htmlspecialchars($_POST['couponcode'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label>Korting in euro's</label>
                    <input type="number" name="korting_eur" placeholder="10.00" step="0.01" min="0"
                           value="<?= $_POST['korting_eur'] ?? '' ?>">
                    <div class="hint">Vul óf euro óf percentage in, niet beide</div>
                </div>
                <div class="field">
                    <label>Korting in %</label>
                    <input type="number" name="korting_pct" placeholder="20" step="0.01" min="0" max="100"
                           value="<?= $_POST['korting_pct'] ?? '' ?>">
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label>Minimumleeftijd</label>
                    <input type="number" name="min_age" placeholder="65" min="0"
                           value="<?= $_POST['min_age'] ?? '' ?>">
                    <div class="hint">Leeg laten voor geen leeftijdsgrens</div>
                </div>
                <div class="field">
                    <label>Maximumleeftijd</label>
                    <input type="number" name="max_age" placeholder="12" min="0"
                           value="<?= $_POST['max_age'] ?? '' ?>">
                </div>
            </div>
        </div>

        <!-- TODO: Update button label -->
        <button type="submit" class="submit-btn">Kortingscode aanmaken →</button>
    </form>
</main>
</body>
</html>