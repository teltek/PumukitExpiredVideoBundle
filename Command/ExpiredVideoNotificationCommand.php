<?php

namespace Pumukit\ExpiredVideoBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

class ExpiredVideoNotificationCommand extends ContainerAwareCommand
{
    private $dm = null;
    private $mmobjRepo = null;
    private $type = "expired";
    private $user_code;
    private $factoryService;
    private $logger;

    protected function configure()
    {
        $this
            ->setName('video:expired:notification')
            ->setDescription('Automatically sending notifications to users who have a video about to expire.')
            ->addArgument('days', InputArgument::REQUIRED, 'days')
            ->setHelp(
                <<<EOT
Automatic email sending to owners who have videos that expire soon

Arguments: 
    days : Videos that expire in the next X days
EOT
            );
    }

    private function initParameters()
    {
        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();
        $this->expiredVideoService = $this->getContainer()->get('pumukit_expired_video.notification');
        $this->user_code = $this->getContainer()->get('pumukitschema.person')->getPersonalScopeRoleCode();

        $this->mmobjRepo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initParameters();
        $days = abs(intval($input->getArgument('days')));
        if (!is_int($days)) {
            $output->writeln('Please, write an integer number');
        }

        $aMultimediaObject = $this->findExpiredVideos($days);
        $this->sendNotification($output, $aMultimediaObject);

        return;
    }

    /**
     * @param $days
     * @return mixed
     */
    private function findExpiredVideos($days)
    {
        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();
        $mmobjExpired = $this->getExpiredVideos($days);

        return $mmobjExpired;
    }

    /**
     * @param $days
     * @return mixed
     */
    private function getExpiredVideos($days)
    {
        $now = new \DateTime();
        $now->add(new \DateInterval('P'.$days.'D'));
        $now = $now->format('c');

        return $this->mmobjRepo->createQueryBuilder()
            ->field('properties.expiration_date')->exists(true)
            ->field('properties.expiration_date')->lte($now)
            ->getQuery()
            ->execute();
    }

    /**
     * @param OutputInterface $output
     * @param $aMultimediaObject array of multimedia objects
     */
    private function sendNotification(OutputInterface $output, $aMultimediaObject)
    {
        if ($aMultimediaObject) {

            foreach ($aMultimediaObject as $mmObj) {

                $output->writeln('Expired Video ====> Multimedia Object ID - '.$mmObj->getId());

                if (count($mmObj->getPeopleByRoleCod($this->user_code, true)) > 0) {

                    foreach ($mmObj->getPeopleByRoleCod($this->user_code, true) as $person) {
                        if ($person->getEmail()) {
                            $sendMail[$person->getId()]['videos'][] = $mmObj->getId();
                            $sendMail[$person->getId()]['email'] = $person->getEmail();
                        }
                    }
                } else {
                    $output->writeln("There aren't owners on this video");
                }
            }

            if ($sendMail) {
                try {
                    $this->expiredVideoService->generateNotification($sendMail, $this->type);
                } catch (\Exception $e) {
                    $output->writeln('<error>'.$e->getMessage().'</error>');
                }
            } else {
                $output->writeln("There aren't user emails to send");
            }
        } else {
            $output->writeln('No videos expired in this range');
        }
    }
}