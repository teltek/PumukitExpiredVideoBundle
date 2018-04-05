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
    private $type = 'expired';
    private $user_code;
    private $expiredVideoService;
    private $days;

    protected function configure()
    {
        $this
            ->setName('video:expired:notification')
            ->setDescription('Automatically sending notifications to users who have a video about to expire.')
            ->addArgument('days', InputArgument::REQUIRED, 'days')
            ->addArgument('range', InputArgument::REQUIRED, 'range')
            ->setHelp(
                <<<'EOT'
Automatic email sending to owners who have videos that expire soon

Arguments: 
    days : Videos that expire in the next X days
    range: Send email for all videos that expired from today to date that pass on option days in these command.
    
If only use days option, will send email for all users that have video expired in this day. Example:

   php app/console video:expired:notification 7 ( if today is 01/01/2018 will send to all video that expired in 08/01/2018 )
   
If use range days:

   php app/console video:expired:notification 7 true  ( if today is 01/01/2018 will send to all video that expired in the range beetween  01/01/2018 - 08/01/2018)
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
        $this->expiredVideoService = $this->getContainer()->get('pumukit_expired_video.notification');
        $this->user_code = $this->getContainer()->get('pumukitschema.person')->getPersonalScopeRoleCode();
        $this->days = $this->getContainer()->getParameter('pumukit_expired_video.expiration_date_days');
        $this->mmobjRepo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (0 == $this->days) {
            $output->writeln('Expiration date days is 0, it means deactivate expired video functionality.');

            return;
        }

        if (!is_int($input->getArgument('days'))) {
            throw new \Exception('Please, write an integer number');
        }

        $days = $input->getArgument('days');
        $range = $input->getArgument('range');

        $aMultimediaObject = $this->findExpiredVideos($days, $range);

        if (count($aMultimediaObject) > 0) {
            $this->sendNotification($output, $aMultimediaObject);
        } else {
            $date = new \DateTime(date('Y-m-d'));
            $date->add(new \DateInterval('P'.$days.'D'));
            $date = $date->format('Y-m-d H:i:s');
            $output->writeln('No videos to expired on date '.$date);
        }

        return;
    }

    /**
     * @param $days
     * @param $range
     *
     * @return mixed
     * @throws \Exception
     */
    private function findExpiredVideos($days, $range)
    {
        $mmobjExpired = $this->getExpiredVideos($days, $range);

        return $mmobjExpired;
    }

    /**
     * @param $days
     * @param $range
     *
     * @return mixed
     * @throws \Exception
     */
    private function getExpiredVideos($days, $range)
    {
        $date = new \DateTime(date('Y-m-d'));
        $date->add(new \DateInterval('P'.$days.'D'));
        $date = $date->format('Y-m-d H:i:s');

        $qb = $this->mmobjRepo->createQueryBuilder()
            ->field('properties.expiration_date')->exists(true);

        if ('false' === $range) {
            $oTomorrow = new \DateTime(date('Y-m-d'));
            $oTomorrow->add(new \DateInterval('P'.($days + 1).'D'));
            $oTomorrow = $oTomorrow->format('Y-m-d H:i:s');
            $qb->field('properties.expiration_date')->equals(array('$gte' => $date, '$lt' => $oTomorrow));
        } else {
            $qb->field('properties.expiration_date')->equals(array('$gte' => new \DateTime(date('Y-m-d')), '$lte' => $date));
        }

        return $qb->getQuery()->execute();
    }

    /**
     * @param OutputInterface $output
     * @param $aMultimediaObject array of multimedia objects
     */
    private function sendNotification(OutputInterface $output, $aMultimediaObject)
    {
        $sendMail = array();
        if ($aMultimediaObject) {
            foreach ($aMultimediaObject as $mmObj) {
                $output->writeln('Expired Video ====> Multimedia Object ID - '.$mmObj->getId());

                $people = $mmObj->getPeopleByRoleCod($this->user_code, true);
                if (count($people) > 0) {
                    foreach ($people as $person) {
                        if ($person->getEmail()) {
                            $sendMail[$person->getId()]['videos'][] = $mmObj->getId();
                            $sendMail[$person->getId()]['email'] = $person->getEmail();
                        }
                    }
                } else {
                    $output->writeln("There aren't owners on this video");
                }
            }

            if (!empty($sendMail)) {
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
