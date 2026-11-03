<?php
// Recibo de pago en PDF (librería FPDF).
// Solo lo puede ver el DUEÑO de la venta o un admin, y solo si la venta está pagada.
//   recibo.php?folio=AULA-20261102-A1B2C3                 -> se abre en el navegador
//   recibo.php?folio=AULA-20261102-A1B2C3&descargar=1     -> fuerza la descarga
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/paypal.php';

$usuario = requiere_login();

function recibo_no_encontrado(): void
{
    http_response_code(404);
    exit('Recibo no encontrado.');
}

// FPDF no entiende UTF-8: hay que pasar los textos a Windows-1252
// (si no, los acentos y la ñ salen mal en el PDF).
function pdf_txt($texto): string
{
    $texto = (string)$texto;
    if (function_exists('iconv')) {
        $r = @iconv('UTF-8', 'windows-1252//TRANSLIT', $texto);
        if ($r !== false) {
            return $r;
        }
    }
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
    }
    return $texto;
}

// ---------- 1) Buscar la venta y verificar permisos ----------
$folio = trim((string)($_GET['folio'] ?? ''));
if (!preg_match('/^[A-Za-z0-9-]{5,30}$/', $folio)) {
    recibo_no_encontrado();
}

$pdo = db();
$st = $pdo->prepare(
    "SELECT v.id, v.usuario_id, v.folio, v.total, v.paypal_capture_id, v.pagado_en,
            u.nombre AS cliente, u.email
     FROM ventas v
     JOIN usuarios u ON u.id = v.usuario_id
     WHERE v.folio = ? AND v.estado = 'pagada'"
);
$st->execute([$folio]);
$venta = $st->fetch();

// Si no existe, no está pagada o es de otra persona: mismo 404 (no revelamos nada).
$esDueno = $venta && (int)$venta['usuario_id'] === (int)$usuario['id'];
if (!$venta || (!$esDueno && $usuario['rol'] !== 'admin')) {
    recibo_no_encontrado();
}

$st = $pdo->prepare(
    'SELECT p.titulo, m.nombre AS materia, d.precio_unitario, d.cantidad
     FROM detalle_venta d
     JOIN productos p ON p.id = d.producto_id
     JOIN materias m ON m.id = p.materia_id
     WHERE d.venta_id = ?
     ORDER BY p.id'
);
$st->execute([$venta['id']]);
$detalle = $st->fetchAll();

// ---------- 2) Cargar FPDF ----------
$rutaFpdf = __DIR__ . '/lib/fpdf/fpdf.php';
if (!is_file($rutaFpdf)) {
    http_response_code(500);
    exit('Falta la librería FPDF. Copia fpdf.php y la carpeta font/ dentro de lib/fpdf/ (ver instrucciones de la Fase 5).');
}
require_once $rutaFpdf;

class ReciboPDF extends FPDF
{
    public $folioPie = '';

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(138, 138, 138);
        $this->Cell(0, 8, pdf_txt('Aula · Recibo ' . $this->folioPie . ' · Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'C');
    }
}

// ---------- 3) Dibujar el recibo ----------
$fechaPago = $venta['pagado_en'] ? date('d/m/Y H:i', strtotime($venta['pagado_en'])) : '-';

$pdf = new ReciboPDF('P', 'mm', 'A4');
$pdf->folioPie = $venta['folio'];
$pdf->SetTitle(pdf_txt('Recibo ' . $venta['folio']));
$pdf->SetAuthor('Aula');
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AliasNbPages();
$pdf->AddPage();

// Banda superior (color de la página: --verde-oscuro)
$pdf->SetFillColor(139, 147, 224);
$pdf->Rect(0, 0, 210, 34, 'F');

$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Helvetica', 'B', 26);
$pdf->SetXY(15, 8);
$pdf->Cell(90, 12, 'Aula', 0, 0, 'L');
$pdf->SetFont('Helvetica', '', 12);
$pdf->SetXY(15, 21);
$pdf->Cell(90, 6, 'Recibo de pago', 0, 0, 'L');

$pdf->SetFont('Helvetica', 'B', 11);
$pdf->SetXY(105, 12);
$pdf->Cell(90, 6, pdf_txt('Folio ' . $venta['folio']), 0, 0, 'R');
$pdf->SetFont('Helvetica', '', 10);
$pdf->SetXY(105, 20);
$pdf->Cell(90, 6, pdf_txt($fechaPago), 0, 0, 'R');

// Datos de la compra
$pdf->SetTextColor(74, 74, 74);
$pdf->SetY(44);

$par = function ($etiqueta, $valor) use ($pdf) {
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(42, 6.5, pdf_txt($etiqueta), 0, 0, 'L');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 6.5, pdf_txt($valor), 0, 1, 'L');
};
$par('Cliente:', $venta['cliente']);
$par('Correo:', $venta['email']);
$par('Fecha de pago:', $fechaPago);
$par('Método de pago:', 'PayPal');
$par('Estado:', 'Pagado');
if (!empty($venta['paypal_capture_id'])) {
    $par('ID de transacción:', $venta['paypal_capture_id']);
}

// Tabla de conceptos (ancho total 180 mm)
$wConcepto = 98;
$wCant     = 20;
$wPrecio   = 31;
$wImporte  = 31;

$pdf->Ln(6);
$pdf->SetFillColor(139, 147, 224);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->Cell($wConcepto, 8, 'Concepto', 0, 0, 'L', true);
$pdf->Cell($wCant, 8, 'Cant.', 0, 0, 'C', true);
$pdf->Cell($wPrecio, 8, 'Precio', 0, 0, 'R', true);
$pdf->Cell($wImporte, 8, 'Importe', 0, 1, 'R', true);

$pdf->SetDrawColor(230, 227, 239);
$pdf->SetLineWidth(0.3);

foreach ($detalle as $d) {
    $cantidad = (int)$d['cantidad'];
    $precio   = (float)$d['precio_unitario'];
    $importe  = round($precio * $cantidad, 2);

    // Por si hay muchos conceptos: nueva página antes de que no quepa la fila.
    if ($pdf->GetY() > $pdf->GetPageHeight() - 55) {
        $pdf->AddPage();
    }

    $x = $pdf->GetX();
    $y = $pdf->GetY();

    // Concepto: título en negrita y materia debajo (se ajusta si el texto es largo)
    $pdf->SetXY($x, $y + 1);
    $pdf->SetTextColor(74, 74, 74);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->MultiCell($wConcepto, 5.5, pdf_txt($d['titulo']), 0, 'L');
    $pdf->SetX($x);
    $pdf->SetTextColor(138, 138, 138);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->MultiCell($wConcepto, 4.5, pdf_txt($d['materia']), 0, 'L');
    $altura = ($pdf->GetY() - $y) + 2;

    // Columnas numéricas
    $pdf->SetTextColor(74, 74, 74);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetXY($x + $wConcepto, $y + 1);
    $pdf->Cell($wCant, 5.5, (string)$cantidad, 0, 0, 'C');
    $pdf->Cell($wPrecio, 5.5, pdf_txt(dinero($precio)), 0, 0, 'R');
    $pdf->Cell($wImporte, 5.5, pdf_txt(dinero($importe)), 0, 0, 'R');

    // Línea inferior de la fila
    $pdf->Line($x, $y + $altura, $x + 180, $y + $altura);
    $pdf->SetXY($x, $y + $altura);
}

// Total
$pdf->Ln(4);
$pdf->SetTextColor(74, 74, 74);
$pdf->SetFont('Helvetica', 'B', 12);
$pdf->Cell(79, 10, '', 0, 0);
$pdf->Cell(40, 10, 'Total pagado', 0, 0, 'R');
$pdf->Cell(61, 10, pdf_txt(dinero((float)$venta['total'])), 0, 1, 'R');

// Notas
$pdf->Ln(8);
$pdf->SetTextColor(138, 138, 138);
$pdf->SetFont('Helvetica', 'I', 9);
$pdf->MultiCell(0, 5, pdf_txt('Este documento es un comprobante de pago, no una factura fiscal.'), 0, 'L');
if (stripos(paypal_api_base(), 'sandbox') !== false) {
    $pdf->MultiCell(0, 5, pdf_txt('Pago realizado en PayPal Sandbox (modo de pruebas): no se cobró dinero real.'), 0, 'L');
}

// ---------- 4) Entregar el PDF ----------
header('X-Content-Type-Options: nosniff');
$pdf->Output(isset($_GET['descargar']) ? 'D' : 'I', 'Recibo-' . $venta['folio'] . '.pdf');
exit;
