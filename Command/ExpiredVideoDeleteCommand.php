<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Command;

use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoConfigurationService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoDeleteService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExpiredVideoDeleteCommand extends Command
{
    private $expiredVideoConfigurationService;
    private $expiredVideoDeleteService;

    public function __construct(
        ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        ExpiredVideoDeleteService $expiredVideoDeleteService
    ) {
        $this->expiredVideoConfigurationService = $expiredVideoConfigurationService;
        $this->expiredVideoDeleteService = $expiredVideoDeleteService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('pumukit:expired:video:delete')
            ->setDescription('Expired video delete')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Set this parameter force the execution of this action')
            ->setHelp(
                <<<'EOT'
This command delete all the videos without owner people when this expiration_date is less than a the configured max time days.
EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->expiredVideoConfigurationService->isDeactivatedService()) {
            $message = 'Expiration date days is 0, it means deactivate expired video functionality.';
            $this->generateTableWithResult($output, [], $message);

            return -1;
        }

        $multimediaObjectsExpired = $this->expiredVideoDeleteService->getAllExpiredVideosToDelete();
        if (!$multimediaObjectsExpired) {
            $message = "There aren't expired video to delete";
            $this->generateTableWithResult($output, [], $message);

            return -1;
        }

        $result = [];
        foreach ($multimediaObjectsExpired as $multimediaObject) {
            $result[] = $this->expiredVideoDeleteService->removeMultimediaObject($multimediaObject);
        }

        if (!$input->getOption('force')) {
            $message = 'The option force must be set to delete expired videos';
            $this->generateTableWithResult($output, $result, $message);

            return -1;
        }

        if (!empty($result)) {
            $this->expiredVideoDeleteService->sendAdministratorEmail($result);
        }

        $this->generateTableWithResult($output, $result);

        return 0;
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

        $table = new Table($output);
        $table
            ->setHeaders(['MultimediaObjectID', 'Multimedia Object Title', 'Multimedia Object Expiration Date'])
            ->setRows($elements)
        ;
        $table->render();
    }
}
