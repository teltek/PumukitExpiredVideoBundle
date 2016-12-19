<?php

namespace Pumukit\ExpiredVideoBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoService;

class ExpiredVideoRemoveOwnerCommand extends ContainerAwareCommand
{
    private $dm = null;
    private $mmobjRepo = null;

    private $factoryService;
    private $logger;

    protected function configure()
    {
        $this
            ->setName('video:expired:remove')
            ->setDescription('This command delete role owner when the video was timed out')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Set this parameter force the execution of this action')
            ->setHelp(<<<EOT
Expired video list
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if($input->getOption('force')) {
            $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();

            $this->mmobjRepo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
            $mmobjExpired = $this->mmobjRepo->getExpiredVideos();

            if ($mmobjExpired) {
                foreach ($mmObj->getPeopleByRoleCod($this->user_code) as $mmObj) {
                    // Remove owner
                }
            } else {
                $output->writeln('No videos timed out.');
            }
        } else {
            $output->writeln('The option force must be set to remove owner videos timed out');
        }
    }
}