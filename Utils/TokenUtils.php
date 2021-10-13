<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Utils;

use MongoDB\BSON\ObjectId;

class TokenUtils
{
    public static function generateExpiredToken(): ObjectId
    {
        return new ObjectId();
    }

    public static function isValidToken(string $token): bool
    {
        return (bool) preg_match('/^[0-9a-z]{24}$/', $token);
    }
}
