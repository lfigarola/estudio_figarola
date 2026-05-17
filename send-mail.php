<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html#contacto');
    exit;
}

$requestId = strtoupper(substr(bin2hex(function_exists('random_bytes') ? random_bytes(8) : openssl_random_pseudo_bytes(8)), 0, 10));

function clean_header_value($value, $maxLength = 200) {
    $value = trim(str_replace(["\r", "\n", "\0"], ' ', strip_tags((string) ($value ?? ''))));
    $value = preg_replace('/\s+/', ' ', $value);

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    return substr($value, 0, $maxLength);
}

function clean_message_value($value, $maxLength = 3000) {
    $value = trim(str_replace("\0", '', strip_tags((string) ($value ?? ''))));
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace("/\n{4,}/", "\n\n\n", $value);

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    return substr($value, 0, $maxLength);
}

function bool_config($value) {
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
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
    body{margin:0;font-family:Calibri,Carlito,"Source Sans 3","Segoe UI",Arial,sans-serif;background:#1d2f43;color:#d0ceba;line-height:1.6;padding:2rem}
    .box{max-width:680px;margin:3rem auto;background:#23384d;border:1px solid rgba(208,206,186,.18);border-radius:8px;padding:1.6rem;box-shadow:0 18px 38px rgba(0,0,0,.26)}
    h1{margin:0 0 .7rem;color:<?php echo $headingColor; ?>}
    a{color:#d0ceba;font-weight:700}
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

function wants_json_response() {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    return stripos($accept, 'application/json') !== false || strtolower($requestedWith) === 'xmlhttprequest';
}

function respond_form($ok, $title, $message, $statusCode = 200) {
    if (wants_json_response()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => (bool) $ok,
            'title' => $title,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    render_page($title, $message, $ok);
}

function config_candidates() {
    $fileName = 'estudio-figarola-secrets.php';
    $localFileName = 'estudio-figarola-secrets.local.php';
    $paths = [];

    $envPath = getenv('ESTUDIO_FIGAROLA_SECRETS_PATH');
    if ($envPath) {
        $paths[] = $envPath;
    }

    $current = __DIR__;

    for ($i = 0; $i < 5; $i++) {
        $paths[] = $current . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . $fileName;
        $parent = dirname($current);

        if ($parent === $current) {
            break;
        }

        $current = $parent;
    }

    $paths[] = __DIR__ . DIRECTORY_SEPARATOR . 'references' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . $localFileName;

    return array_values(array_unique($paths));
}

function private_log_candidates() {
    $fileName = 'estudio-figarola-contact.log';
    $localFileName = 'estudio-figarola-contact.local.log';
    $paths = [];
    $current = __DIR__;

    for ($i = 0; $i < 5; $i++) {
        $paths[] = $current . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . $fileName;
        $parent = dirname($current);

        if ($parent === $current) {
            break;
        }

        $current = $parent;
    }

    $paths[] = __DIR__ . DIRECTORY_SEPARATOR . 'references' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . $localFileName;

    return array_values(array_unique($paths));
}

function redact_log_value($value) {
    $value = preg_replace('/(password|pass|pwd|secret|token)\s*[:=]\s*\S+/i', '$1=[redacted]', (string) $value);
    return str_replace(["\r", "\n", "\0"], ' ', $value);
}

function contact_form_log($level, $message, array $context = []) {
    $parts = [
        date('c'),
        strtoupper($level),
        redact_log_value($message),
    ];

    foreach ($context as $key => $value) {
        if (preg_match('/password|pass|pwd|secret|token/i', (string) $key)) {
            $value = '[redacted]';
        }

        $parts[] = preg_replace('/[^a-zA-Z0-9_.-]/', '_', (string) $key) . '=' . redact_log_value((string) $value);
    }

    $line = implode(' | ', $parts);
    error_log('[Estudio Figarola contact form] ' . $line);

    foreach (private_log_candidates() as $path) {
        $directory = dirname($path);

        if (!is_dir($directory) || !is_writable($directory)) {
            continue;
        }

        @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        break;
    }
}

function load_mail_config() {
    foreach (config_candidates() as $path) {
        if (!is_file($path)) {
            continue;
        }

        $config = require $path;

        if (is_array($config)) {
            return $config;
        }

        throw new RuntimeException('Mail config file did not return an array.');
    }

    throw new RuntimeException('Mail config file was not found.');
}

function cfg($config, $section, $key, $default = null) {
    return $config[$section][$key] ?? $default;
}

function validated_mail_config($config) {
    $smtp = [
        'host' => clean_header_value(cfg($config, 'smtp', 'host'), 255),
        'port' => (int) cfg($config, 'smtp', 'port', 587),
        'secure' => strtolower(clean_header_value(cfg($config, 'smtp', 'secure', 'tls'), 20)),
        'username' => clean_header_value(cfg($config, 'smtp', 'username'), 255),
        'password' => (string) cfg($config, 'smtp', 'password'),
    ];

    $mail = [
        'from_address' => clean_header_value(cfg($config, 'mail', 'from_address'), 255),
        'from_name' => clean_header_value(cfg($config, 'mail', 'from_name', 'Estudio Figarola'), 120),
        'to_address' => clean_header_value(cfg($config, 'mail', 'to_address'), 255),
        'to_name' => clean_header_value(cfg($config, 'mail', 'to_name', 'Estudio Figarola'), 120),
        'reply_to_enabled' => bool_config(cfg($config, 'mail', 'reply_to_enabled', true)),
    ];

    $missing = [];

    foreach (['host', 'username', 'password'] as $key) {
        if ($smtp[$key] === '') {
            $missing[] = 'smtp.' . $key;
        }
    }

    foreach (['from_address', 'to_address'] as $key) {
        if (!filter_var($mail[$key], FILTER_VALIDATE_EMAIL)) {
            $missing[] = 'mail.' . $key;
        }
    }

    if ($smtp['port'] < 1 || $smtp['port'] > 65535) {
        $missing[] = 'smtp.port';
    }

    if (!in_array($smtp['secure'], ['tls', 'starttls', 'ssl', 'smtps', 'none', ''], true)) {
        $missing[] = 'smtp.secure';
    }

    if ($missing) {
        throw new RuntimeException('Mail config is missing or invalid: ' . implode(', ', $missing));
    }

    return ['smtp' => $smtp, 'mail' => $mail];
}

function load_phpmailer_if_available() {
    $autoloaders = [
        __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
        dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
    ];

    foreach ($autoloaders as $autoload) {
        if (is_file($autoload)) {
            require_once $autoload;
            break;
        }
    }

    return class_exists('\\PHPMailer\\PHPMailer\\PHPMailer');
}

function encode_header($value) {
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }

    if (preg_match('/^[\x20-\x7E]*$/', $value)) {
        return $value;
    }

    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function format_mailbox($email, $name = '') {
    $email = clean_header_value($email, 255);
    $name = clean_header_value($name, 120);

    if ($name === '') {
        return '<' . $email . '>';
    }

    return encode_header($name) . ' <' . $email . '>';
}

function normalize_body($body) {
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);
    $normalized = [];

    foreach ($lines as $line) {
        if (strpos($line, '.') === 0) {
            $line = '.' . $line;
        }

        $normalized[] = $line;
    }

    return implode("\r\n", $normalized);
}

function smtp_read_response($socket) {
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (preg_match('/^\d{3}\s/', $line)) {
            break;
        }
    }

    if ($response === '') {
        throw new RuntimeException('SMTP server did not respond.');
    }

    return [(int) substr($response, 0, 3), trim($response)];
}

function smtp_send_line($socket, $line) {
    if (fwrite($socket, $line . "\r\n") === false) {
        throw new RuntimeException('Could not write to SMTP server.');
    }
}

function smtp_command($socket, $line, $expectedCodes, $label) {
    smtp_send_line($socket, $line);
    [$code, $response] = smtp_read_response($socket);

    if (!in_array($code, (array) $expectedCodes, true)) {
        throw new RuntimeException($label . ' failed with SMTP code ' . $code . '.');
    }

    return $response;
}

function send_with_builtin_smtp($settings, $message) {
    $smtp = $settings['smtp'];
    $mail = $settings['mail'];
    $secure = $smtp['secure'];
    $host = $smtp['host'];
    $port = $smtp['port'];
    $transportHost = in_array($secure, ['ssl', 'smtps'], true) ? 'ssl://' . $host : $host;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
        ],
    ]);

    $socket = @stream_socket_client($transportHost . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);

    if (!$socket) {
        throw new RuntimeException('Could not connect to SMTP server.');
    }

    stream_set_timeout($socket, 20);

    try {
        [$code] = smtp_read_response($socket);
        if ($code !== 220) {
            throw new RuntimeException('SMTP greeting failed with code ' . $code . '.');
        }

        smtp_command($socket, 'EHLO estudiofigarola.com.ar', 250, 'SMTP EHLO');

        if (in_array($secure, ['tls', 'starttls'], true)) {
            smtp_command($socket, 'STARTTLS', 220, 'SMTP STARTTLS');

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP TLS negotiation failed.');
            }

            smtp_command($socket, 'EHLO estudiofigarola.com.ar', 250, 'SMTP EHLO after STARTTLS');
        }

        smtp_command($socket, 'AUTH LOGIN', 334, 'SMTP authentication');
        smtp_send_line($socket, base64_encode($smtp['username']));
        [$userCode] = smtp_read_response($socket);
        if ($userCode !== 334) {
            throw new RuntimeException('SMTP authentication username step failed.');
        }

        smtp_send_line($socket, base64_encode($smtp['password']));
        [$passwordCode] = smtp_read_response($socket);
        if ($passwordCode !== 235) {
            throw new RuntimeException('SMTP authentication password step failed.');
        }

        smtp_command($socket, 'MAIL FROM:<' . $mail['from_address'] . '>', 250, 'SMTP MAIL FROM');
        smtp_command($socket, 'RCPT TO:<' . $mail['to_address'] . '>', [250, 251], 'SMTP RCPT TO');
        smtp_command($socket, 'DATA', 354, 'SMTP DATA');
        smtp_send_line($socket, $message . "\r\n.");
        [$dataCode] = smtp_read_response($socket);
        if ($dataCode !== 250) {
            throw new RuntimeException('SMTP message submission failed with code ' . $dataCode . '.');
        }

        smtp_command($socket, 'QUIT', 221, 'SMTP QUIT');
    } finally {
        fclose($socket);
    }
}

function send_with_phpmailer($settings, $messageData) {
    $smtp = $settings['smtp'];
    $mailConfig = $settings['mail'];
    $mailerClass = '\\PHPMailer\\PHPMailer\\PHPMailer';
    $mailer = new $mailerClass(true);

    $mailer->isSMTP();
    $mailer->Host = $smtp['host'];
    $mailer->SMTPAuth = true;
    $mailer->Username = $smtp['username'];
    $mailer->Password = $smtp['password'];
    $mailer->Port = $smtp['port'];
    $mailer->CharSet = 'UTF-8';

    if (in_array($smtp['secure'], ['tls', 'starttls'], true)) {
        $mailer->SMTPSecure = $mailerClass::ENCRYPTION_STARTTLS;
    } elseif (in_array($smtp['secure'], ['ssl', 'smtps'], true)) {
        $mailer->SMTPSecure = $mailerClass::ENCRYPTION_SMTPS;
    }

    $mailer->setFrom($mailConfig['from_address'], $mailConfig['from_name']);
    $mailer->addAddress($mailConfig['to_address'], $mailConfig['to_name']);

    if ($mailConfig['reply_to_enabled']) {
        $mailer->addReplyTo($messageData['visitor_email'], $messageData['visitor_name']);
    }

    $mailer->Subject = $messageData['subject'];
    $mailer->Body = $messageData['body'];
    $mailer->AltBody = $messageData['body'];

    $mailer->send();
}

function safe_error_log($message) {
    contact_form_log('error', substr((string) $message, 0, 600));
}

if (!empty($_POST['website'] ?? '')) {
    contact_form_log('warning', 'Spam honeypot triggered.', ['request_id' => $requestId]);
    respond_form(false, 'Mensaje no enviado', 'La protección antispam detuvo el envío.', 400);
}

$formStarted = isset($_POST['form_started']) ? (int) $_POST['form_started'] : 0;

if (!$formStarted) {
    contact_form_log('warning', 'Missing form timestamp.', ['request_id' => $requestId]);
    respond_form(false, 'Mensaje no enviado', 'El formulario no pudo validarse correctamente.', 400);
}

$elapsedSeconds = (microtime(true) * 1000 - $formStarted) / 1000;

if ($elapsedSeconds < 3) {
    contact_form_log('warning', 'Form submitted too quickly.', ['request_id' => $requestId, 'elapsed_seconds' => round($elapsedSeconds, 2)]);
    respond_form(false, 'Mensaje no enviado', 'El formulario se envió demasiado rápido.', 400);
}

$name = clean_header_value($_POST['name'] ?? '', 120);
$phone = clean_header_value($_POST['phone'] ?? '', 80);
$emailRaw = clean_header_value($_POST['email'] ?? '', 255);
$email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
$subject = clean_header_value($_POST['subject'] ?? 'Consulta desde el sitio web', 200);
$message = clean_message_value($_POST['message'] ?? '', 3000);

if (!$name || !$email || !$subject || !$message) {
    contact_form_log('warning', 'Missing required form data.', ['request_id' => $requestId]);
    respond_form(false, 'Faltan datos', 'Complete los campos requeridos e intente nuevamente.', 400);
}

if (strlen($name) > 120 || strlen($phone) > 80 || strlen($subject) > 200) {
    contact_form_log('warning', 'Header field length exceeded.', ['request_id' => $requestId]);
    respond_form(false, 'Mensaje no enviado', 'Uno de los campos supera la longitud permitida.', 400);
}

if (strlen($message) > 3000) {
    contact_form_log('warning', 'Message length exceeded.', ['request_id' => $requestId]);
    respond_form(false, 'Mensaje no enviado', 'El mensaje es demasiado extenso.', 400);
}

try {
    $settings = validated_mail_config(load_mail_config());
    $emailSubject = 'Consulta web: ' . $subject;

    $body = "Nueva consulta desde estudiofigarola.com.ar\n\n";
    $body .= "Nombre: {$name}\n";
    $body .= "Telefono: " . ($phone ?: '-') . "\n";
    $body .= "Email: {$email}\n";
    $body .= "Asunto: {$subject}\n\n";
    $body .= "Mensaje:\n{$message}\n";

    $messageData = [
        'visitor_name' => $name,
        'visitor_email' => $email,
        'subject' => $emailSubject,
        'body' => $body,
    ];

    $transport = 'builtin-smtp';

    if (load_phpmailer_if_available()) {
        $transport = 'phpmailer';
        send_with_phpmailer($settings, $messageData);
    } else {
        $mail = $settings['mail'];
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . format_mailbox($mail['from_address'], $mail['from_name']),
            'To: ' . format_mailbox($mail['to_address'], $mail['to_name']),
            'Subject: ' . encode_header($emailSubject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: Estudio Figarola SMTP',
        ];

        if ($mail['reply_to_enabled']) {
            $headers[] = 'Reply-To: ' . format_mailbox($email, $name);
        }

        send_with_builtin_smtp($settings, implode("\r\n", $headers) . "\r\n\r\n" . normalize_body($body));
    }

    contact_form_log('info', 'Mail accepted by SMTP transport.', [
        'request_id' => $requestId,
        'transport' => $transport,
        'smtp_host' => $settings['smtp']['host'],
        'from' => $settings['mail']['from_address'],
        'to' => $settings['mail']['to_address'],
        'reply_to' => $email,
    ]);

    respond_form(true, 'Mensaje enviado', 'Gracias. Su consulta fue enviada correctamente.', 200);
} catch (Throwable $exception) {
    contact_form_log('error', 'Mail send failed: ' . $exception->getMessage(), [
        'request_id' => $requestId,
        'exception' => get_class($exception),
    ]);
    respond_form(false, 'Mensaje no enviado', 'Hubo un problema al enviar el mensaje. Intente nuevamente o escriba directamente a consultas@estudiofigarola.com.ar.', 500);
}
?>
