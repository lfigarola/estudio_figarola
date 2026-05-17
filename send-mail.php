<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html#contacto');
    exit;
}

function clean_input($value) {
    return trim(str_replace(["\r", "\n"], ' ', strip_tags($value ?? '')));
}

function render_page($title, $message, $success = false) {
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $headingColor = $success ? '#1d2f43' : '#9c3129';
    ?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <meta http-equiv="refresh" content="7;url=index.html#contacto">
  <title><?php echo $safeTitle; ?></title>
  <style>
    body{margin:0;font-family:Calibri,Carlito,"Source Sans 3","Segoe UI",Arial,sans-serif;background:#f5f7f5;color:#142334;line-height:1.6;padding:2rem}
    .box{max-width:680px;margin:3rem auto;background:#fff;border:1px solid rgba(29,47,67,.14);border-radius:8px;padding:1.6rem;box-shadow:0 14px 34px rgba(18,34,52,.12)}
    h1{margin:0 0 .7rem;color:<?php echo $headingColor; ?>}
    a{color:#2d5f42;font-weight:700}
  </style>
</head>
<body>
  <main class="box">
    <h1><?php echo $safeTitle; ?></h1>
    <p><?php echo $safeMessage; ?></p>
    <p>Será redirigido en unos segundos. Si no ocurre, <a href="index.html#contacto">vuelva al formulario</a>.</p>
  </main>
</body>
</html>
<?php
    exit;
}

if (!empty($_POST['website'] ?? '')) {
    render_page('Mensaje no enviado', 'La protección antispam detuvo el envío.', false);
}

$formStarted = isset($_POST['form_started']) ? (int) $_POST['form_started'] : 0;

if (!$formStarted) {
    render_page('Mensaje no enviado', 'El formulario no pudo validarse correctamente.', false);
}

$elapsedSeconds = (microtime(true) * 1000 - $formStarted) / 1000;

if ($elapsedSeconds < 3) {
    render_page('Mensaje no enviado', 'El formulario se envió demasiado rápido.', false);
}

$name = clean_input($_POST['name'] ?? '');
$phone = clean_input($_POST['phone'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$subject = clean_input($_POST['subject'] ?? 'Consulta desde el sitio web');
$message = trim(strip_tags($_POST['message'] ?? ''));

if (!$name || !$email || !$subject || !$message) {
    render_page('Faltan datos', 'Complete los campos requeridos e intente nuevamente.', false);
}

if (strlen($name) > 120 || strlen($phone) > 80 || strlen($subject) > 200) {
    render_page('Mensaje no enviado', 'Uno de los campos supera la longitud permitida.', false);
}

if (strlen($message) > 3000) {
    render_page('Mensaje no enviado', 'El mensaje es demasiado extenso.', false);
}

$to = 'consultas@estudiofigarola.com.ar';
$emailSubject = 'Consulta web: ' . $subject;

$body = "Nueva consulta desde estudiofigarola.com.ar\n\n";
$body .= "Nombre: {$name}\n";
$body .= "Telefono: " . ($phone ?: '-') . "\n";
$body .= "Email: {$email}\n";
$body .= "Asunto: {$subject}\n\n";
$body .= "Mensaje:\n{$message}\n";

$headers = "From: Estudio Figarola <consultas@estudiofigarola.com.ar>\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

$sent = mail($to, $emailSubject, $body, $headers);

if ($sent) {
    render_page('Mensaje enviado', 'Gracias. Su consulta fue enviada correctamente a consultas@estudiofigarola.com.ar.', true);
}

render_page('Mensaje no enviado', 'Hubo un problema al enviar el mensaje. Intente nuevamente o escriba directamente a consultas@estudiofigarola.com.ar.', false);
?>
