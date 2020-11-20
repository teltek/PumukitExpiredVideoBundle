<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Command;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExpiredVideoDeleteCommand extends ContainerAwareCommand
{
    private $dm;
    private $days;
    private $user_code;
    private $expiredVideoService;

    protected function configure(): void
    {
        $this
            ->setName('video:expired:delete')
            ->setDescription('Expired video delete')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Set this parameter force the execution of this action')
            ->setHelp(
                <<<'EOT'
This command delete all the videos without owner people when this expiration_date is less than a year ago.
EOT
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->dm = $this->getContainer()->get('doctrine_mongodb.odm.document_manager');
        $personService = $this->getContainer()->get('pumukitschema.person');
        $this->user_code = $personService->getPersonalScopeRoleCode();
        $this->days = (int) $this->getContainer()->getParameter('pumukit_expired_video.expiration_date_days');
        $this->expiredVideoService = $this->getContainer()->get('pumukit_expired_video.expired_video');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('force')) {
            $output->writeln('The option force must be set to delete videos timed out');

            return -1;
        }

        if (0 === $this->days) {
            $output->writeln('Expiration date days is 0, it means deactivate expired video functionality.');

            return -1;
        }

        $multimediaObjectsExpired = $this->expiredVideoService->getExpiredVideosToDelete($this->days);

        if (!$multimediaObjectsExpired) {
            $output->writeln('No videos to delete.');

            return -1;
        }

        $this->removeExpiredVideos($output, $multimediaObjectsExpired);

        return 0;
    }

    private function executeRemoveOnVideo(OutputInterface $output, MultimediaObject $multimediaObject, int $counter): int
    {
        $result = $this->deleteVideos($multimediaObject);

        $seriesId = $multimediaObject->getSeries()->getId();
        $this->deleteSeries($seriesId);

        $output->writeln('Status remove - '.$result['ok']);

        if ($result['errmsg'] && $result['err']) {
            $output->writeln('errmsg - '.$result['errmsg']);
            $output->writeln('err' - $result['err']);
        } else {
            ++$counter;
        }

        return $counter;
    }

    private function removeExpiredVideos(OutputInterface $output, iterable $multimediaObjects): void
    {
        $i = 0;
        foreach ($multimediaObjects as $multimediaObject) {
            $canBeDelete = $this->validateVideoToRemove($output, $multimediaObject);
            if (!$canBeDelete) {
                $output->writeln('This video '.$multimediaObject->getId()." can't be delete because there are owners");
            }

            $output->writeln('Delete Multimedia Object ID - '.$multimediaObject->getId());
            $this->executeRemoveOnVideo($output, $multimediaObject, $i);
        }

        $output->writeln('Total delete count: '.$i);
    }

    private function validateVideoToRemove(OutputInterface $output, MultimediaObject $multimediaObject): bool
    {
        return !$multimediaObject->getPeopleByRoleCod($this->user_code, true) || empty($multimediaObject->getPeopleByRoleCod($this->user_code, true));
    }

    private function deleteVideos(MultimediaObject $mmObj)
    {
        return $this->dm->getRepository(MultimediaObject::class)->createQueryBuilder()
            ->remove()
            ->field('_id')->equals(new \MongoId($mmObj->getId()))
            ->getQuery()
            ->execute()
        ;
    }

    private function deleteSeries(string $sSeriesId)
    {
        $seriesMongoId = new \MongoId($sSeriesId);

        $multimediaObjectsFromSeries = $this->dm->getRepository(MultimediaObject::class)->findBy([
            'series' => $seriesMongoId,
            'status' => ['$nin' => [
                MultimediaObject::STATUS_PROTOTYPE,
            ]],
        ]);

        if (0 !== count($multimediaObjectsFromSeries)) {
            return false;
        }

        return $this->dm->getRepository(Series::class)->createQueryBuilder()
            ->remove()
            ->field('_id')->equals($seriesMongoId)
            ->getQuery()
            ->execute()
            ;
    }
}
