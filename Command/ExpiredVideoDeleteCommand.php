<?php

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
    private $mmobjRepo;
    private $days;
    private $user_code;
    private $seriesRepo;
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
        $this->mmobjRepo = $this->dm->getRepository(MultimediaObject::class);
        $this->seriesRepo = $this->dm->getRepository(Series::class);
        $this->user_code = $this->getContainer()->get('pumukitschema.person')->getPersonalScopeRoleCode();
        $this->days = (int) $this->getContainer()->getParameter('pumukit_expired_video.expiration_date_days');
        $this->expiredVideoService = $this->getContainer()->get('pumukit_expired_video.expired_video');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        if ($input->getOption('force')) {
            if (0 === $this->days) {
                $output->writeln('Expiration date days is 0, it means deactivate expired video functionality.');

                return null;
            }

            $mmobjExpired = $this->expiredVideoService->getExpiredVideosToDelete($this->days);
            $i = 0;
            if ($mmobjExpired) {
                foreach ($mmobjExpired as $mmObj) {
                    if (!$mmObj->getPeopleByRoleCod($this->user_code, true) || empty($mmObj->getPeopleByRoleCod($this->user_code, true))) {
                        $output->writeln('Delete Multimedia Object ID - '.$mmObj->getId());
                        $result = $this->deleteVideos($mmObj);

                        if (0 === count($mmObj->getSeries()->getMultimediaObjects())) {
                            $this->deleteSeries($mmObj->getSeries()->getId());
                        }

                        $output->writeln('Status remove - '.$result['ok']);
                        if ($result['errmsg'] && $result['err']) {
                            $output->writeln('errmsg - '.$result['errmsg']);
                            $output->writeln('err' - $result['err']);
                        } else {
                            ++$i;
                        }
                    } else {
                        $output->writeln('This video '.$mmObj->getId()." can't be delete because there are owners");
                    }
                }
                $output->writeln('Total delete count: '.$i);
            } else {
                $output->writeln('No videos to delete.');
            }
        } else {
            $output->writeln('The option force must be set to delete videos timed out');
        }

        return 0;
    }

    private function deleteVideos(MultimediaObject $mmObj)
    {
        return $this->mmobjRepo->createQueryBuilder()
            ->remove()
            ->field('_id')->equals(new \MongoId($mmObj->getId()))
            ->getQuery()
            ->execute()
        ;
    }

    private function deleteSeries(string $sSeriesId)
    {
        return $this->seriesRepo->createQueryBuilder()
            ->remove()
            ->field('_id')->equals(new \MongoId($sSeriesId))
            ->getQuery()
            ->execute()
        ;
    }
}
