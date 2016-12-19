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

    private $factoryService;
    private $logger;

    private $user_code = 'owner';

    protected function configure()
    {
        $this
            ->setName('video:expired:notification')
            ->setDescription('Automatically sending notifications to users who have a video about to expire.')
            ->addArgument('days', InputArgument::REQUIRED, 'days')
            ->setHelp(<<<EOT
Automatically sending notifications to users who have a video about to expire
EOT
            );
    }

    private function initParameters()
    {
        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();
        $this->expiredVideoService = $this->getContainer()->get('pumukit_expired_video.notification');

        $this->mmobjRepo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initParameters();
        $days = intval($input->getArgument('days'));
        if(!is_int($days)) {
            $output->writeln('Please, write an integer number');
        }

        $mmObj = $this->findExpiredVideos($days);

        $this->getOwnerEmails($output, $mmObj);

        return;
    }

    private function findExpiredVideos($days)
    {
        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();
        $mmobjExpired = $this->getExpiredVideos($days);

        return $mmobjExpired;
    }

    private function getOwnerEmails(OutputInterface $output, $mmobjExpired)
    {
        if($mmobjExpired) {
            foreach ($mmobjExpired as $mmObj) {
                $sendMail = array('pablo.ogando@teltek.es');
                foreach($mmObj->getPeopleByRoleCod($this->user_code) as $person) {
                    if($person->getEmail()) {
                        //$sendMail[] = $person->getEmail();
                        $sendMail = array('pablo.ogando@teltek.es');
                    } else {
                        $sendMail = array('pablo.ogando@teltek.es');
                    }
                }

                $output->writeln(' ***** Send notification email for ****** ' );
                $output->writeln('Multimedia Object ID - ' . $mmObj->getId());
                $output->writeln('Owners count - ' . count($sendMail));
                try {
                    $this->expiredVideoService->generateNotification($sendMail);
                } catch(\Exception $e) {
                    $output->writeln('<error>' . $e->getMessage() . '</error>');
                }
            }
            $output->writeln('Total multimedia object count: ' . count($mmobjExpired));
        } else {
            $output->writeln('No videos expired in this range');
        }
    }

    private function getExpiredVideos($days)
    {
        $now = new \DateTime();
        $now->sub(new \DateInterval('P' . $days . 'D'));

        return $this->mmobjRepo->createQueryBuilder()
            ->field('properties.expiration_date')->exists(true)
            ->field('properties.expiration_date')->gte($now)
            ->getQuery()
            ->execute();
    }
}