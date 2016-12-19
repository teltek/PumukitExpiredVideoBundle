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
    private $days = 365;

    private $factoryService;
    private $logger;

    protected function configure()
    {
        $this
            ->setName('video:expired:delete')
            ->setDescription('Expired video list')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Set this parameter force the execution of this action')
            ->setHelp(<<<EOT
Expired video list
EOT
            );
    }

    private function initParameters()
    {
        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();
        $this->mmobjRepo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initParameters();

        if($input->getOption('force')) {

            $mmobjExpired = $this->getDeleteExpiredVideos($this->days);

            if ($mmobjExpired) {
                foreach ($mmobjExpired as $mmObj) {
                    $output->writeln('Delete Multimedia Object ID - '.$mmObj->getId());
                    //$result = $this->deleteVideos($mmObj);
                    //$output->writeln('Status remove - ' . $result);
                }
                $output->writeln('Total delete count: '.count($mmobjExpired));
            } else {
                $output->writeln('No videos to delete.');
            }
        } else {
            $output->writeln('The option force must be set to delete videos timed out');
        }
    }

    private function getDeleteExpiredVideos($days)
    {
        $now = new \DateTime();
        $now->sub(new \DateInterval('P' . $days . 'D'));

        return $this->createQueryBuilder()
            ->field('properties.expiration_date')->exists(true)
            ->field('properties.expiration_date')->lte($now)
            ->getQuery()
            ->execute();
    }

    private function deleteVideos($mmObj)
    {
        return $this->createQueryBuilder()
            ->remove()
            ->field('_id')->equals(new \MongoId($mmObj->getId()))
            ->getQuery()
            ->execute();
    }
}