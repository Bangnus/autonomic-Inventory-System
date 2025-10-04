<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php'; // your database connection

$pdo = get_pdo();
$transaction_id = (int)($_GET['id'] ?? 0);

// ดึง transaction ปัจจุบัน
$stmt = $pdo->prepare("
    SELECT t.*, u.name AS user_name, p.name AS product_name
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN products p ON t.product_id = p.id
    WHERE t.id = ?
");
$stmt->execute([$transaction_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) die("Transaction not found");

// ดึง transactions วันเดียวกัน
$stmt_all = $pdo->prepare("
    SELECT t.*, u.name AS user_name, p.name AS product_name
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN products p ON t.product_id = p.id
    WHERE t.date = ?
");
$stmt_all->execute([$transaction['date']]);
$transactions_same_date = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

// ตั้งค่า Twig
$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);

// render HTML จาก template
$html = $twig->render('transaction_template.html', [
    'logo_path' => __DIR__ . '/assets/images/logo_autonomic.jpeg',
    'date' => $transaction['date'],
    'transactions' => $transactions_same_date
]);

// สร้าง PDF
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'default_font' => 'thsarabun',
    'fontDir' => array_merge([__DIR__ . '/fonts'], (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir']),
    'fontdata' => array_merge((new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'], [
        'thsarabun' => [
            'R' => 'THSarabun.ttf',
            'B' => 'THSarabun-Bold.ttf',
            'I' => 'THSarabun-Italic.ttf',
            'BI' => 'THSarabun-BoldItalic.ttf'
        ]
    ])
]);





$mpdf->WriteHTML($html);

// ตั้งชื่อไฟล์
$pdf_filename = 'transactions_' . $transaction['date'] . '.pdf';

// ส่ง PDF ไป browser
while (ob_get_level()) ob_end_clean();
$mpdf->Output($pdf_filename, \Mpdf\Output\Destination::INLINE);
