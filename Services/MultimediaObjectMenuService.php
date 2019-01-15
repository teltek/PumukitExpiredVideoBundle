<?php

namespace Pumukit\ExpiredVideoBundle\Services;

use Pumukit\NewAdminBundle\Menu\ItemInterface;

/**
 * Class MultimediaObjectMenuService.
 */
class MultimediaObjectMenuService implements ItemInterface
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'Show expiration date';
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return 'pumukit_expired_video_info';
    }

    /**
     * @return string
     */
    public function getAccessRole()
    {
        return 'ROLE_ACCESS_MULTIMEDIA_SERIES';
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return 'mdi-device-access-time';
    }
}
