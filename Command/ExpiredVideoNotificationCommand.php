<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Command;

use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoConfigurationService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoNotificationService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pumukit:expired:video:notification', description: 'Automatically sending notifications to users who have a video about to expire.')]
class ExpiredVideoNotificationCommand extends Command
{
    public function __construct(
        private readonly ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        private readonly ExpiredVideoService $expiredVideoService,
        private readonly ExpiredVideoNotificationService $expiredVideoNotificationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('days', InputArgument::REQUIRED, 'days')
            ->addArgument('range', InputArgument::REQUIRED, 'range')
            ->setHelp(
                <<<'EOT'
                    Automatic email sending to owners who have videos that expire soon.

                    Arguments:
                        days : Videos that expire in the next X days
                        range: Send email for all videos that expired from today to date that pass on option days in these command.

                    If range is false, it sends an email for the videos that expire on that day. Example:

                       php bin/console pumukit:expired:video:notification 7 false ( if today is 01/01/2018 will send to all video that expired in 08/01/2018 )

                    If range is true:

                       php bin/console pumukit:expired:video:notification 7 true  ( if today is 01/01/2018 will send to all video that expired in the range between 01/01/2018 - 08/01/2018)
                    EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getArgument('days');
        $range = 'true' === $input->getArgument('range');

        if (!$this->expiredVideoConfigurationService->isEnabled()) {
            $message = 'The expired video bundle is disabled (pumukit_expired_video.enable: false).';
            $this->generateTableWithResult($output, [], $days, $message);

            return Command::FAILURE;
        }

        $expiredVideos = $this->expiredVideoService->getExpiredVideosByDateAndRange($days, $range);
        $expiredVideos = is_array($expiredVideos) ? $expiredVideos : iterator_to_array($expiredVideos, false);
        if (!$expiredVideos) {
            $message = 'No expired video on the selected date';
            $this->generateTableWithResult($output, [], $days, $message);

            return Command::FAILURE;
        }

        $result = $this->expiredVideoNotificationService->checkAndSendNotifications($expiredVideos);

        $this->generateTableWithResult($output, $result, $days, null);

        return Command::SUCCESS;
    }

    private function generateTableWithResult(OutputInterface $output, array $elements, int $days, ?string $message): void
    {
        $date = new \DateTime(date('Y-m-d'));
        $date->add(new \DateInterval('P'.$days.'D'));

        $output->writeln([
            '',
            '<info>***** Command: Expired video notification *****</info>',
            '<comment>Criteria: </comment>',
            '<comment>    Expiration date == '.$date->format('c').'</comment>',
        ]);

        if ($message) {
            $output->writeln([
                '<comment>Result message: </comment>',
                '<info>'.$message.'</info>',
            ]);
        }

        $resultElements = [];
        foreach ($elements as $multimediaObject => $people) {
            $resultElements[] = [$multimediaObject, implode(',', $people)];
        }

        new Table($output)
            ->setHeaders(['MultimediaObject', 'People'])
            ->setRows($resultElements)
            ->render()
        ;
    }
}
