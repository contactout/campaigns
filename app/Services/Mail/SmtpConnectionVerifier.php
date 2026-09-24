<?php

namespace App\Services\Mail;

use RuntimeException;

/**
 * Performs a lightweight, dependency-free reachability check against an SMTP server.
 *
 * Only the initial connection and the SMTP 220 greeting are validated. Authentication
 * (username/password) is not exercised here because most providers rate-limit failed
 * login attempts; credentials are therefore validated on the first real send.
 */
class SmtpConnectionVerifier
{
    /**
     * Verify that the SMTP server accepts a connection and sends a 220 greeting.
     *
     * @param  array<string, mixed>  $settings
     *
     * @throws RuntimeException When the server is unreachable or does not greet with a 2xx code.
     */
    public function verify(array $settings): void
    {
        $scheme = ($settings['encryption'] ?? 'tls') === 'ssl' ? 'ssl://' : 'tcp://';

        $address = $scheme.($settings['host'] ?? '').':'.($settings['port'] ?? '');

        $errorNumber = 0;
        $errorMessage = '';

        $stream = @stream_socket_client($address, $errorNumber, $errorMessage, 10);

        if ($stream === false) {
            throw new RuntimeException(sprintf(
                'Could not connect to %s: %s',
                $address,
                $errorMessage !== '' ? $errorMessage : 'connection refused',
            ));
        }

        $greeting = @fread($stream, 512);

        fclose($stream);

        if ($greeting === false || ! str_starts_with($greeting, '2')) {
            throw new RuntimeException(sprintf(
                'The SMTP server at %s did not return a valid greeting.',
                $address,
            ));
        }
    }
}
