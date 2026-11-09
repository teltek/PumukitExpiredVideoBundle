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

    public function __construct(
        private readonly int $expirationDateDaysConf,
        private readonly int $rangeWarningDays,
        private readonly string $notificationEmailSubject,
        private readonly string $notificationEmailTemplate,
        private readonly string $updateEmailSubject,
        private readonly string $updateEmailTemplate,
        private readonly array $administratorEmails,
        private readonly string $deleteEmailSubject,
        private readonly string $deleteEmailTemplate,
    ) {}

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

    public function getDeleteEmailConfiguration(): array
    {
        return [
            'subject' => $this->deleteEmailSubject,
            'template' => $this->deleteEmailTemplate,
        ];
    }

    public function getMultimediaObjectPropertyExpirationDateKey(bool $fullPropertyKey = false): string
    {
        if ($fullPropertyKey) {
            return 'properties.'.self::MULTIMEDIA_OBJECT_PROPERTY_EXPIRATION_DATE;
        }

        return self::MULTIMEDIA_OBJECT_PROPERTY_EXPIRATION_DATE;
    }

    public function getMultimediaObjectPropertyRenewExpirationDateKey(bool $fullPropertyKey = false): string
    {
        if ($fullPropertyKey) {
            return 'properties.'.self::MULTIMEDIA_OBJECT_PROPERTY_RENEW_EXPIRATION_DATE;
        }

        return self::MULTIMEDIA_OBJECT_PROPERTY_RENEW_EXPIRATION_DATE;
    }

    public function getMultimediaObjectPropertyRenewKey(bool $fullPropertyKey = false): string
    {
        if ($fullPropertyKey) {
            return 'properties.'.self::RENEW_PROPERTY_KEY;
        }

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

    public function getAdministratorEmails(): array
    {
        return $this->administratorEmails;
    }
}
