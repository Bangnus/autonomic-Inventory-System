<?php
require __DIR__ . '/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;

$html2pdf = new Html2Pdf('P', 'A4', 'en');

// เพิ่มฟอนต์ภาษาไทย
$html2pdf->pdf->AddFont('thsarabunnew', '', 'thsarabunnew.php');
$html2pdf->setDefaultFont('thsarabunnew');

// HTML ตัวอย่าง
$html = <<<EOD
<style>
    body { font-family: thsarabunnew; font-size: 16pt; }
    h1 { font-family: thsarabunnew; font-size: 22pt; text-align: center; }
    p  { font-family: thsarabunnew; font-size: 16pt; }
</style>

<h1>สวัสดีชาวโลก</h1>
<p>นี่คือข้อความทดสอบภาษาไทย</p>
<p>ก ข ฃ ค ฅ ฆ ง จ ฉ ช ซ ฌ ญ ฎ ฏ ฐ ฑ ฒ ณ ด ต ถ ท ธ น บ ป ผ ฝ พ ฟ ภ ม ย ร ล ว ศ ษ ส ห ฬ อ ฮ</p>
<p>สระ: ะ า ิ ี ึ ื ุ ู เ แ โ ใ ไ ๅ ํ</p>
<p>วรรณยุกต์: ่ ้ ๊ ๋</p>
<p>ตัวเลขไทย: ๐ ๑ ๒ ๓ ๔ ๕ ๖ ๗ ๘ ๙</p>
EOD;

// สร้าง PDF
$html2pdf->writeHTML($html);
$html2pdf->output('thai_test.pdf', 'I');
