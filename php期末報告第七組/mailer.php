<?php
function smtp_read($socket): string
{
    $data = '';
    while (($line = fgets($socket, 515)) !== false) {
        $data .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $data;
}

function smtp_write($socket, string $command): void
{
    fwrite($socket, $command . "\r\n");
}

function smtp_expect($socket, string $prefix): void
{
    $response = smtp_read($socket);
    if (strncmp($response, $prefix, strlen($prefix)) !== 0) {
        throw new RuntimeException('SMTP error: ' . trim($response));
    }
}

function mime_header(string $text): string
{
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function send_platform_mail(array $to, string $subject, string $body): void
{
    $config = require __DIR__ . '/config.php';
    $smtp = $config['smtp'];
    $socket = stream_socket_client(
        $smtp['host'] . ':' . $smtp['port'],
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        throw new RuntimeException("SMTP connection failed: $errstr");
    }

    smtp_expect($socket, '220');
    smtp_write($socket, 'EHLO localhost');
    smtp_expect($socket, '250');
    smtp_write($socket, 'AUTH LOGIN');
    smtp_expect($socket, '334');
    smtp_write($socket, base64_encode($smtp['user']));
    smtp_expect($socket, '334');
    smtp_write($socket, base64_encode($smtp['pass']));
    smtp_expect($socket, '235');

    smtp_write($socket, 'MAIL FROM:<' . $smtp['user'] . '>');
    smtp_expect($socket, '250');
    foreach ($to as $email) {
        smtp_write($socket, 'RCPT TO:<' . $email . '>');
        smtp_expect($socket, '250');
    }
    smtp_write($socket, 'DATA');
    smtp_expect($socket, '354');

    $headers = [
        'From: ' . mime_header($smtp['from_name']) . ' <' . $smtp['user'] . '>',
        'To: ' . implode(', ', $to),
        'Subject: ' . mime_header($subject),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];

    smtp_write($socket, implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body)) . "\r\n.");
    smtp_expect($socket, '250');
    smtp_write($socket, 'QUIT');
    fclose($socket);
}
