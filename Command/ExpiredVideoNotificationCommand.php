<?php

namespace Pumukit\ExpiredVideoBundle\Command;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExpiredVideoNotificationCommand extends ContainerAwareCommand
{
    private $dm;
    private $mmobjRepo;
    private $type = 'expired';
    private $user_code;
    private $expiredVideoService;
    private $days;
    private $output;

    protected function configure(): void
    {
        $this
            ->setName('video:expired:notification')
            ->setDescription('Automatically sending notifications to users who have a video about to expire.')
            ->addArgument('days', InputArgument::REQUIRED, 'days')
            ->addArgument('range', InputArgument::REQUIRED, 'range')
            ->setHelp(
                <<<'EOT'
Automatic email sending to owners who have videos that expire soon.

Arguments: 
    days : Videos that expire in the next X days
    range: Send email for all videos that expired from today to date that pass on option days in these command.
    
If only use days option, will send email for all users that have video expired in this day. Example:

   php app/console video:expired:notification 7 ( if today is 01/01/2018 will send to all video that expired in 08/01/2018 )
   
If use range days:

   php app/console video:expired:notification 7 true  ( if today is 01/01/2018 will send to all video that expired in the range between 01/01/2018 - 08/01/2018)
   
EOT
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->dm = $this->getContainer()->get('doctrine_mongodb.odm.document_manager');
        $this->expiredVideoService = $this->getContainer()->get('pumukit_expired_video.expired_video');
        $this->user_code = $this->getContainer()->get('pumukitschema.person')->getPersonalScopeRoleCode();
        $this->days = $this->getContainer()->getParameter('pumukit_expired_video.expiration_date_days');
        $this->mmobjRepo = $this->dm->getRepository(MultimediaObject::class);
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $message = [
            '',
            '<info>Expired video notification</info>',
            '<info>==========================</info>',
        ];

        if (0 === (int) $this->days) {
            $message[] = '<error>Expiration date days is 0, it means deactivate expired video functionality.</error>';
            $output->writeln($message);

            return null;
        }

        $days = (int) $input->getArgument('days');
        $range = 'true' === $input->getArgument('range');

        $expiredVideos = $this->expiredVideoService->getExpiredVideosByDateAndRange($days, $range);

        if (!$expiredVideos) {
            $date = new \DateTime(date('Y-m-d'));
            $date->add(new \DateInterval('P'.$days.'D'));
            $date = $date->format('Y-m-d H:i:s');
            $message[] = 'No videos to expired on date '.$date;
            $output->writeln($message);

            return null;
        }

        $message[] = '<comment>Expired videos to notify: '.count($expiredVideos).'</comment>';

        $output->writeln($message);

        $this->sendNotification($output, $expiredVideos);

        $output->writeln('');

        return 0;
    }

    private function sendNotification(OutputInterface $output, iterable $aMultimediaObject): void
    {
        $sendMail = [];
        if ($aMultimediaObject) {
            foreach ($aMultimediaObject as $mmObj) {
                $output->writeln(' * Multimedia Object ID: '.$mmObj->getId().' - Expiration date: '.$mmObj->getProperty('expiration_date'));

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
