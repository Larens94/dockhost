<?php

namespace App\Support;

class OperatorError
{
    public static function present(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return $message;
        }

        if (preg_match('/SQLSTATE\[HY000\] \[2002\]|Connection refused|Connection timed out|getaddrinfo|No route to host|php_network_getaddresses/i', $message)) {
            return 'Could not reach the database pool. Check the admin host and port.';
        }

        return $message;
    }
}
