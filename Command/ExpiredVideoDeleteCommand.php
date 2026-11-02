<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Command;

use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoConfigurationService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoDeleteService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pumukit:expired:video:delete', description: 'Expired video delete')]
class ExpiredVideoDeleteCommand extends Command
{
    public function __construct(
        private readonly ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        private readonly ExpiredVideoDeleteService $expiredVideoDeleteService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Set this parameter force the execution of this action')
            ->setHelp(
                <<<'EOT'
                    Deletes the multimedia objects whose expiration date is older than expiration_date_days. Without --force it only lists them.
                    EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->expiredVideoConfigurationService->isDeactivatedService()) {
            $message = 'Expiration date days is 0, it means deactivate expired video functionality.';
            $this->generateTableWithResult($output, [], $message);

            return Command::FAILURE;
        }

        $multimediaObjectsExpired = $this->expiredVideoDeleteService->getAllExpiredVideosToDelete();
        if (!$multimediaObjectsExpired) {
            $message = "There aren't expired video to delete";
            $this->generateTableWithResult($output, [], $message);

            return Command::FAILURE;
        }

        if (!$input->getOption('force')) {
            $message = 'The option force must be set to delete expired videos';
            $this->generateTableWithResult($output, array_map($this->expiredVideoDeleteService->describe(...), $multimediaObjectsExpired), $message);

            return Command::FAILURE;
        }

        $result = [];
        foreach ($multimediaObjectsExpired as $multimediaObject) {
            $result[] = $this->expiredVideoDeleteService->removeMultimediaObject($multimediaObject);
        }

        $this->expiredVideoDeleteService->sendAdministratorEmail($result);

        $this->generateTableWithResult($output, $result);

        return Command::SUCCESS;
    }

    private function generateTableWithResult(OutputInterface $output, array $elements, ?string $message = null): void
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval('P'.$this->expiredVideoConfigurationService->getExpirationDateDaysConf().'D'));

        $output->writeln([
            '',
            '<info>***** Command: Expired video delete *****</info>',
            '<comment>Criteria: </comment>',
            '<comment>    Expiration date < '.$date->format('c').'</comment>',
        ]);

        if ($message) {
            $output->writeln([
                '<comment>Result message: </comment>',
                '<info>'.$message.'</info>',
            ]);
        }

        new Table($output)
            ->setHeaders(['MultimediaObjectID', 'Multimedia Object Title', 'Multimedia Object Expiration Date'])
            ->setRows($elements)
            ->render()
        ;
    }
}
