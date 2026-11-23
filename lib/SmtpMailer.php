<?php
/**
 * Minimal authenticated SMTP client (no dependencies).
 * Supports implicit TLS (465) and STARTTLS (587), AUTH LOGIN, multipart text/html.
 */
declare(strict_types=1);

final class SmtpMailer
{
    /** @var resource|null */
    private $sock = null;

    public function __construct(
        private string $host,
        private int $port,
        private string $user,
        private string $pass,
        private ?string $secure = null, // 'ssl' (implicit TLS) | 'tls' (STARTTLS) | null = auto by port
        private int $timeout = 20
    ) {}

    public function send(
        string $from, string $fromName, string $to,
        string $subject, string $text, string $html,
        ?string $replyTo = null, string $replyName = ''
    ): void {
        $this->connect();
        try {
            $this->cmd('MAIL FROM:<' . $from . '>', [250]);
            $this->cmd('RCPT TO:<' . $to . '>', [250, 251]);
            $this->cmd('DATA', [354]);
            $msg = $this->build($from, $fromName, $to, $subject, $text, $html, $replyTo, $replyName);
            // dot-stuffing
            $msg = preg_replace('/^\./m', '..', $msg);
            fwrite($this->sock, $msg . "\r\n.\r\n");
            $this->expect([250]);
            $this->cmd('QUIT', [221]);
        } finally {
            if (is_resource($this->sock)) fclose($this->sock);
        }
    }

    private function connect(): void
    {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $implicit = $this->secure === 'ssl' || ($this->secure === null && $this->port === 465);
        $remote = ($implicit ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $sock = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) throw new RuntimeException("Connect failed: $errstr ($errno)");
        stream_set_timeout($sock, $this->timeout);
        $this->sock = $sock;

        $this->expect([220]);
        $ehlo = 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $this->cmd($ehlo, [250]);

        if (!$implicit) {
            $this->cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS failed');
            }
            $this->cmd($ehlo, [250]);
        }

        $this->cmd('AUTH LOGIN', [334]);
        $this->cmd(base64_encode($this->user), [334]);
        $this->cmd(base64_encode($this->pass), [235]);
    }

    private function cmd(string $line, array $ok): string
    {
        fwrite($this->sock, $line . "\r\n");
        return $this->expect($ok);
    }

    private function expect(array $ok): string
    {
        $resp = '';
        while (($line = fgets($this->sock, 515)) !== false) {
            $resp .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        $code = (int)substr($resp, 0, 3);
        if (!in_array($code, $ok, true)) {
            throw new RuntimeException('SMTP unexpected response: ' . trim($resp));
        }
        return $resp;
    }

    private static function encHeader(string $s): string
    {
        return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    private function build(
        string $from, string $fromName, string $to, string $subject,
        string $text, string $html, ?string $replyTo, string $replyName
    ): string {
        $b = 'b_' . bin2hex(random_bytes(12));
        $domain = substr(strrchr($from, '@'), 1) ?: 'localhost';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . self::encHeader($fromName) . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . self::encHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $b . '"',
        ];
        if ($replyTo) {
            $headers[] = 'Reply-To: ' . self::encHeader($replyName) . ' <' . $replyTo . '>';
        }
        $body = "--$b\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($text))
              . "--$b\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($html))
              . "--$b--";
        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }
}
