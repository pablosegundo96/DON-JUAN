<?php
// enviar-cv.php — recibe el formulario "Trabajá con nosotros" y envía el CV por email.
// Subir junto al index.html. Ajustar $DESTINO y $REMITENTE (debe ser una casilla del dominio en Hostinger).
$DESTINO   = 'rrhh@donjuan.com.ar';
$REMITENTE = 'no-responder@donjuan.com.ar';
$MAX_BYTES = 5 * 1024 * 1024;
$EXT_OK    = ['pdf', 'doc', 'docx'];

header('Content-Type: application/json; charset=utf-8');
function fin($ok, $error = '') { echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fin(false, 'Método no permitido.');
if (!empty($_POST['web'])) fin(true); // honeypot: bots

$nombre = trim(strip_tags($_POST['nombre'] ?? ''));
$email  = trim($_POST['email'] ?? '');
$tel    = trim(strip_tags($_POST['tel'] ?? ''));
$puesto = trim(strip_tags($_POST['puesto'] ?? ''));

if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) fin(false, 'Revisá tu nombre y email.');
if (empty($_POST['ok'])) fin(false, 'Tenés que aceptar el uso de tus datos.');
if (!isset($_FILES['cv']) || $_FILES['cv']['error'] !== UPLOAD_ERR_OK) fin(false, 'No se pudo recibir el archivo.');

$f = $_FILES['cv'];
$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $EXT_OK, true)) fin(false, 'Formato no válido. Usá PDF o Word.');
if ($f['size'] > $MAX_BYTES) fin(false, 'El archivo supera los 5 MB.');

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
$mimes_ok = ['application/pdf', 'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'];
if (!in_array($mime, $mimes_ok, true)) fin(false, 'El archivo no parece un PDF o Word válido.');

$archivo = 'CV-' . preg_replace('/[^A-Za-z0-9]+/', '-', $nombre) . '.' . $ext;
$adjunto = chunk_split(base64_encode(file_get_contents($f['tmp_name'])));
$b = md5(uniqid('', true));

$texto = "Nueva postulación desde la web\n\nNombre: $nombre\nEmail: $email\nTeléfono: $tel\nPuesto: $puesto\n";

$headers  = "From: Web Don Juan SRL <$REMITENTE>\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$b\"\r\n";

$cuerpo  = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$texto\r\n";
$cuerpo .= "--$b\r\nContent-Type: $mime; name=\"$archivo\"\r\nContent-Transfer-Encoding: base64\r\n";
$cuerpo .= "Content-Disposition: attachment; filename=\"$archivo\"\r\n\r\n$adjunto\r\n--$b--";

$asunto = '=?UTF-8?B?' . base64_encode("Postulación web - $nombre") . '?=';
fin(mail($DESTINO, $asunto, $cuerpo, $headers), 'No se pudo enviar. Probá de nuevo más tarde.');
