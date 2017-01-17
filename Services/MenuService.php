<?php

namespace Pumukit\ExpiredVideoBundle\Services;

use Pumukit\NewAdminBundle\Menu\ItemInterface;

class MenuService implements ItemInterface
{
    public function getName()
    {
        return 'Expired video list';
    }

    public function getUri()
    {
        return 'pumukit_expired_video_list_all';
    }

    public function getAccessRole()
    {
        return 'ROLE_ACCESS_EXPIRED_VIDEO';
    }
}
