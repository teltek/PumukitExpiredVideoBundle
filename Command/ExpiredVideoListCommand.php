<?php

namespace Pumukit\ExpiredVideoBundle\Command;


use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExpiredVideoListCommand extends ContainerAwareCommand
{

    /** @var DocumentManager */
    private $dm;

    /** @var ExpiredVideoService */
    private $expiredVideoService;

    protected function configure(): void
    {
        $this
            ->setName('video:expired:list')
            ->setDescription('Expired video list')
            ->setHelp(
                <<<'EOT'
            
Expired video list returns a list of multimedia object ID and his expiration date when the expiration_date is less than now.

Example:

php app/console video:expired:list

EOT
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->dm = $this->getContainer()->get('doctrine_mongodb.odm.document_manager');
        $this->expiredVideoService = $this->getContainer()->get('pumukit_expired_video.expired_video');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $now = new \DateTime();

        $message = [
            '',
            '<info>Expired video list</info>',
            '<info>==================</info>',
            '<comment>Searching videos with expiration date less than '.$now->format('c').'</comment>',
        ];

        $expiredVideos = $this->expiredVideoService->getExpiredVideos();

        if (!$expiredVideos) {
            $output->writeln('No videos expired.');

            return;
        }

        $message[] = 'Expired videos: '.count($expiredVideos);
        foreach ($expiredVideos as $multimediaObject) {
            $message[] = $this->generateMessageByMultimediaObject($multimediaObject);
        }

        $message[] = '';
        $output->writeln($message);
    }

    private function generateMessageByMultimediaObject(MultimediaObject $multimediaObject): string
    {
        return ' * Multimedia Object ID: '.$multimediaObject->getId().' - Expiration date: '.$multimediaObject->getProperty('expiration_date');
    }
}
