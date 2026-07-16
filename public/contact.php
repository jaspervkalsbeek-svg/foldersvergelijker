<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../include/functions.php';
require_once __DIR__ . '/../lib/PHPMailer.php';
require_once __DIR__ . '/../lib/SMTP.php';
require_once __DIR__ . '/../lib/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($name && $email && $message && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'jasper.v.kalsbeek@gmail.com';
            $mail->Password = 'epqk nagz zgze lbla';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom('jasper.v.kalsbeek@gmail.com', 'Contactformulier');
            $mail->addReplyTo($email, $name);
            $mail->addAddress('jasper.v.kalsbeek@gmail.com');
            $mail->isHTML(false);
            $mail->Subject = 'Contactformulier - Folders Vergelijker';
            $mail->Body = "Naam: $name\nE-mail: $email\n\nBericht:\n$message";
            $mail->send();
            $sent = true;
        } catch (\Exception $e) {}
    }
}
?><!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact - Folders Vergelijker</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
.legal{max-width:600px;margin:0 auto;padding:48px 0}
.legal h1{font-size:2rem;font-weight:800;margin-bottom:8px}
.legal p.sub{color:var(--text-muted);font-size:.92rem;margin-bottom:32px}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:.88rem;font-weight:600;margin-bottom:6px;color:var(--text-dim)}
.form-group input,.form-group textarea{width:100%;background:var(--card);border:1px solid var(--border);border-radius:var(--radius-sm);padding:11px 14px;color:var(--text);font-size:.92rem;font-family:inherit;outline:none;transition:border-color var(--transition)}
.form-group input:focus,.form-group textarea:focus{border-color:var(--yellow)}
.form-group textarea{min-height:140px;resize:vertical}
.submit-btn{background:var(--yellow);color:#000;border:none;border-radius:var(--radius);padding:12px 28px;font-size:.95rem;font-weight:700;cursor:pointer;transition:opacity var(--transition);font-family:inherit}
.submit-btn:hover{opacity:.9}
.success-msg{background:rgba(129,199,132,.1);border:1px solid rgba(129,199,132,.3);border-radius:var(--radius);padding:20px;margin-bottom:20px;color:#81c784;font-weight:600}
</style>
</head>
<body>
<header class="header"><div class="container"><a href="index.php" class="logo">Folders<span>Vergelijker</span></a><nav class="nav"><a href="index.php">Home</a><a href="stores.php">Winkels</a><a href="shopping-list.php">Boodschappenlijstje</a></nav></div></header>
<main class="container"><div class="legal">
<h1>Contact</h1>
<p class="sub">Heb je een vraag, opmerking of klacht? Laat het ons weten.</p>

<?php if ($sent): ?>
<div class="success-msg">Bericht verzonden! Wij nemen zo snel mogelijk contact met je op.</div>
<?php endif; ?>

<form method="POST">
<div class="form-group"><label for="name">Naam</label><input type="text" id="name" name="name" required></div>
<div class="form-group"><label for="email">E-mail</label><input type="email" id="email" name="email" required></div>
<div class="form-group"><label for="message">Bericht</label><textarea id="message" name="message" required></textarea></div>
<button type="submit" class="submit-btn">Verstuur bericht</button>
</form>
</div></main>
<footer class="footer"><div class="container"><p>Folders Vergelijker - <a href="privacy.php">Privacybeleid</a> - <a href="voorwaarden.php">Voorwaarden</a> - <a href="contact.php">Contact</a></p></div></footer>
</body>
</html>
