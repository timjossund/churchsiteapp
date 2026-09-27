<?php

namespace App\Support;

final class CustomerPagePath
{
    public const RESERVED = ['editor', 'checkout', 'webhook', 'passkey', 'passkeys', 'login', 'logout', 'register', 'dashboard', 'sites', 'settings', 'profile', 'billing', 'stripe', 's', 'up', 'api', 'admin', 'password', 'forgot-password', 'reset-password', 'confirm-password', 'email', 'verify-email', 'two-factor-challenge', 'user', 'storage', 'build'];

    public static function valid(string $path): bool
    {
        return strlen($path) <= 100 && preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $path) === 1
            && ! in_array($path, self::RESERVED, true);
    }
}
