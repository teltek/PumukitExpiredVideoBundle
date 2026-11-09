<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Command;

use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoConfigurationService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoUpdateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pumukit:expired:video:update', description: 'This command move owners to expired owners and remove tag webtv from multimedia object if the video was expired')]
class ExpiredVideoUpdateCommand extends Command
{
    public function __construct(
        private readonly ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        private readonly ExpiredVideoService $expiredVideoService,
        private readonly ExpiredVideoUpdateService $expiredVideoUpdateService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Set this parameter force the execution of this action')
            ->setHelp(
                <<<'EOT'
                    If the video was expired this command execute the next actions
                        1. Move video owners to expired video owners
                        2. Remove tag PUCHWEBTV to unpublish the video
                        3. Send email to administrator with this actions.
                    EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $expiredVideos = $this->expiredVideoService->getExpiredVideos();
        $expiredVideos = is_array($expiredVideos) ? $expiredVideos : iterator_to_array($expiredVideos, false);

        if (!$expiredVideos) {
            $message = "There aren't expired videos.";
            $this->generateTableWithResult($output, [], $message);

            return Command::FAILURE;
        }

        $result = [];
        if (!$input->getOption('force')) {
            $message = 'The option force must be set to remove owner videos timed out';
            foreach ($expiredVideos as $multimediaObject) {
                $result[] = [
                    $multimediaObject->getId(),
                    $multimediaObject->getProperty($this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey()),
                ];
            }
            $this->generateTableWithResult($output, $result, $message);

            return Command::FAILURE;
        }

        $removedOwners = [];
        foreach ($expiredVideos as $multimediaObject) {
            $result[] = [
                $multimediaObject->getId(),
                $multimediaObject->getProperty($this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey()),
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

        return Command::SUCCESS;
    }

    private function generateTableWithResult(OutputInterface $output, array $elements, ?string $message = null): void
    {
        $output->writeln([
            '',
            '<info>***** Command: Expired video update *****</info>',
            '<comment>Criteria: </comment>',
            '<comment>    Expiration date < '.new \DateTime()->format('c').'</comment>',
        ]);

        if ($message) {
            $output->writeln([
                '<comment>Result message: </comment>',
                '<info>'.$message.'</info>',
            ]);
        }

        new Table($output)
            ->setHeaders(['MultimediaObject', 'Expiration date'])
            ->setRows($elements)
            ->render()
        ;
    }
}
