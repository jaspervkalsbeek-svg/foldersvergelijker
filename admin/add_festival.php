<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- TODO: Update page title -->
    <title>Nieuw festival - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- FIX: Removed trailing space from stylesheet filename -->
    <!-- TODO: Update stylesheet href -->
    <link rel="stylesheet" href="adminstyle.css">
</head><!-- FIX: </head> tag was missing entirely -->
<body>
<?php
session_start();
require_once '../include/db.php';
require_once '../include/auth.php';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TODO: Declare a variable for every POST field you need
    $name     = trim($_POST['name']);
    $desc     = trim($_POST['discription']);
    $start    = $_POST['start_date'];
    $end      = $_POST['end_date'];
    $location = trim($_POST['location']);
    $types    = $_POST['types'] ?? [];

    // TODO: Add every required field to this check
    if (!$name || !$start || !$location) {
        $error = 'Vul alle verplichte velden in.';
    } else {
        try {
            // Step 1: Insert the parent record
            // TODO: Replace table name and column list; add one ? per column
            $stmt = $pdo->prepare('INSERT INTO events (name, discription, start_date, end_date, location) VALUES (?, ?, ?, ?, ?)');
            // TODO: Pass variables in the same order as the columns above
            $stmt->execute([$name, $desc, $start, $end ?: null, $location]);
            $event_id = $pdo->lastInsertId();

            // Step 2: Insert each child record (ticket types)
            // TODO: If your entity has no child records, remove this foreach block
            foreach ($types as $t) {
                // TODO: Update the required field check for child records
                if (empty($t['name']) || !isset($t['price'])) continue;

                // TODO: Replace table name and column list; add one ? per column
                $ttStmt = $pdo->prepare('INSERT INTO ticket_type_tb (name, price, max_per_order, max_available, event_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                // TODO: Pass variables in the same order as the columns above
                $ttStmt->execute([
                    trim($t['name']),
                    (float)$t['price'],
                    (int)($t['max_per_order'] ?: 8),
                    (int)($t['max_available'] ?: 0) ?: null,
                    $event_id,
                ]);
            }

            // TODO: Update the success message entity name
            $success = 'Festival <strong>' . htmlspecialchars($name) . '</strong> succesvol aangemaakt!';
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
    <!-- TODO: Update nav labels; none are active here since this page is reached via the dashboard card -->
    <a href="add.php"      class="nav-item"><span class="icon">🎪</span> Evenementen</a>
    <a href="add2.php"     class="nav-item"><span class="icon">🎟️</span> Ticket types</a>
    <a href="add3.php"     class="nav-item"><span class="icon">🏷️</span> Kortingscodes</a>
    <a href="overview.php" class="nav-item"><span class="icon">📦</span> Bestellingen</a>
    <div class="sidebar-footer"><a href="../public/festivals.php">← Terug naar site</a></div>
</aside>

<main class="main">
    <a href="index.php" class="back">← Terug naar dashboard</a>

    <div class="page-header">
        <!-- TODO: Update heading and subtitle -->
        <h1>Nieuw festival</h1>
        <p>Voeg een volledig nieuw festival toe met ticket types</p>
    </div>

    <?php if ($success): ?><div class="alert success"><?= $success ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST">

        <!-- Parent record fields -->
        <div class="section">
            <!-- TODO: Update section title -->
            <div class="section-title">Festival gegevens</div>

            <!-- TODO: Add/remove fields to match your table columns -->
            <div class="field">
                <label>Naam *</label>
                <input type="text" name="name" placeholder="Spik & Span XXL 2027" required
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>

            <div class="field">
                <label>Omschrijving</label>
                <textarea name="discription" placeholder="Beschrijving van het festival..."><?= htmlspecialchars($_POST['discription'] ?? '') ?></textarea>
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
                <input type="text" name="location" placeholder="Landgoed Kasteel Limbricht, Limbricht" required
                       value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
            </div>
        </div>

        <!-- Child record rows (ticket types) -->
        <!-- TODO: If your entity has no child records, remove this entire section and the JS below -->
        <div class="section">
            <!-- TODO: Update section title -->
            <div class="section-title">Ticket types</div>
            <div id="ticket-types">
                <!-- Default: 1 child row -->
                <div class="ticket-type-row">
                    <button type="button" class="remove-btn" onclick="removeType(this)">✕</button>
                    <div class="row">
                        <div class="field">
                            <!-- TODO: Update child field labels and input names -->
                            <label>Naam</label>
                            <input type="text" name="types[0][name]" placeholder="Normaal">
                        </div>
                        <div class="field">
                            <label>Prijs (€)</label>
                            <input type="number" name="types[0][price]" placeholder="35.00" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="field">
                            <label>Max per bestelling</label>
                            <input type="number" name="types[0][max_per_order]" placeholder="8" min="1">
                        </div>
                        <div class="field">
                            <label>Max beschikbaar</label>
                            <input type="number" name="types[0][max_available]" placeholder="500" min="0">
                        </div>
                    </div>
                </div>
            </div>
            <!-- TODO: Update button label -->
            <button type="button" class="add-type-btn" onclick="addType()">+ Ticket type toevoegen</button>
        </div>

        <!-- TODO: Update button label -->
        <button type="submit" class="submit-btn">Festival aanmaken →</button>
    </form>
</main>

<!-- TODO: If you removed the child-records section above, remove this <script> block too -->
<script>
let typeCount = 1;

function addType() {
    const i = typeCount++;
    // TODO: Update field names and placeholders to match your child record columns
    const html = `
    <div class="ticket-type-row">
        <button type="button" class="remove-btn" onclick="removeType(this)">✕</button>
        <div class="row">
            <div class="field">
                <label>Naam</label>
                <input type="text" name="types[${i}][name]" placeholder="Junior">
            </div>
            <div class="field">
                <label>Prijs (€)</label>
                <input type="number" name="types[${i}][price]" placeholder="20.00" step="0.01" min="0">
            </div>
        </div>
        <div class="row">
            <div class="field">
                <label>Max per bestelling</label>
                <input type="number" name="types[${i}][max_per_order]" placeholder="8" min="1">
            </div>
            <div class="field">
                <label>Max beschikbaar</label>
                <input type="number" name="types[${i}][max_available]" placeholder="500" min="0">
            </div>
        </div>
    </div>`;
    document.getElementById('ticket-types').insertAdjacentHTML('beforeend', html);
}

function removeType(btn) {
    // Keeps at least 1 child row at all times
    const rows = document.querySelectorAll('.ticket-type-row');
    if (rows.length > 1) btn.closest('.ticket-type-row').remove();
}
</script>
</body>
</html>