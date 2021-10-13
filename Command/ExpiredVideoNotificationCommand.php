<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Command;

use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoConfigurationService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoNotificationService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExpiredVideoNotificationCommand extends ContainerAwareCommand
{
    private $expiredVideoConfigurationService;
    private $expiredVideoService;
    private $expiredVideoNotificationService;

    public function __construct(
        ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        ExpiredVideoService $expiredVideoService,
        ExpiredVideoNotificationService $expiredVideoNotificationService
    ) {
        $this->expiredVideoConfigurationService = $expiredVideoConfigurationService;
        $this->expiredVideoService = $expiredVideoService;
        $this->expiredVideoNotificationService = $expiredVideoNotificationService;
    }

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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $days = (int) $input->getArgument('days');
        $range = 'true' === $input->getArgument('range');

        if ($this->expiredVideoConfigurationService->isDeactivatedService()) {
            $message = 'Expiration date days is 0, it means deactivate expired video functionality.';
            $this->generateTableWithResult($output, [], $days, $message);

            return -1;
        }

        $expiredVideos = $this->expiredVideoService->getExpiredVideosByDateAndRange($days, $range);
        if (!$expiredVideos || 0 === count($expiredVideos)) {
            $message = 'No expired video on the selected date';
            $this->generateTableWithResult($output, [], $days, $message);

            return -1;
        }

        $result = $this->expiredVideoNotificationService->checkAndSendNotifications($expiredVideos);

        $this->generateTableWithResult($output, $result, $days, null);

        return 0;
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
            $resultElements[] = [
                $multimediaObject,
                implode(',', $people),
            ];
        }

        $table = new Table($output);
        $table
            ->setHeaders(['MultimediaObject', 'People'])
            ->setRows($resultElements)
        ;
        $table->render();
    }
}
