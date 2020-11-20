<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Services;

use Pumukit\NewAdminBundle\Menu\ItemInterface;

class MenuService implements ItemInterface
{
    public function getName(): string
    {
        return 'Expired video list';
    }

    public function getUri(): string
    {
        return 'pumukit_expired_video_list_all';
    }

    public function getAccessRole(): string
    {
        return 'ROLE_ACCESS_EXPIRED_VIDEO';
    }

    public function getClass(): string
    {
        return 'qa-button-video-expiration-date';
    }
}
