<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/fpdf/fpdf.php';

require_login();

$user = $_SESSION['user'];
$pdo = get_pdo();

// Get transaction details
$type = $_GET['type'] ?? '';
$transaction_id = (int)($_GET['id'] ?? 0);

if (!in_array($type, ['request', 'return']) || !$transaction_id) {
    http_response_code(400);
    echo 'Invalid parameters.';
    exit;
}

// Get transaction details
$sql = "
    SELECT t.*, u.name as user_name, u.email as user_email,
           p.name as product_name, p.code as product_code, p.serial, p.model
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    JOIN products p ON t.product_id = p.id
    WHERE t.id = ? AND t.type = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$transaction_id, $type]);
$transaction = $stmt->fetch();

if (!$transaction) {
    http_response_code(404);
    echo 'Transaction not found.';
    exit;
}

// Check if user can access this transaction (admin or own transaction)
if ($user['role'] !== 'admin' && $transaction['user_id'] !== $user['id']) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

// Create PDF
class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'Inventory Management System', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, strtoupper($this->transactionType) . ' DOCUMENT', 0, 1, 'C');
        $this->Ln(5);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Generated on ' . date('Y-m-d H:i:s'), 0, 0, 'C');
    }
    
    function SetTransactionType($type) {
        $this->transactionType = $type;
    }
}

$pdf = new PDF();
$pdf->SetTransactionType($type);
$pdf->AddPage();

// Transaction details
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Transaction Details', 0, 1);
$pdf->SetFont('Arial', '', 10);

$pdf->Cell(40, 8, 'Transaction ID:', 0, 0);
$pdf->Cell(0, 8, '#' . $transaction['id'], 0, 1);

$pdf->Cell(40, 8, 'Date:', 0, 0);
$pdf->Cell(0, 8, date('F j, Y g:i A', strtotime($transaction['created_at'])), 0, 1);

$pdf->Cell(40, 8, 'Type:', 0, 0);
$pdf->Cell(0, 8, ucfirst($transaction['type']), 0, 1);

$pdf->Cell(40, 8, 'User:', 0, 0);
$pdf->Cell(0, 8, $transaction['user_name'] . ' (' . $transaction['user_email'] . ')', 0, 1);

$pdf->Ln(5);

// Product details
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Product Information', 0, 1);
$pdf->SetFont('Arial', '', 10);

$pdf->Cell(40, 8, 'Product Name:', 0, 0);
$pdf->Cell(0, 8, $transaction['product_name'], 0, 1);

$pdf->Cell(40, 8, 'Product Code:', 0, 0);
$pdf->Cell(0, 8, $transaction['product_code'], 0, 1);

if ($transaction['serial']) {
    $pdf->Cell(40, 8, 'Serial Number:', 0, 0);
    $pdf->Cell(0, 8, $transaction['serial'], 0, 1);
}

if ($transaction['model']) {
    $pdf->Cell(40, 8, 'Model:', 0, 0);
    $pdf->Cell(0, 8, $transaction['model'], 0, 1);
}

$pdf->Cell(40, 8, 'Quantity:', 0, 0);
$pdf->Cell(0, 8, number_format($transaction['quantity']), 0, 1);

$pdf->Ln(10);

// Signature section
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Digital Signature', 0, 1);

if ($transaction['signature_base64']) {
    // Decode base64 signature
    $signatureData = $transaction['signature_base64'];
    
    // Create a temporary file for the signature image
    $tempFile = tempnam(sys_get_temp_dir(), 'signature_');
    $imageData = base64_decode(preg_replace('/^data:image\/png;base64,/', '', $signatureData));
    file_put_contents($tempFile, $imageData);
    
    // Get image dimensions
    $imageInfo = getimagesize($tempFile);
    if ($imageInfo) {
        $imageWidth = $imageInfo[0];
        $imageHeight = $imageInfo[1];
        
        // Calculate size to fit in PDF (max width 120mm)
        $maxWidth = 120;
        $maxHeight = 40;
        
        $scaleX = $maxWidth / $imageWidth;
        $scaleY = $maxHeight / $imageHeight;
        $scale = min($scaleX, $scaleY);
        
        $displayWidth = $imageWidth * $scale;
        $displayHeight = $imageHeight * $scale;
        
        // Add image to PDF
        $pdf->Image($tempFile, null, null, $displayWidth, $displayHeight);
    }
    
    // Clean up temporary file
    unlink($tempFile);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 8, 'No signature available', 0, 1);
}

$pdf->Ln(10);

// Terms and conditions
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 8, 'Terms and Conditions:', 0, 1);
$pdf->SetFont('Arial', '', 9);

if ($type === 'request') {
    $terms = [
        '1. The requester is responsible for the proper use and care of the requested items.',
        '2. Items must be returned in the same condition as received.',
        '3. Any damage or loss must be reported immediately.',
        '4. This document serves as proof of request and must be kept for records.',
        '5. Stock has been automatically deducted from inventory.'
    ];
} else {
    $terms = [
        '1. The returned items have been received and added back to inventory.',
        '2. Items should be in good working condition.',
        '3. Any discrepancies should be reported immediately.',
        '4. This document serves as proof of return and must be kept for records.',
        '5. Stock has been automatically added back to inventory.'
    ];
}

foreach ($terms as $term) {
    $pdf->Cell(0, 6, $term, 0, 1);
}

// Generate filename
$filename = $type . '_' . $transaction_id . '_' . date('Y-m-d_H-i-s') . '.pdf';

// Output PDF
$pdf->Output('D', $filename); // 'D' for download
?>