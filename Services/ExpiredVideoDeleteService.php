<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use MongoDB\BSON\ObjectId;
use Pumukit\NotificationBundle\Services\SenderService;
use Pumukit\SchemaBundle\Document\Material;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Pic;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\Track;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\TranslatorInterface;

class ExpiredVideoDeleteService
{
    private $documentManager;
    private $expiredVideoConfigurationService;
    private $expiredVideoService;
    private $senderService;
    private $translator;

    public function __construct(
        DocumentManager $documentManager,
        ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        ExpiredVideoService $expiredVideoService,
        SenderService $senderService,
        TranslatorInterface $translator
    ) {
        $this->documentManager = $documentManager;
        $this->expiredVideoConfigurationService = $expiredVideoConfigurationService;
        $this->expiredVideoService = $expiredVideoService;
        $this->senderService = $senderService;
        $this->translator = $translator;
    }

    public function getAllExpiredVideosToDelete()
    {
        $now = new \DateTime();
        $now->sub(new \DateInterval('P'.$this->expiredVideoConfigurationService->getExpirationDateDaysConf().'D'));

        /** @var DocumentRepository $qb */
        $qb = $this->documentManager->getRepository(MultimediaObject::class);
        $qb = $qb->createQueryBuilder();
        $qb->field($this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(true))->exists(true);
        $qb->field($this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(true))->lte($now->format('c'));

        return $qb->getQuery()->execute();
    }

    public function removeMultimediaObject(MultimediaObject $multimediaObject): ?array
    {
        $element = [
            $multimediaObject->getId(),
            $multimediaObject->getTitle(),
            $multimediaObject->getProperty(
                $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey()
            ),
        ];

        $this->removeAllMedia($multimediaObject);
        $this->deleteOnBBDD($multimediaObject);
        $this->deleteSeries($multimediaObject->getSeries()->getId());

        return $element;
    }

    public function removeAllMedia(MultimediaObject $multimediaObject): void
    {
        $this->removeTracks($multimediaObject);
        $this->removeMaterial($multimediaObject);
        $this->removePics($multimediaObject);
    }

    public function sendAdministratorEmail(array $result)
    {
        $multimediaObjects = $this->getMultimediaObjectsWhichExpiredTomorrow();
        $this->senderService->sendNotification(
            $this->expiredVideoConfigurationService->getAdministratorEmails(),
            $this->translator->trans($this->expiredVideoConfigurationService->getDeleteEmailConfiguration()['subject']),
            $this->expiredVideoConfigurationService->getDeleteEmailConfiguration()['template'],
            [
                'subject' => $this->expiredVideoConfigurationService->getDeleteEmailConfiguration()['subject'],
                'multimedia_objects' => $result,
                'multimedia_objects_to_remove_tomorrow' => $multimediaObjects,
            ],
            false
        );
    }

    private function getMultimediaObjectsWhichExpiredTomorrow(): array
    {
        $multimediaObjects = $this->expiredVideoService->getExpiredVideosByDateAndRange(1, true);

        $result = [];
        foreach ($multimediaObjects as $multimediaObject) {
            $result[] = [
                $multimediaObject->getId(),
                $multimediaObject->getTitle(),
            ];
        }

        return $result;
    }

    private function deleteOnBBDD(MultimediaObject $multimediaObject)
    {
        /** @var DocumentRepository $repository */
        $repository = $this->documentManager->getRepository(MultimediaObject::class);

        return $repository->createQueryBuilder()
            ->remove()
            ->field('_id')->equals(new ObjectId($multimediaObject->getId()))
            ->getQuery()
            ->execute()
        ;
    }

    private function deleteSeries(string $seriesID)
    {
        $seriesMongoId = new ObjectId($seriesID);

        $multimediaObjectsFromSeries = $this->documentManager->getRepository(MultimediaObject::class)->findBy([
            'series' => $seriesMongoId,
            'status' => ['$nin' => [
                MultimediaObject::STATUS_PROTOTYPE,
            ]],
        ]);

        if (0 !== count($multimediaObjectsFromSeries)) {
            return false;
        }

        /** @var DocumentRepository $repository */
        $repository = $this->documentManager->getRepository(Series::class);

        return $repository->createQueryBuilder()
            ->remove()
            ->field('_id')->equals($seriesMongoId)
            ->getQuery()
            ->execute()
        ;
    }

    private function removeTracks(MultimediaObject $multimediaObject): void
    {
        $fileSystem = new Filesystem();
        $tracks = $multimediaObject->getTracks();

        foreach ($tracks as $track) {
            if ($track instanceof Track) {
                $isUsedOnAnotherMedia = $this->checkMedia($track);
                if (!$isUsedOnAnotherMedia && $fileSystem->exists($track->getPath())) {
                    $this->removeFileFromDisk($track->getPath());
                }
            }
        }
    }

    private function removeMaterial(MultimediaObject $multimediaObject): void
    {
        $fileSystem = new Filesystem();
        $materials = $multimediaObject->getMaterials();

        foreach ($materials as $material) {
            if ($material instanceof Material) {
                $isUsedOnAnotherMedia = $this->checkMaterial($material);
                if (!$isUsedOnAnotherMedia && $fileSystem->exists($material->getPath())) {
                    $this->removeFileFromDisk($material->getPath());
                }
            }
        }
    }

    private function removePics(MultimediaObject $multimediaObject): void
    {
        $fileSystem = new Filesystem();
        $pics = $multimediaObject->getPics();

        foreach ($pics as $pic) {
            if ($pic instanceof Pic) {
                $isUsedOnAnotherMedia = $this->checkPics($pic);
                if (!$isUsedOnAnotherMedia && $fileSystem->exists($pic->getPath())) {
                    $this->removeFileFromDisk($pic->getPath());
                }
            }
        }
    }

    private function checkMedia(Track $track): bool
    {
        $multimediaObjects = $this->documentManager->getRepository(MultimediaObject::class)->findBy([
            'tracks.path' => $track->getPath(),
        ]);

        return count($multimediaObjects) > 1;
    }

    private function checkMaterial(Material $material): bool
    {
        $multimediaObjects = $this->documentManager->getRepository(MultimediaObject::class)->findBy([
            'materials.path' => $material->getPath(),
        ]);

        return count($multimediaObjects) > 1;
    }

    private function checkPics(Pic $pic): bool
    {
        $multimediaObjects = $this->documentManager->getRepository(MultimediaObject::class)->findBy([
            'pics.path' => $pic->getPath(),
        ]);

        return count($multimediaObjects) > 1;
    }

    private function removeFileFromDisk(string $path): void
    {
        $dirname = pathinfo($path, PATHINFO_DIRNAME);

        try {
            $deleted = unlink($path);
            if (!$deleted) {
                throw new \Exception("Error deleting file '".$path."' on disk");
            }
            $finder = new Finder();
            $finder->files()->in($dirname);
            if (0 === $finder->count()) {
                $dirDeleted = rmdir($dirname);
                if (!$dirDeleted) {
                    throw new \Exception("Error deleting directory '".$dirname."'on disk");
                }
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
