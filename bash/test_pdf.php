<?php
require 'vendor/autoload.php';

use Dompdf\Dompdf;

// Instancia de Dompdf
$dompdf = new Dompdf();

// Cargar contenido HTML
$html = '<h1>Hola Mundo!</h1><p>Este es un PDF generado con DomPDF.</p>';
$dompdf->loadHtml($html);

// Configurar el tamaño del papel (opcional)
$dompdf->setPaper('A4', 'portrait');

// Renderizar el PDF
$dompdf->render();

// Enviar el PDF al navegador
$dompdf->stream("test_pdf.pdf", array("Attachment" => false)); // Esto hace que se muestre en el navegador
?>
