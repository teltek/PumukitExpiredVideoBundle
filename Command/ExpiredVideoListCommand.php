<?php

namespace Pumukit\ExpiredVideoBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExpiredVideoListCommand extends ContainerAwareCommand
{
    private $dm;
    private $mmobjRepo;
    private $expiredVideoService;

    protected function configure()
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

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();
        $this->mmobjRepo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        $this->expiredVideoService = $this->getContainer()->get('pumukit_expired_video.expired_video');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return null|int|void
     */
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

        if ($expiredVideos) {
            array_push($message, 'Expired videos: '.count($expiredVideos));
            foreach ($expiredVideos as $mmObj) {
                array_push($message, ' * Multimedia Object ID: '.$mmObj->getId().' - Expiration date: '.$mmObj->getProperty('expiration_date'));
            }
        } else {
            array_push($message, 'No videos expired.');
        }

        array_push($message, '');
        $output->writeln($message);
    }
}
