<?php
declare(strict_types=1);

namespace Cms\Security;

final class Csrf
{
    public static function token(): string
    {
        if (!Session::has('_csrf_token')) {
            Session::put('_csrf_token', bin2hex(random_bytes(32)));
        }

        return (string) Session::get('_csrf_token');
    }

    public static function validate(mixed $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $storedToken = (string) Session::get('_csrf_token', '');

        return $storedToken !== '' && hash_equals($storedToken, $token);
    }
}
