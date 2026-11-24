<?php
/**
 * Maritor — contact form handler.
 * Sends submissions to the Operations mailbox through authenticated SMTP.
 * Responds with JSON for fetch() requests, or redirects back for no-JS posts.
 */
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');

$wantsJson = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch')
    || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function respond(bool $ok, string $error = '', int $code = 200): void
{
    global $wantsJson;
    if ($wantsJson) {
        http_response_code($ok ? 200 : $code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    } else {
        header('Location: contact.html?sent=' . ($ok ? '1' : '0'), true, 303);
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(false, 'Method not allowed.', 405);
}

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    error_log('Maritor contact: config.php missing');
    respond(false, 'The form is temporarily unavailable. Please try again later.', 500);
}
$cfg = require $configFile;

/* ---------- Anti-spam ---------- */
// 1) honeypot
if (trim((string)($_POST['company_website'] ?? '')) !== '') {
    respond(true); // pretend success to bots
}
// 2) time-to-fill
$ts = (int)($_POST['ts'] ?? 0);
if ($ts <= 0 || (time() * 1000 - $ts) < ($cfg['min_seconds'] * 1000)) {
    respond(true);
}
// 3) per-IP rate limit (file-based)
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rlDir = sys_get_temp_dir() . '/maritor_rl';
@mkdir($rlDir, 0700, true);
$rlFile = $rlDir . '/' . hash('sha256', $ip);
$hits = [];
if (is_file($rlFile)) {
    $hits = array_filter(array_map('intval', explode(',', (string)file_get_contents($rlFile))), fn($t) => $t > time() - 3600);
}
if (count($hits) >= $cfg['max_per_hour_ip']) {
    respond(false, 'Too many messages from your network. Please try again later.', 429);
}

/* ---------- Validation ---------- */
$clean = fn(string $k, int $max) => mb_substr(trim(str_replace(["\r", "\0"], '', (string)($_POST[$k] ?? ''))), 0, $max);

$name    = $clean('name', 100);
$phone   = $clean('phone', 30);
$email   = $clean('email', 150);
$service = $clean('service', 20);
$message = mb_substr(trim(str_replace("\0", '', (string)($_POST['message'] ?? ''))), 0, 5000);

$services = [
    'chartering' => 'Chartering Inquiry',
    'security'   => 'Maritime Security',
    'insurance'  => 'Marine Insurance',
    'general'    => 'General Inquiry',
];

$digits = preg_replace('/\D/', '', $phone);
if (mb_strlen($name) < 2)                                                   respond(false, 'Please enter your name.', 422);
if (!preg_match('/^[+\d\s().-]{6,30}$/', $phone) || strlen($digits) < 6 || strlen($digits) > 15)
                                                                            respond(false, 'Please enter a valid phone number.', 422);
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))            respond(false, 'Please enter a valid email address.', 422);
if (!isset($services[$service]))                                            respond(false, 'Please choose a subject.', 422);
if (mb_strlen($message) < 5)                                                respond(false, 'Please write your message.', 422);

// Strip anything that could inject headers
foreach ([$name, $phone, $email] as $v) {
    if (preg_match('/[\r\n]/', $v)) respond(false, 'Invalid input.', 422);
}

/* ---------- Compose ---------- */
$subjectLine = 'Website inquiry: ' . $services[$service] . ' — ' . $name;
$when = gmdate('Y-m-d H:i') . ' UTC';

$text = "New inquiry from maritorshipbrokers.com\n\n"
      . "Subject:  {$services[$service]}\n"
      . "Name:     {$name}\n"
      . "Phone:    {$phone}\n"
      . "Email:    " . ($email !== '' ? $email : '—') . "\n"
      . "Received: {$when}\n"
      . "IP:       {$ip}\n\n"
      . "Message:\n{$message}\n";

$h = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#18213a">'
      . '<h2 style="color:#0f1f45;margin:0 0 16px">New website inquiry</h2>'
      . '<table cellpadding="6" style="border-collapse:collapse">'
      . '<tr><td style="color:#5b6478">Subject</td><td><b>' . $h($services[$service]) . '</b></td></tr>'
      . '<tr><td style="color:#5b6478">Name</td><td>' . $h($name) . '</td></tr>'
      . '<tr><td style="color:#5b6478">Phone</td><td><a href="tel:' . $h(preg_replace('/[^\d+]/', '', $phone)) . '">' . $h($phone) . '</a></td></tr>'
      . '<tr><td style="color:#5b6478">Email</td><td>' . ($email !== '' ? '<a href="mailto:' . $h($email) . '">' . $h($email) . '</a>' : '—') . '</td></tr>'
      . '<tr><td style="color:#5b6478">Received</td><td>' . $h($when) . '</td></tr>'
      . '</table>'
      . '<p style="margin:20px 0 6px;color:#5b6478">Message</p>'
      . '<div style="white-space:pre-wrap;background:#f4f6fa;padding:14px;border-radius:6px">' . $h($message) . '</div>'
      . '</div>';

require __DIR__ . '/lib/SmtpMailer.php';

try {
    $mailer = new SmtpMailer(
        $cfg['smtp_host'], (int)$cfg['smtp_port'], $cfg['smtp_user'], $cfg['smtp_pass'],
        $cfg['smtp_secure'] ?? null
    );
    $mailer->send(
        $cfg['from'], $cfg['from_name'], $cfg['to'],
        $subjectLine, $text, $html,
        $email !== '' ? $email : null, $name
    );
} catch (Throwable $e) {
    error_log('Maritor contact SMTP error: ' . $e->getMessage());
    respond(false, 'Sorry, the message could not be sent. Please try again in a moment.', 502);
}

$hits[] = time();
@file_put_contents($rlFile, implode(',', $hits), LOCK_EX);

respond(true);
