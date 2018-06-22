<?php

namespace Pumukit\ExpiredVideoBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

class ExpiredVideoDeleteCommand extends ContainerAwareCommand
{
    private $dm = null;
    private $mmobjRepo = null;
    private $days;
    private $user_code;

    protected function configure()
    {
        $this
            ->setName('video:expired:delete')
            ->setDescription('Expired video delete')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Set this parameter force the execution of this action')
            ->setHelp(<<<'EOT'
This command delete all the videos without owner people when this expiration_date is less than a year ago.
EOT
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();
        $this->mmobjRepo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        $this->seriesRepo = $this->dm->getRepository('PumukitSchemaBundle:Series');
        $this->user_code = $this->getContainer()->get('pumukitschema.person')->getPersonalScopeRoleCode();
        $this->days = $this->getContainer()->getParameter('pumukit_expired_video.expiration_date_days');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('force')) {
            if (0 === $this->days) {
                $output->writeln('Expiration date days is 0, it means deactivate expired video functionality.');

                return;
            }

            $mmobjExpired = $this->getDeleteExpiredVideos($this->days);
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
    }

    /**
     * @param $days
     *
     * @return mixed
     * @throws \Exception
     */
    private function getDeleteExpiredVideos($days)
    {
        $now = new \DateTime();
        $now->sub(new \DateInterval('P'.$days.'D'));

        $qb = $this->mmobjRepo->createQueryBuilder();
        $qb->field('properties.expiration_date')->exists(true);
        $qb->field('properties.expiration_date')->lte($now->format('c'));

        return $qb->getQuery()->execute();
    }

    /**
     * @param $mmObj
     *
     * @return mixed
     */
    private function deleteVideos($mmObj)
    {
        return $this->mmobjRepo->createQueryBuilder()
            ->remove()
            ->field('_id')->equals(new \MongoId($mmObj->getId()))
            ->getQuery()
            ->execute();
    }

    /**
     * @param $sSeriesId
     *
     * @return mixed
     */
    private function deleteSeries($sSeriesId)
    {
        return $this->seriesRepo->createQueryBuilder()
            ->remove()
            ->field('_id')->equals(new \MongoId($sSeriesId))
            ->getQuery()
            ->execute();
    }
}
