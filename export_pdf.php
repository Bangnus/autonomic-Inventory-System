<?php
// เปิด error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ล้าง buffer และเริ่ม session
if (ob_get_level()) ob_end_clean();
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) die("กรุณาเข้าสู่ระบบก่อน");

// include HTML2PDF
require_once __DIR__ . '/vendor/autoload.php';

// include db & auth
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';

$debug = isset($_GET['debug']) && $_GET['debug'] === 'true';
require_login();

$user = $_SESSION['user'];
$pdo = get_pdo();

// ดึงค่า transaction
$type = $_GET['type'] ?? '';
$transaction_id = (int)($_GET['id'] ?? 0);

if ($debug) {
    echo "transaction_id = $transaction_id<br>";
    echo "type = '$type'<br>";
}

// Query transaction
$sql = "
SELECT t.*, u.name AS user_name, u.email AS user_email,
       p.name AS product_name, p.code AS product_code, p.serial, p.model
FROM transactions t
LEFT JOIN users u ON t.user_id = u.id
LEFT JOIN products p ON t.product_id = p.id
WHERE t.id = ? AND t.type = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$transaction_id, $type]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if ($debug) {
    echo "<pre>" . print_r($transaction, true) . "</pre>";
    exit;
}

if (!$transaction) {
    http_response_code(404);
    die('Transaction not found');
}

// สร้าง HTML content แบบง่าย ๆ ก่อน
$html = '<page>
<div style="text-align: center; margin-bottom: 20px;">
    <h1>INVENTORY MANAGEMENT SYSTEM</h1>
</div>
<div style="margin-bottom: 10px;">
    <b>Transaction ID:</b> ' . $transaction['id'] . '
</div>
<div style="margin-bottom: 10px;">
    <b>Type:</b> ' . ucfirst($transaction['type']) . '
</div>
<div style="margin-bottom: 10px;">
    <b>User:</b> ' . htmlspecialchars($transaction['user_name']) . '
</div>
<div style="margin-bottom: 10px;">
    <b>Product:</b> ' . htmlspecialchars($transaction['product_name']) . '
</div>
<div style="margin-bottom: 10px;">
    <b>Quantity:</b> ' . $transaction['quantity'] . '
</div>
</page>';

// Add debug logging
if ($debug) {
    echo "HTML Content:<br><pre>" . htmlspecialchars($html) . "</pre>";
}

// Check system requirements
if ($debug) {
    echo "Checking system requirements...<br>";
    echo "PHP Version: " . phpversion() . "<br>";
    if (extension_loaded('gd')) {
        echo "GD extension: Installed (Version: " . gd_info()['GD Version'] . ")<br>";
    } else {
        die("Error: GD extension is required but not installed");
    }
    echo "Memory limit: " . ini_get('memory_limit') . "<br>";
}

try {
    if ($debug) {
        echo "Creating HTML2PDF instance...<br>";
    }

    // สร้าง PDF ใช้ภาษา en แทน th
    $pdf = new Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'en', true, 'UTF-8');
    $pdf->pdf->AddFont('thsarabunnew', '', 'thsarabunnew.php');
    $pdf->setDefaultFont('thsarabunnew');

    if ($debug) {
        echo "Setting default font...<br>";
        echo "Current working directory: " . getcwd() . "<br>";
    }

    // ตรวจสอบและโหลด font
    $fontPath = __DIR__ . '/vendor/tecnickcom/tcpdf/fonts/THSarabun.ttf';
    if (!file_exists($fontPath)) {
        if ($debug) {
            echo "Warning: THSarabun font not found at: $fontPath<br>";
            echo "Falling back to default font<br>";
        }
    } else {
        if ($debug) {
            echo "Font file found: $fontPath<br>";
        }
    }

    // Set some options - using default font if THSarabun not available
    if (file_exists($fontPath)) {
        $pdf->setDefaultFont('THSarabun');
    }

    if ($debug) {
        echo "Writing HTML content...<br>";
        echo "Content length: " . strlen($html) . " bytes<br>";
    }

    // Write content
    $pdf->writeHTML($html);

    if ($debug) {
        echo "Clearing output buffer...<br>";
    }

    if ($debug) {
        // Save to file for debugging
        $debugFile = __DIR__ . '/debug.pdf';
        echo "Saving PDF to: " . $debugFile . "<br>";
        $pdf->Output($debugFile, 'F');
        echo "PDF saved successfully. File size: " . filesize($debugFile) . " bytes<br>";
        exit;
    }

    // สร้างชื่อไฟล์
    $filename = sprintf(
        '%s_%d_%s.pdf',
        $transaction['type'],
        $transaction['id'],
        date('Y-m-d_H-i-s')
    );

    // Update filename in database
    $stmt = $pdo->prepare("UPDATE transactions SET pdf_filename = ? WHERE id = ?");
    $stmt->execute([$filename, $transaction_id]);

    // ล้าง buffer และตั้งค่า headers
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // ส่ง PDF
    $pdf->Output($filename, 'I');
} catch (Exception $e) {
    if ($debug) {
        echo "Error generating PDF:<br>";
        echo "<pre>" . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
        echo "\nPHP Version: " . phpversion();
        echo "\nMemory Usage: " . memory_get_usage(true) / 1024 / 1024 . " MB";
        echo "\nPeak Memory Usage: " . memory_get_peak_usage(true) / 1024 / 1024 . " MB";
    } else {
        http_response_code(500);
        die("Error generating PDF");
    }
}
