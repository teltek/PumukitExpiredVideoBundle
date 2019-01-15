<?php

namespace Pumukit\ExpiredVideoBundle\Services;

use Pumukit\NewAdminBundle\Menu\ItemInterface;

/**
 * Class MenuService.
 */
class MenuService implements ItemInterface
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'Expired video list';
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return 'pumukit_expired_video_list_all';
    }

    /**
     * @return string
     */
    public function getAccessRole()
    {
        return 'ROLE_ACCESS_EXPIRED_VIDEO';
    }
}
