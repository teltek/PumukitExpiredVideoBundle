<?php

namespace Pumukit\ExpiredVideoBundle\Services;

use Pumukit\NewAdminBundle\Menu\ItemInterface;

class MultimediaObjectMenuService implements ItemInterface
{
    public function getName()
    {
        return 'Show expiration date';
    }

    public function getUri()
    {
        return 'pumukit_expired_video_info';
    }

    public function getAccessRole()
    {
        return 'ROLE_ACCESS_MULTIMEDIA_SERIES';
    }

    public function getIcon()
    {
        return 'mdi-device-access-time';
    }
}
