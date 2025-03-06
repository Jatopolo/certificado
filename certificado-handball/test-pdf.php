<?php
require_once 'vendor/autoload.php';

try {
    $mpdf = new \Mpdf\Mpdf();
    $mpdf->WriteHTML('<h1>Prueba de Generación de PDF</h1><p>Si ves este mensaje, mPDF funciona correctamente.</p>');
    $mpdf->Output();
} catch (Exception $e) {
    echo 'Error generando PDF: ' . $e->getMessage();
}
?>
