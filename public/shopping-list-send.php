<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../include/functions.php';
require_once __DIR__ . '/../lib/PHPMailer.php';
require_once __DIR__ . '/../lib/SMTP.php';
require_once __DIR__ . '/../lib/Exception.php';
require_once __DIR__ . '/../lib/fpdf.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$items = $_POST['items'] ?? '';
$email = trim($_POST['email'] ?? '');

if (!$items || !$email) {
    echo json_encode(['success' => false, 'error' => 'Vul producten en email in']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Ongeldig email adres']);
    exit;
}

$productNames = array_filter(array_map('trim', explode("\n", $items)));
if (empty($productNames)) {
    echo json_encode(['success' => false, 'error' => 'Geen producten ingevuld']);
    exit;
}

// Save to DB
$token = bin2hex(random_bytes(16));
$stmt = $pdo->prepare("INSERT INTO shopping_lists (email, token) VALUES (?, ?)");
$stmt->execute([$email, $token]);
$listId = (int)$pdo->lastInsertId();

$stmt = $pdo->prepare("INSERT INTO shopping_list_items (list_id, product_name) VALUES (?, ?)");
foreach ($productNames as $name) {
    $stmt->execute([$listId, $name]);
}

// ── Look up all products ──
$productData = [];
$foundCount = 0;
$totalCount = count($productNames);

foreach ($productNames as $productName) {
    $stmt = $pdo->prepare("SELECT p.name, p.brand, pp.price, pp.unit_price, pp.unit_size, pp.store_id, pp.scraped_at, s.name as store_name, s.country
        FROM products p
        JOIN product_prices pp ON pp.product_id = p.id
        JOIN stores s ON s.id = pp.store_id
        WHERE p.name LIKE ? AND s.active = 1
        ORDER BY pp.price ASC");
    $stmt->execute(["%$productName%"]);
    $results = $stmt->fetchAll();

    $stores = [];
    $seen = [];
    foreach ($results as $r) {
        $key = $r['store_name'] . '|' . $r['country'];
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $stores[] = $r;
    }

    if (empty($stores)) {
        $productData[] = [
            'name' => $productName,
            'found' => false,
            'stores' => []
        ];
    } else {
        $foundCount++;
        $productData[] = [
            'name' => $productName,
            'found' => true,
            'stores' => $stores
        ];
    }
}

// ── Generate PDF matching website design ──
function hex2rgb($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 6) return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
    return [255,255,255];
}

class ShoppingListPdf extends FPDF
{
    private $storeColors = [];

    function __construct($colors = [])
    {
        parent::__construct();
        $this->storeColors = $colors;
    }

    function u($s) { return iconv('UTF-8', 'CP1252//TRANSLIT', $s); }

    function Header()
    {
        // Full page dark background
        $this->SetFillColor(13, 13, 13);
        $this->Rect(0, 0, 210, 297, 'F');
        // Header bar
        $this->SetFillColor(23, 23, 23);
        $this->Rect(0, 0, 210, 28, 'F');
        $this->SetXY(10, 8);
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetTextColor(240, 240, 240);
        $this->Write(8, $this->u('Folders '));
        $this->SetTextColor(255, 214, 0);
        $this->Write(8, $this->u('Vergelijker'));
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(180, 180, 180);
        $this->SetXY(130, 10);
        $this->Cell(70, 6, $this->u('Boodschappenlijstje'), 0, 1, 'R');
        $this->SetDrawColor(255, 214, 0);
        $this->SetLineWidth(0.5);
        $this->Line(10, 28, 200, 28);
        $this->Ln(6);
    }

    function Footer()
    {
        if ($this->GetY() > 270) return;
        $this->SetY(-15);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 10, $this->u('Folders Vergelijker - Pagina ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function ProductCard($data)
    {
        $left = 10;
        $cardW = 190;
        $hasData = $data['found'] && !empty($data['stores']);
        $cheapest = $hasData ? $data['stores'][0] : null;
        $storeCount = count($data['stores']);
        $hasUnit = $cheapest && $cheapest['unit_price'] && (float)$cheapest['unit_price'] > 0;
        $hasBrand = $cheapest && !empty($cheapest['brand']);
        $hasOthers = $storeCount > 1;

        // Compute card height based on content
        $contentLines = 1;
        if ($hasData) {
            if ($hasBrand) $contentLines++;
            $contentLines++; // price row
            if ($hasUnit) $contentLines++;
            if ($hasOthers) $contentLines++;
        }
        $cardH = max(28, 16 + $contentLines * 5 + 4);

        // Page break: move to new page if card doesn't fit within bottom margin
        $pageBreakAt = $this->h - $this->bMargin;
        if ($this->GetY() + $cardH + 4 > $pageBreakAt) {
            $this->AddPage();
        }

        $y = $this->GetY();

        // Card background
        $this->SetFillColor(31, 31, 31);
        $this->SetDrawColor(50, 50, 50);
        $this->SetLineWidth(0.3);
        $this->Rect($left, $y, $cardW, $cardH, 'DF');

        // Checkbox right side
        $cbSize = 7;
        $cbX = $left + $cardW - 16;
        $cbY = $y + ($cardH - $cbSize) / 2;
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.5);
        $this->Rect($cbX, $cbY, $cbSize, $cbSize, 'D');

        if ($hasData) {
            $storeName = $cheapest['store_name'];
            $country = $cheapest['country'];
            $price = number_format((float)$cheapest['price'], 2, ',', '.');
            $unitPrice = (float)$cheapest['unit_price'];
            $unitSize = $cheapest['unit_size'] ?? '';
            $brand = $cheapest['brand'] ?? '';

            // Store badge
            $color = $this->storeColors[$storeName] ?? '#666';
            $rgb = hex2rgb($color);
            $badgeW = $this->GetStringWidth($this->u($storeName)) + 8;
            $this->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
            $this->Rect($left + 6, $y + 5, $badgeW, 7, 'F');
            $this->SetXY($left + 6, $y + 5);
            $this->SetFont('Helvetica', 'B', 6);
            $this->SetTextColor(255, 255, 255);
            $this->Cell($badgeW, 7, $this->u($storeName), 0, 1, 'C');

            // Country tag
            $cx = $left + 6 + $badgeW + 4;
            $this->SetFillColor(45, 45, 45);
            $this->Rect($cx, $y + 5, 12, 7, 'F');
            $this->SetXY($cx, $y + 5);
            $this->SetFont('Helvetica', '', 6);
            $this->SetTextColor(180, 180, 180);
            $this->Cell(12, 7, $country, 0, 1, 'C');

            // Product name
            $nameMaxW = $cardW - 70;
            $this->SetXY($left + 10, $y + 15);
            $this->SetFont('Helvetica', 'B', 9);
            $this->SetTextColor(240, 240, 240);
            $nameEnc = $this->u($data['name']);
            if ($this->GetStringWidth($nameEnc) > $nameMaxW) {
                while ($this->GetStringWidth($nameEnc . '...') > $nameMaxW && strlen($nameEnc) > 3) {
                    $nameEnc = substr($nameEnc, 0, -1);
                }
                $nameEnc .= '...';
            }
            $this->Cell($nameMaxW, 5, $nameEnc, 0, 1);

            $rowY = $y + 20;

            // Brand
            if ($hasBrand) {
                $this->SetX($left + 10);
                $this->SetFont('Helvetica', '', 7);
                $this->SetTextColor(160, 160, 160);
                $this->Cell($nameMaxW, 4, $this->u($brand), 0, 1);
                $rowY += 5;
            }

            // Price row
            $this->SetXY($left + 10, $rowY);
            $this->SetFont('Helvetica', 'B', 11);
            $this->SetTextColor(255, 214, 0);
            $this->Cell(32, 6, "\x80 " . $price, 0, 0);
            $this->SetFont('Helvetica', '', 6);
            $this->SetTextColor(160, 160, 160);
            $this->Cell(20, 6, $this->u('Laagste prijs'), 0, 1);
            $rowY += 7;

            // Unit price (green)
            if ($hasUnit) {
                $this->SetX($left + 10);
                $this->SetFont('Helvetica', '', 7);
                $this->SetTextColor(129, 199, 132);
                $per100 = $unitPrice / 10;
                $unitLower = mb_strtolower($unitSize ?? '');
                $isLiquid = str_contains($unitLower, 'l') && !str_contains($unitLower, 'cl');
                $unitLabel = $isLiquid ? '/100ml' : '/100g';
                $unitStr = "\x80 " . number_format($per100, 2, ',', '.') . $unitLabel;
                if ($unitSize) {
                    $unitStr .= $this->u(' (' . $unitSize . ')');
                }
                $this->Cell(50, 4, $unitStr, 0, 1);
                $rowY += 5;
            }

            // Other stores
            if ($hasOthers) {
                $this->SetX($left + 10);
                $this->SetFont('Helvetica', '', 6.5);
                $this->SetTextColor(130, 130, 130);
                $others = [];
                for ($i = 1; $i < min(4, $storeCount); $i++) {
                    $s = $data['stores'][$i];
                    $p = number_format((float)$s['price'], 2, ',', '.');
                    $others[] = $this->u($s['store_name']) . ' ' . "\x80" . $p;
                }
                $this->Cell($cardW - 30, 4, $this->u('Ook: ') . implode(' | ', $others), 0, 1);
                $rowY += 5;
            }
        } else {
            // Not found — show "Alle" badge + product name
            $nameMaxW = $cardW - 60;
            // "Alle" badge (green, all supermarkets)
            $badgeText = 'Alle';
            $badgeW = $this->GetStringWidth($this->u($badgeText)) + 10;
            $this->SetFillColor(100, 180, 100);
            $this->Rect($left + 6, $y + 5, $badgeW, 7, 'F');
            $this->SetXY($left + 6, $y + 5);
            $this->SetFont('Helvetica', 'B', 6);
            $this->SetTextColor(255, 255, 255);
            $this->Cell($badgeW, 7, $this->u($badgeText), 0, 1, 'C');

            // Product name
            $this->SetXY($left + 12, $y + 15);
            $this->SetFont('Helvetica', 'B', 9);
            $this->SetTextColor(240, 240, 240);
            $nameEnc = $this->u($data['name']);
            if ($this->GetStringWidth($nameEnc) > $nameMaxW) {
                while ($this->GetStringWidth($nameEnc . '...') > $nameMaxW && strlen($nameEnc) > 3) {
                    $nameEnc = substr($nameEnc, 0, -1);
                }
                $nameEnc .= '...';
            }
            $this->Cell($nameMaxW, 5, $nameEnc, 0, 1);
        }

        // Store count at bottom
        if ($storeCount > 0) {
            $this->SetXY($left + 10, $y + $cardH - 5);
            $this->SetFont('Helvetica', '', 6);
            $this->SetTextColor(140, 140, 140);
            $this->Cell(30, 4, $storeCount . ' ' . ($storeCount == 1 ? 'winkel' : 'winkels'), 0, 1);
        }

        $this->SetY($y + $cardH + 4);
    }
}

// Build store color map
$storeColorMap = [];
$storeStmt = $pdo->query("SELECT name FROM stores WHERE active = 1");
while ($row = $storeStmt->fetch()) {
    $storeColorMap[$row['name']] = getStoreColor($row['name']);
}

$pdf = new ShoppingListPdf($storeColorMap);
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// Summary bar
$summaryColor = $foundCount > 0 ? [255, 214, 0] : [200, 80, 80];
$pdf->SetFillColor(23, 23, 23);
$pdf->Rect(10, $pdf->GetY(), 190, 10, 'F');
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetTextColor($summaryColor[0], $summaryColor[1], $summaryColor[2]);
$pdf->SetXY(16, $pdf->GetY() + 2);
$summaryText = $pdf->u("$foundCount van de $totalCount producten gevonden in huidige folders");
$pdf->Cell(0, 6, $summaryText, 0, 1);
$pdf->Ln(6);

// Products
foreach ($productData as $pd) {
    $pdf->ProductCard($pd);
}

$pdfData = $pdf->Output('S');

// ── Build HTML email ──
$lines = [];
foreach ($productData as $pd) {
    if ($pd['found']) {
        $lines[] = "☐ <b>" . htmlspecialchars($pd['name']) . "</b>";
        foreach ($pd['stores'] as $r) {
            $unitInfo = '';
            if ($r['unit_price'] && $r['unit_size']) {
                $unitInfo = ' (' . formatPrice((float)$r['unit_price']) . '/' . htmlspecialchars($r['unit_size']) . ')';
            }
            $scraped = date('d-m', strtotime($r['scraped_at']));
            $lines[] = "&nbsp;&nbsp;🛒 " . htmlspecialchars($r['store_name']) . " (" . $r['country'] . "): <b>" . formatPrice((float)$r['price']) . "</b>" . $unitInfo . " <span style='color:#888'>[folder " . $scraped . "]</span>";
        }
    } else {
        $lines[] = "☐ <b>" . htmlspecialchars($pd['name']) . "</b> — <span style='color:#c84'>Niet gevonden</span>";
    }
}

$intro = "Je boodschappenlijstje is gecontroleerd!<br><br>
Van de <b>{$totalCount}</b> producten zijn er <b>{$foundCount}</b> gevonden in de huidige folders.<br>
Bijgevoegd vind je een PDF om af te printen met vinkjes.<br><br>";

$html = "<!DOCTYPE html><html><head><meta charset='utf-8'><style>
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#111;color:#eee;padding:32px;max-width:600px}
h1{color:#ffd600;font-size:20px;margin-bottom:8px}
.intro{color:#aaa;font-size:14px;margin-bottom:24px}
.line{padding:10px 14px;margin:6px 0;background:#1a1a1a;border-radius:8px;font-size:13px;line-height:1.6}
.checked{color:#81c784;margin-right:6px}
.unchecked{color:#888;margin-right:6px}
.footer{margin-top:32px;padding-top:16px;border-top:1px solid #333;font-size:11px;color:#666}
</style></head><body>
<h1>Folders Vergelijker — Boodschappenlijstje</h1>
<p class='intro'>" . $intro . "</p>
" . implode("<br>", $lines) . "
<p class='footer'>Dit is een automatisch gegenereerd bericht van Folders Vergelijker</p>
</body></html>";

// ── Send via Gmail SMTP ──
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'jasper.v.kalsbeek@gmail.com';
    $mail->Password   = 'epqk nagz zgze lbla';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom('jasper.v.kalsbeek@gmail.com', 'Folders Vergelijker');
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Jouw boodschappenlijstje - ' . $foundCount . '/' . $totalCount . ' gevonden in folders';
    $mail->Body    = $html;

    // Attach PDF
    $mail->addStringAttachment($pdfData, 'boodschappenlijstje.pdf', 'base64', 'application/pdf');

    $mail->send();

    // Mark as sent
    $pdo->prepare("UPDATE shopping_lists SET sent_at = NOW() WHERE id = ?")->execute([$listId]);

    echo json_encode(['success' => true, 'found' => $foundCount, 'total' => $totalCount]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Email kon niet worden verzonden: ' . $e->getMessage()]);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Fout: ' . $e->getMessage()]);
}
