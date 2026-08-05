<?php
/**
 * Notification seam — placeholder for the future centralized email
 * engine (PHPMailer). Business logic calls mp_notify() at every point
 * an email should eventually fire; swapping this for real SMTP sending
 * later won't require touching any page.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_notify(string $event, string $recipientEmail, array $context = []): void
{
    $dir = __DIR__ . '/../../logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $line = sprintf(
        "[%s] %s -> %s %s\n",
        date('Y-m-d H:i:s'),
        $event,
        $recipientEmail,
        json_encode($context)
    );

    file_put_contents($dir . '/notifications.log', $line, FILE_APPEND);
}
