<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Command;

use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoConfigurationService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoUpdateService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExpiredVideoUpdateCommand extends ContainerAwareCommand
{
    private $expiredVideoConfigurationService;
    private $expiredVideoService;
    private $expiredVideoUpdateService;

    public function __construct(
        ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        ExpiredVideoService $expiredVideoService,
        ExpiredVideoUpdateService $expiredVideoUpdateService
    ) {
        $this->expiredVideoConfigurationService = $expiredVideoConfigurationService;
        $this->expiredVideoService = $expiredVideoService;
        $this->expiredVideoUpdateService = $expiredVideoUpdateService;
    }

    protected function configure(): void
    {
        $this
            ->setName('video:expired:update')
            ->setDescription('This command move owners to expired owners and remove tag webtv from multimedia object if the video was expired')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Set this parameter force the execution of this action')
            ->setHelp(
                <<<'EOT'
Video Expired Update Command:

If the video was expired this command execute the next actions
    1. Move video owners to expired video owners
    2. Remove tag PUCHWEBTV to unpublish the video
    3. Send email to administrator with this actions.
EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->expiredVideoConfigurationService->isDeactivatedService()) {
            $message = 'Expiration date days is 0, it means deactivate expired video functionality.';
            $this->generateTableWithResult($output, [], $message);

            return -1;
        }

        $expiredVideos = $this->expiredVideoService->getExpiredVideos();

        if (!$expiredVideos) {
            $message = "There aren't expired videos.";
            $this->generateTableWithResult($output, [], $message);

            return -1;
        }

        $result = [];
        if (!$input->getOption('force')) {
            $message = 'The option force must be set to remove owner videos timed out';
            foreach ($expiredVideos as $multimediaObject) {
                $result[] = [
                    $multimediaObject->getId(),
                    $multimediaObject->getProperty(
                        $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey()
                    ),
                ];
            }
            $this->generateTableWithResult($output, $result, $message);

            return -1;
        }

        $removedOwners = [];
        foreach ($expiredVideos as $multimediaObject) {
            $result[] = [
                $multimediaObject->getId(),
                $multimediaObject->getProperty(
                    $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey()
                ),
            ];

            $wasOwnerRemoved = $this->expiredVideoUpdateService->removeOwners($multimediaObject);
            if (!empty($wasOwnerRemoved)) {
                $removedOwners[] = $wasOwnerRemoved;
            }
            $this->expiredVideoUpdateService->removeTag($multimediaObject);
        }

        if (!empty($removedOwners)) {
            $this->expiredVideoUpdateService->sendAdministratorEmail($removedOwners);
        }

        $this->generateTableWithResult($output, $result);

        return 0;
    }

    private function generateTableWithResult(OutputInterface $output, array $elements, ?string $message = null): void
    {
        $date = new \DateTime();

        $output->writeln([
            '',
            '<info>***** Command: Expired video update *****</info>',
            '<comment>Criteria: </comment>',
            '<comment>    Expiration date < '.$date->format('c').'</comment>',
        ]);

        if ($message) {
            $output->writeln([
                '<comment>Result message: </comment>',
                '<info>'.$message.'</info>',
            ]);
        }

        $table = new Table($output);
        $table
            ->setHeaders(['MultimediaObject', 'Expiration date'])
            ->setRows($elements)
        ;
        $table->render();
    }
}
