<?php

namespace Pumukit\ExpiredVideoBundle\Services;

use Pumukit\NewAdminBundle\Menu\ItemInterface;

class MultimediaObjectMenuService implements ItemInterface
{
    public function getName(): string
    {
        return 'Show expiration date';
    }

    public function getUri(): string
    {
        return 'pumukit_expired_video_info';
    }

    public function getAccessRole(): string
    {
        return 'ROLE_ACCESS_MULTIMEDIA_SERIES';
    }

    public function getIcon(): string
    {
        return 'mdi-device-access-time';
    }
}
