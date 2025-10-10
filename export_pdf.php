<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php'; // your database connection

$pdo = get_pdo();
$transaction_id = (int)($_GET['id'] ?? 0);

// ดึง transaction ปัจจุบัน
$stmt = $pdo->prepare("
    SELECT 
        t.*, 
        u.name AS user_name,
        u.signature_base64 AS user_signature,
        p.name AS product_name,
        (SELECT signature_base64 FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1) AS admin_signature,
        (SELECT name FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1) AS admin_name
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN products p ON t.product_id = p.id
    WHERE t.id = ?
");
$stmt->execute([$transaction_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) die("Transaction not found");

// ดึง transactions วันเดียวกันและประเภทเดียวกัน
$stmt_all = $pdo->prepare("
    SELECT t.*, u.name AS user_name, p.code AS product_code, p.name AS product_name, 
           p.serial AS product_serial, p.model AS product_model, p.stock_quantity AS product_stock
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN products p ON t.product_id = p.id
    WHERE t.date = ? 
    AND t.user_id = ? 
    AND t.type = ?
    ORDER BY t.id
");
$stmt_all->execute([$transaction['date'], $transaction['user_id'], $transaction['type']]);
$transactions_same_date = $stmt_all->fetchAll(PDO::FETCH_ASSOC);



// ตั้งค่า Twig
$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);


// แปลงวันที่เป็นรูปแบบไทย
$timestamp = strtotime($transaction['date']);
$thai_year = date('Y', $timestamp) + 543;
$thai_date = date('j/n/', $timestamp) . $thai_year;

// เตรียมข้อมูลสำหรับ template
$document_type = $transaction['type'] === 'request' ? 'ใบเบิกอุปกรณ์' : 'ใบคืนอุปกรณ์';

// render HTML จาก template
$html = $twig->render('transaction_template.html', [
    'logo_path' => __DIR__ . '/assets/images/logo_autonomic.jpeg',
    'date' => $thai_date,
    'transactions' => $transactions_same_date,
    'transaction' => $transaction,
    'document_type' => $document_type,
    'is_request' => $transaction['type'] === 'request'
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
$type_text = $transaction['type'] === 'request' ? 'request' : 'return';
$pdf_filename = $type_text . '_' . $transaction['id'] . '_' . $transaction['date'] . '.pdf';

// ส่ง PDF ไป browser
while (ob_get_level()) ob_end_clean();
$mpdf->Output($pdf_filename, \Mpdf\Output\Destination::INLINE);
