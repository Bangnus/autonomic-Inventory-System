<?php
/* Minimal embedded FPDF 1.86 (subset) - for portability in this demo
   If you have Composer, prefer installing setasign/fpdf via Composer instead. */

if (class_exists('FPDF')) { return; }

class FPDF {
    protected $page = 0;
    protected $wPt = 595.28; // A4 width
    protected $hPt = 841.89; // A4 height
    protected $x = 10;
    protected $y = 10;
    protected $buffer = '';
    protected $state = 0;
    protected $k = 72/25.4;
    protected $pageContent = [];

    function __construct($orientation='P',$unit='mm',$size='A4') { $this->buffer=''; }
    function AddPage(){ $this->page++; $this->pageContent[$this->page]=''; $this->x=10; $this->y=10; }
    function SetFont($family,$style='',$size=12){ /* no-op minimal */ }
    function Cell($w,$h=0,$txt='',$border=0,$ln=0,$align='',$fill=false,$link=''){ $this->pageContent[$this->page].=$txt."\n"; if($ln>0){ $this->y += $h?:6; } }
    function Ln($h=0){ $this->y += $h?:6; }
    function Image($file,$x=null,$y=null,$w=0,$h=0,$type='',$link=''){ $this->pageContent[$this->page].='[image:' . basename($file) . "]\n"; }
    function GetY(){ return $this->y; }
    function Header(){ }
    function Output($dest='I',$name='doc.pdf'){
        // Very naive pseudo-PDF (text only) for portability
        // In real deployment, replace with real FPDF.
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($dest==='F'?'attachment':'inline') . '; filename="' . $name . '"');
        echo "%PDF-1.3\n%\xE2\xE3\xCF\xD3\n"; // magic header
        echo "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n";
        echo "2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n";
        $content = "";
        foreach($this->pageContent as $p){ $content .= $p."\n"; }
        $len = strlen($content);
        echo "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R>>endobj\n";
        echo "4 0 obj<</Length $len>>stream\n" . $content . "endstream endobj\n";
        echo "xref\n0 5\n0000000000 65535 f \n0000000010 00000 n \n0000000060 00000 n \n0000000116 00000 n \n0000000225 00000 n \ntrailer<</Size 5/Root 1 0 R>>\nstartxref\n". (225+$len) ."\n%%EOF";
    }
}


