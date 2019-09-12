<?php

namespace Pumukit\ExpiredVideoBundle\Command;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExpiredVideoListCommand extends ContainerAwareCommand
{
    private $dm;
    private $mmobjRepo;
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
        $this->mmobjRepo = $this->dm->getRepository(MultimediaObject::class);
        $this->expiredVideoService = $this->getContainer()->get('pumukit_expired_video.expired_video');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $now = new \DateTime();

        $message = [
            '',
            '<info>Expired video list</info>',
            '<info>==================</info>',
            '<comment>Searching videos with expiration date less than '.$now->format('c').'</comment>',
        ];

        $expiredVideos = $this->expiredVideoService->getExpiredVideos();

        if ($expiredVideos) {
            $message[] = 'Expired videos: '.count($expiredVideos);
            foreach ($expiredVideos as $mmObj) {
                $message[] = ' * Multimedia Object ID: '.$mmObj->getId().' - Expiration date: '.$mmObj->getProperty('expiration_date');
            }
        } else {
            $message[] = 'No videos expired.';
        }

        $message[] = '';
        $output->writeln($message);

        return 0;
    }
}
