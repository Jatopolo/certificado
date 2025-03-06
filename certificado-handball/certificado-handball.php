<?php
/*
Plugin Name: Certificado Handball
Description: Genera certificados en PDF para jugadores federados usando un archivo CSV local.
Version: 1.6
Author: Javier (Jato)
*/

// Cargar DomPDF
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

if (!defined('ABSPATH')) {
    exit; // Evita el acceso directo.
}

/* -----------------------------------------------------------------
   1. Función para escribir logs en un archivo
----------------------------------------------------------------- */
function ch_log($mensaje) {
    $logfile = plugin_dir_path(__FILE__) . 'log-certificado.txt';
    file_put_contents($logfile, date('Y-m-d H:i:s') . " - " . $mensaje . "\n", FILE_APPEND);
}

/* -----------------------------------------------------------------
   2. Shortcode y Formulario para Solicitar el Certificado
----------------------------------------------------------------- */
function ch_certificado_form_shortcode() {
    ob_start();
    ?>
    <form method="post">
        <label for="dato">Número de Carnet o DNI:</label>
        <input type="text" name="dato" id="dato" required>
        <input type="submit" name="ch_submit" value="Generar Certificado">
    </form>
    <?php

    if (isset($_POST['ch_submit']) && !empty($_POST['dato'])) {
        $dato = sanitize_text_field($_POST['dato']);
        ch_log("Formulario recibido con dato: " . $dato);

        $registro = ch_buscar_registro($dato);

        if (!$registro) {
            ch_log("Jugador no encontrado.");
            echo '<p>No se encontró el jugador con ese número de carnet o DNI.</p>';
            return ob_get_clean();
        }

        ch_generate_pdf_certificado($registro['club'], $registro['nombre_completo'], $registro['carnet'], $registro['dni']);
    }

    return ob_get_clean();
}
add_shortcode('certificado_handball', 'ch_certificado_form_shortcode');

/* -----------------------------------------------------------------
   3. Leer los Datos desde el Archivo CSV
----------------------------------------------------------------- */
function ch_get_local_data() {
    $filePath = plugin_dir_path(__FILE__) . 'data/jugadores.csv';

    if (!file_exists($filePath)) {
        ch_log("Error: El archivo CSV no existe en " . $filePath);
        return false;
    }

    ch_log("Intentando leer el archivo CSV...");

    $data = [];
    if (($handle = fopen($filePath, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ","); // Leer la primera fila (encabezados)

        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $data[] = array_combine($headers, $row);
        }
        fclose($handle);
    }

    ch_log("CSV leído con éxito. Total de registros: " . count($data));
    return $data;
}

/* -----------------------------------------------------------------
   4. Buscar Jugador en el Archivo CSV
----------------------------------------------------------------- */
function ch_buscar_registro($dato) {
    ch_log("Buscando el jugador con carnet o DNI: " . $dato);

    $values = ch_get_local_data();
    if (!$values || count($values) == 0) {
        ch_log("Error: No se encontraron datos en el CSV.");
        return false;
    }

    foreach ($values as $row) {
        if (isset($row['Carnet']) && isset($row['DNI'])) {
            if ($row['Carnet'] == $dato || $row['DNI'] == $dato) {
                ch_log("Jugador encontrado: " . $row['Apellido'] . " " . $row['Nombre']);
                return array(
                    'club'   => $row['Institución'],
                    'nombre_completo' => $row['Apellido'] . " " . $row['Nombre'],
                    'carnet' => $row['Carnet'],
                    'dni'    => $row['DNI']
                );
            }
        }
    }

    ch_log("Jugador no encontrado.");
    return false;
}

/* -----------------------------------------------------------------
   5. Generar el Certificado en PDF con Dompdf
----------------------------------------------------------------- */
function ch_generate_pdf_certificado($club, $nombreJugador, $carnet, $dni) {
    ch_log("Generando PDF para: " . $nombreJugador);

    require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

    $fecha = date('d/m/Y');

    // URLs de imágenes del membrete y firma
    $membrete = 'https://amebal.com/nueva/wp-content/plugins/certificado-handball/images/membrete.png';
    $firma = 'https://amebal.com/nueva/wp-content/plugins/certificado-handball/images/firma.png';

    // Plantilla HTML del Certificado
    $htmlCert = '
    <html>
      <head>
         <meta charset="UTF-8">
         <style>
            body { font-family: Arial, sans-serif; margin: 40px; text-align: center; }
            header img { width: 100%; max-height: 150px; }
            h1 { margin-top: 20px; font-size: 22px; }
            .contenido { margin-top: 30px; font-size: 16px; line-height: 1.5; text-align: justify; }
            .firma { margin-top: 40px; text-align: right; }
            .firma img { width: 200px; }
         </style>
      </head>
      <body>
         <header>
            <img src="' . $membrete . '" alt="Membrete">
         </header>
         <h1>CERTIFICADO JUGADOR/A FEDERADO</h1>
         <div class="contenido">
            <p>La Asociación Mendocina de Balonmano deja constar que <strong>' . esc_html($nombreJugador) . '</strong>, con DNI Nº <strong>' . esc_html($dni) . '</strong>, se encontraba como jugador(a) federado(a) en dicha asociación, perteneciente al club <strong>' . esc_html($club) . '</strong>.</p>
            <p>Se extiende el presente CERTIFICADO para ser presentado ante quien lo requiera, a los <strong>' . esc_html($fecha) . '</strong>.</p>
         </div>
         <div class="firma">
            <img src="' . $firma . '" alt="Firma Institucional">
         </div>
      </body>
    </html>';

    // Generar PDF con DomPDF
    $dompdf = new Dompdf\Dompdf();
    $dompdf->loadHtml($htmlCert);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Guardar el PDF en la carpeta del plugin
    $outputPath = plugin_dir_path(__FILE__) . 'certificados/certificado_' . $dni . '.pdf';
    file_put_contents($outputPath, $dompdf->output());

    ch_log("PDF guardado en: " . $outputPath);

    // Enviar PDF al navegador
    $dompdf->stream('certificado.pdf', array("Attachment" => false));
    exit;
}
