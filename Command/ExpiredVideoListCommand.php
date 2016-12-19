<?php

namespace Pumukit\ExpiredVideoBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

class ExpiredVideoListCommand extends ContainerAwareCommand
{
    private $dm = null;
    private $mmobjRepo = null;

    private $factoryService;
    private $logger;

    protected function configure()
    {
        $this
            ->setName('video:expired:list')
            ->setDescription('Expired video list')
            ->setHelp(<<<EOT
Expired video list
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initParameters();

        $mmobjExpired = $this->getExpiredVideos();
        if($mmobjExpired) {
            foreach ($mmobjExpired as $mmObj) {
                $output->writeln('Multimedia Object ID - ' . $mmObj->getId());
            }

            $output->writeln('Total count: ' . count($mmobjExpired));
        } else {
            $output->writeln('No videos expired.');
        }
    }

    private function initParameters()
    {
        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();
        $this->mmobjRepo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
    }

    private function getExpiredVideos()
    {
        $now = new \DateTime();
        return $this->mmobjRepo->createQueryBuilder()
            ->field('properties.expiration_date')->exists(true)
            ->field('properties.expiration_date')->lte($now)
            ->getQuery()
            ->execute();
    }
}