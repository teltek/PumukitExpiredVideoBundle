<?php

namespace Pumukit\ExpiredVideoBundle\Command;

use Pumukit\SchemaBundle\Document\EmbeddedPerson;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExpiredVideoNotificationCommand extends ContainerAwareCommand
{
    public const EXPIRED_VIDEO_TYPE = 'expired';
    private $dm;
    private $user_code;
    private $expiredVideoService;
    private $days;

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
        $personService = $this->getContainer()->get('pumukitschema.person');
        $this->user_code = $personService->getPersonalScopeRoleCode();
        $this->days = (int) $this->getContainer()->getParameter('pumukit_expired_video.expiration_date_days');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $message = [
            '',
            '<info>Expired video notification</info>',
            '<info>==========================</info>',
        ];

        if (0 === $this->days) {
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
    }

    private function sendNotification(OutputInterface $output, iterable $aMultimediaObject): void
    {
        if (!$aMultimediaObject) {
            $output->writeln('No videos expired in this range');

            return;
        }
        foreach ($aMultimediaObject as $multimediaObject) {
            $sendMail = $this->getEmailsToNotificationFromMultimediaObject($output, $multimediaObject);
        }

        if (empty($sendMail)) {
            $output->writeln("There aren't user emails to send");

            return;
        }

        try {
            $this->expiredVideoService->generateNotification($sendMail, self::EXPIRED_VIDEO_TYPE);
        } catch (\Exception $exception) {
            $output->writeln('<error>'.$exception->getMessage().'</error>');
        }
    }

    private function getEmailsToNotificationFromMultimediaObject(OutputInterface $output, MultimediaObject $multimediaObject): array
    {
        $output->writeln(' * Multimedia Object ID: '.$multimediaObject->getId().' - Expiration date: '.$multimediaObject->getProperty('expiration_date'));

        $sendMail = [];
        $people = $multimediaObject->getPeopleByRoleCod($this->user_code, true);

        if (0 === count($people)) {
            $output->writeln("There aren't owners on this video");

            return $sendMail;
        }

        foreach ($people as $person) {
            $sendMail[] = $this->getPersonEmail($person, $multimediaObject);
        }

        return $sendMail;
    }

    private function getPersonEmail(EmbeddedPerson $person, MultimediaObject $multimediaObject): ?array
    {
        $personEmail = $person->getEmail();
        if (!$personEmail) {
            return null;
        }

        $personId = $person->getId();

        $sendMail[$personId]['videos'][] = $multimediaObject->getId();
        $sendMail[$personId]['email'] = $personEmail;

        return $sendMail;
    }
}
