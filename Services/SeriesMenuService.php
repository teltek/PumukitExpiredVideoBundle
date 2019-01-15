<?php

namespace Pumukit\ExpiredVideoBundle\Services;

use Pumukit\NewAdminBundle\Menu\ItemInterface;

/**
 * Class SeriesMenuService.
 */
class SeriesMenuService implements ItemInterface
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'Renew all videos';
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return 'pumukit_expired_video_renew_list';
    }

    /**
     * @return string
     */
    public function getAccessRole()
    {
        return 'ROLE_ACCESS_EXPIRED_VIDEO';
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return 'mdi-device-access-time';
    }
}
