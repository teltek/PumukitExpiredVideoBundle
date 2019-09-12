<?php

namespace Pumukit\ExpiredVideoBundle\Services;

use Pumukit\NewAdminBundle\Menu\ItemInterface;

class SeriesMenuService implements ItemInterface
{
    public function getName(): string
    {
        return 'Renew all videos';
    }

    public function getUri(): string
    {
        return 'pumukit_expired_video_renew_list';
    }

    public function getAccessRole(): string
    {
        return 'ROLE_ACCESS_EXPIRED_VIDEO';
    }

    public function getIcon(): string
    {
        return 'mdi-device-access-time';
    }
}
