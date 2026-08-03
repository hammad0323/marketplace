<?php

/**
 * Placeholder seam for the future centralized email notification
 * engine (out of scope for this PR). Business logic calls
 * Notifier::log() at every point an email should eventually fire —
 * swapping this implementation for real template/SMTP sending later
 * won't require touching any controller.
 */
class Notifier
{
    public static function log(string $event, string $recipientEmail, array $context = []): void
    {
        $dir = __DIR__ . '/../../storage/logs';
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
}
