<?php
/*
Plugin Name: Certificado Handball
Description: Genera certificados en PDF para jugadores federados de handball.
Version: 1.0
Author: Javier (Jato)
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

*/

if (!defined('ABSPATH')) {
    exit; // Evita el acceso directo.
}

/* ---------------------------
   1. Creación del Shortcode y Formulario
---------------------------- */
function ch_certificado_form_shortcode() {
    // Muestra el formulario
    ob_start();
    ?>
    <form method="post">
        <label for="carnet">Número de Carnet:</label>
        <input type="text" name="carnet" id="carnet" required>
        <input type="submit" name="ch_submit" value="Generar Certificado">
    </form>
    <?php

    // Procesa el formulario si se envía
    if (isset($_POST['ch_submit']) && !empty($_POST['carnet'])) {
        $carnet = sanitize_text_field($_POST['carnet']);
        ch_process_certificado($carnet);
    }

    return ob_get_clean();
}
add_shortcode('certificado_handball', 'ch_certificado_form_shortcode');

/* ---------------------------
   2. Función para procesar la consulta y extraer datos
---------------------------- */
function ch_process_certificado($carnet) {
    // Construye la URL del sistema de control
    $url = 'http://amebal.com.ar/control.php?n=' . urlencode($carnet);
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        echo '<p>Error al conectar con el sistema de control.</p>';
        return;
    }

    $html = wp_remote_retrieve_body($response);

    // Parsear el HTML recibido
    $dom = new DOMDocument();
    libxml_use_internal_errors(true); // Suprime errores por HTML mal formado
    $dom->loadHTML($html);
    libxml_clear_errors();

    // Extraer el texto completo (en muchos casos la página es simple)
    $content = $dom->textContent;

    // Verifica si aparece "HABILITADO" en el contenido
    if (strpos($content, 'HABILITADO') === false) {
        echo '<p>El jugador no está habilitado o no se encontró información.</p>';
        return;
    }

    // EXTRAER LOS DATOS:
    // Suponiendo que la página tiene un formato similar a:
    // CLUB ETIEC
    // MarÃ­a Emilia Aciar
    // Carnet: J-05299 D.N.I.: 51090209
    // HABILITADO
    //
    // Se puede usar una expresión regular para capturar la información:
    $pattern = '/^(.*)\n(.*)\nCarnet:\s*(.*)\s*D\.N\.I\.\:\s*(.*)\n/i';
    if (preg_match($pattern, $content, $matches)) {
        $club          = trim($matches[1]);
        $nombreJugador = trim($matches[2]);
        $carnetExtraido = trim($matches[3]);
        $dni           = trim($matches[4]);
    } else {
        echo '<p>No se pudo extraer la información necesaria.</p>';
        return;
    }

    // Una vez extraída la información, genera el PDF
    ch_generate_pdf_certificado($club, $nombreJugador, $carnetExtraido, $dni);
}

/* ---------------------------
   3. Función para generar el certificado en PDF
---------------------------- */
function ch_generate_pdf_certificado($club, $nombreJugador, $carnet, $dni) {
    // Verifica que la librería mPDF esté disponible.
    // Es recomendable instalar mPDF con Composer: en la carpeta del plugin, ejecuta
    // "composer require mpdf/mpdf" y asegúrate de incluir el autoload.
    if (!class_exists('Mpdf\Mpdf')) {
        // Incluye el autoload de Composer (ajusta la ruta si es necesario)
        if (file_exists(plugin_dir_path(__FILE__) . 'vendor/autoload.php')) {
            require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
        } else {
            echo '<p>La librería mPDF no está instalada. Instálala para generar el PDF.</p>';
            return;
        }
    }

    // Crea el contenido HTML del certificado.
    $htmlCert = '
    <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                h1 { text-align: center; }
                .info { margin: 20px; }
                .firma { margin-top: 40px; text-align: right; }
            </style>
        </head>
        <body>
            <h1>Certificado de Jugador Federado</h1>
            <div class="info">
                <p><strong>Club:</strong> ' . esc_html($club) . '</p>
                <p><strong>Nombre:</strong> ' . esc_html($nombreJugador) . '</p>
                <p><strong>Carnet:</strong> ' . esc_html($carnet) . '</p>
                <p><strong>DNI:</strong> ' . esc_html($dni) . '</p>
                <p><strong>Estado:</strong> HABILITADO</p>
            </div>
            <div class="firma">
                <p>Firma institucional:</p>
                <img src="' . plugin_dir_url(__FILE__) . 'images/firma.png" alt="Firma Institucional" width="200">
            </div>
        </body>
    </html>';

    // Genera el PDF y envíalo al navegador.
    try {
        $mpdf = new \Mpdf\Mpdf();
        $mpdf->WriteHTML($htmlCert);
        $mpdf->Output('certificado.pdf', 'I'); // "I" para mostrar en el navegador
        exit; // Termina la ejecución después de mostrar el PDF.
    } catch (Exception $e) {
        echo '<p>Error al generar el PDF: ' . $e->getMessage() . '</p>';
    }
}
