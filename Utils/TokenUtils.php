<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Utils;

class TokenUtils
{
    public static function generateExpiredToken(): \MongoId
    {
        return new \MongoId();
    }

    public static function isValidToken(string $token): bool
    {
        return (bool) preg_match('/^[0-9a-z]{24}$/', $token);
    }
}
