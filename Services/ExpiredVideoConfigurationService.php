<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Services;

class ExpiredVideoConfigurationService
{
    public const MULTIMEDIA_OBJECT_PROPERTY_EXPIRATION_DATE = 'expiration_date';
    public const MULTIMEDIA_OBJECT_PROPERTY_RENEW_EXPIRATION_DATE = 'renew_expiration_date';

    public const RENEW_PROPERTY_KEY = 'expiration_key';
    public const EXPIRED_OWNER_CODE = 'expired_owner';

    public const ROLE_UNLIMITED_EXPIRED_VIDEO = 'ROLE_UNLIMITED_EXPIRED_VIDEO';
    public const ROLE_ACCESS_EXPIRED_VIDEO = 'ROLE_ACCESS_EXPIRED_VIDEO';

    private $expirationDateDaysConf;
    private $rangeWarningDays;
    private $notificationEmailSubject;
    private $notificationEmailTemplate;
    private $updateEmailSubject;
    private $updateEmailTemplate;

    public function __construct(
        int $expirationDateDaysConf,
        int $rangeWarningDays,
        string $notificationEmailSubject,
        string $notificationEmailTemplate,
        string $updateEmailSubject,
        string $updateEmailTemplate
    ) {
        $this->expirationDateDaysConf = $expirationDateDaysConf;
        $this->rangeWarningDays = $rangeWarningDays;
        $this->notificationEmailSubject = $notificationEmailSubject;
        $this->notificationEmailTemplate = $notificationEmailTemplate;
        $this->updateEmailSubject = $updateEmailSubject;
        $this->updateEmailTemplate = $updateEmailTemplate;
    }

    public function isDeactivatedService(): bool
    {
        return 0 === $this->expirationDateDaysConf;
    }

    public function getExpirationDateDaysConf(): int
    {
        return $this->expirationDateDaysConf;
    }

    public function getUnlimitedExpirationDateDays(): int
    {
        return 3649635;
    }

    public function getRangeWarningDays(): int
    {
        return $this->rangeWarningDays;
    }

    public function getNotificationEmailConfiguration(): array
    {
        return [
            'subject' => $this->notificationEmailSubject,
            'template' => $this->notificationEmailTemplate,
        ];
    }

    public function getUpdateEmailConfiguration(): array
    {
        return [
            'subject' => $this->updateEmailSubject,
            'template' => $this->updateEmailTemplate,
        ];
    }

    public function getMultimediaObjectPropertyExpirationDateKey(bool $fullPropertyKey = false): string
    {
        if ($fullPropertyKey) {
            return 'property.'.self::MULTIMEDIA_OBJECT_PROPERTY_EXPIRATION_DATE;
        }

        return self::MULTIMEDIA_OBJECT_PROPERTY_EXPIRATION_DATE;
    }

    public function getMultimediaObjectPropertyRenewExpirationDateKey(bool $fullPropertyKey = false): string
    {
        if ($fullPropertyKey) {
            return 'property.'.self::MULTIMEDIA_OBJECT_PROPERTY_RENEW_EXPIRATION_DATE;
        }

        return self::MULTIMEDIA_OBJECT_PROPERTY_RENEW_EXPIRATION_DATE;
    }

    public function getMultimediaObjectPropertyRenewKey(): string
    {
        return self::RENEW_PROPERTY_KEY;
    }

    public function getRoleCodeExpiredOwner(): string
    {
        return self::EXPIRED_OWNER_CODE;
    }

    public function getUnlimitedDateExpiredVideoCodePermission(): string
    {
        return self::ROLE_UNLIMITED_EXPIRED_VIDEO;
    }

    public function getAccessExpiredVideoCodePermission(): string
    {
        return self::ROLE_ACCESS_EXPIRED_VIDEO;
    }
}
