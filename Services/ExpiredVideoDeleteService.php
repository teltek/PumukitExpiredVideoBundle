<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;
use Pumukit\NotificationBundle\Services\SenderService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Services\FactoryService;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExpiredVideoDeleteService
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        private readonly FactoryService $factoryService,
        private readonly SenderService $senderService,
        private readonly TranslatorInterface $translator,
    ) {}

    public function getAllExpiredVideosToDelete(): array
    {
        return $this->findExpiredBefore($this->deletionLimit(new \DateTimeImmutable()));
    }

    public function getVideosToDeleteTomorrow(): array
    {
        $now = new \DateTimeImmutable();

        return $this->findExpiredBefore($this->deletionLimit($now->modify('+1 day')), $this->deletionLimit($now));
    }

    public function describe(MultimediaObject $multimediaObject): array
    {
        return [
            $multimediaObject->getId(),
            $multimediaObject->getTitle(),
            $multimediaObject->getProperty($this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey()),
        ];
    }

    public function removeMultimediaObject(MultimediaObject $multimediaObject): array
    {
        $element = $this->describe($multimediaObject);
        $series = $multimediaObject->getSeries();

        $this->factoryService->deleteMultimediaObject($multimediaObject);

        if (null !== $series && !$this->seriesHasMultimediaObjects($series->getId())) {
            $this->factoryService->deleteSeries($series);
        }

        return $element;
    }

    public function sendAdministratorEmail(array $result): bool
    {
        if (!$this->senderService->isEnabled()) {
            return false;
        }

        $configuration = $this->expiredVideoConfigurationService->getDeleteEmailConfiguration();

        return $this->senderService->sendNotification(
            $this->expiredVideoConfigurationService->getAdministratorEmails(),
            $this->translator->trans($configuration['subject']),
            $configuration['template'],
            [
                'subject' => $configuration['subject'],
                'multimedia_objects' => $result,
                'multimedia_objects_to_remove_tomorrow' => $this->getMultimediaObjectsWhichExpiredTomorrow(),
            ],
            false
        );
    }

    private function deletionLimit(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->sub(new \DateInterval('P'.$this->expiredVideoConfigurationService->getExpirationDateDaysConf().'D'));
    }

    private function findExpiredBefore(\DateTimeImmutable $limit, ?\DateTimeImmutable $after = null): array
    {
        $key = $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(true);
        $qb = $this->documentManager->getRepository(MultimediaObject::class)->createQueryBuilder()
            ->field('status')->notEqual(MultimediaObject::STATUS_PROTOTYPE)
            ->field($key)->exists(true)
            ->field($key)->lte($limit->format('c'))
        ;
        if ($after) {
            $qb->field($key)->gt($after->format('c'));
        }

        return $qb->getQuery()->toArray();
    }

    private function seriesHasMultimediaObjects(string $seriesId): bool
    {
        return null !== $this->documentManager->getRepository(MultimediaObject::class)->findOneBy([
            'series' => new ObjectId($seriesId),
            'status' => ['$ne' => MultimediaObject::STATUS_PROTOTYPE],
        ]);
    }

    private function getMultimediaObjectsWhichExpiredTomorrow(): array
    {
        return array_map(
            static fn (MultimediaObject $multimediaObject): array => [$multimediaObject->getId(), $multimediaObject->getTitle()],
            $this->getVideosToDeleteTomorrow()
        );
    }
}
