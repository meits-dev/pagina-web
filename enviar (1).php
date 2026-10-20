<?php
// ===== CONFIGURACIÓN =====
$PARA  = 'hola@meits.com.mx';  // quién recibe los mensajes del formulario
$DESDE = 'hola@meits.com.mx';  // debe ser un correo que EXISTA en tu hosting (hPanel > Correos)
// =========================

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos);
    exit;
}
function cortar($t, $max) {
    return function_exists('mb_substr') ? mb_substr($t, 0, $max, 'UTF-8') : substr($t, 0, $max);
}
function limpiar($v, $max = 2000) {
    $v = trim((string)$v);
    $v = str_replace(array("\r", "\0"), '', $v);
    return cortar($v, $max);
}

// Prueba rápida: abre  https://tudominio.com/enviar.php?test=1
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['test'])) {
    responder(array(
        'ok' => true,
        'php' => PHP_VERSION,
        'mail_disponible' => function_exists('mail'),
        'mbstring' => function_exists('mb_substr'),
    ));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(array('ok' => false, 'error' => 'metodo'), 405);
}

$entrada = json_decode(file_get_contents('php://input'), true);
if (!is_array($entrada)) { $entrada = $_POST; }

// Campo trampa para bots: si viene lleno, se ignora sin avisar
if (!empty($entrada['web'])) { responder(array('ok' => true)); }

$nombre   = limpiar(isset($entrada['nombre'])   ? $entrada['nombre']   : '', 120);
$empresa  = limpiar(isset($entrada['empresa'])  ? $entrada['empresa']  : '', 120);
$contacto = limpiar(isset($entrada['contacto']) ? $entrada['contacto'] : '', 160);
$temas    = limpiar(isset($entrada['temas'])    ? $entrada['temas']    : '', 300);
$mensaje  = limpiar(isset($entrada['mensaje'])  ? $entrada['mensaje']  : '', 4000);

if ($contacto === '' || ($mensaje === '' && $temas === '')) {
    responder(array('ok' => false, 'error' => 'datos'), 422);
}
if (!function_exists('mail')) {
    responder(array('ok' => false, 'error' => 'mail_no_disponible'), 500);
}

$asunto = 'Contacto desde la web de Meits';
$cuerpo = "Nombre: $nombre\nEmpresa: $empresa\nContacto: $contacto\nLe interesa: $temas\n\nMensaje:\n$mensaje\n";

$cabeceras = array(
    'From: Meits Web <' . $DESDE . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
);
if (filter_var($contacto, FILTER_VALIDATE_EMAIL)) {
    $cabeceras[] = 'Reply-To: ' . $contacto;
}

$enviado = @mail($PARA, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, implode("\r\n", $cabeceras));
responder(array('ok' => (bool)$enviado, 'error' => $enviado ? null : 'envio_fallido'), $enviado ? 200 : 500);
