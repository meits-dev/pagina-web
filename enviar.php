<?php
// Recibe el formulario de contacto de la página y lo envía a tu correo.
// Súbelo a public_html, junto a index.html.
header('Content-Type: application/json; charset=utf-8');

$to = 'hola@meits.com.mx';   // correo que recibe los mensajes (debe existir en tu hosting)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) { $in = $_POST; }

function limpiar($v, $max = 2000) {
    $v = trim((string)($v ?? ''));
    $v = str_replace(["\r", "\0"], '', $v);
    return mb_substr($v, 0, $max);
}

// Campo trampa para bots: si viene lleno, se ignora sin avisar
if (!empty($in['web'])) { echo json_encode(['ok' => true]); exit; }

$nombre   = limpiar($in['nombre']   ?? '', 120);
$empresa  = limpiar($in['empresa']  ?? '', 120);
$contacto = limpiar($in['contacto'] ?? '', 160);
$temas    = limpiar($in['temas']    ?? '', 300);
$mensaje  = limpiar($in['mensaje']  ?? '', 4000);

if ($contacto === '' || ($mensaje === '' && $temas === '')) {
    http_response_code(422);
    echo json_encode(['ok' => false]);
    exit;
}

$asunto = 'Contacto desde la web de Meits';
$cuerpo = "Nombre: $nombre\nEmpresa: $empresa\nContacto: $contacto\nLe interesa: $temas\n\nMensaje:\n$mensaje\n";

$cabeceras = [
    'From: Meits Web <' . $to . '>',
    'Content-Type: text/plain; charset=UTF-8',
];
if (filter_var($contacto, FILTER_VALIDATE_EMAIL)) {
    $cabeceras[] = 'Reply-To: ' . $contacto;
}

$enviado = mail($to, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, implode("\r\n", $cabeceras));
echo json_encode(['ok' => (bool)$enviado]);
