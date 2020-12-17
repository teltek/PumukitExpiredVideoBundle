<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExpiredVideoDeleteCommand extends ContainerAwareCommand
{
    private $expiredVideoDeleteService;
    private $expiredVideoConfigurationService;

    protected function configure(): void
    {
        $this
            ->setName('video:expired:delete')
            ->setDescription('Expired video delete')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Set this parameter force the execution of this action')
            ->setHelp(
                <<<'EOT'
This command delete all the videos without owner people when this expiration_date is less than a the configured max time days.
EOT
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->expiredVideoDeleteService = $this->getContainer()->get('pumukit_expired_video.delete');
        $this->expiredVideoConfigurationService = $this->getContainer()->get('pumukit_expired_video.configuration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('force')) {
            $message = 'The option force must be set to delete expired videos';
            $this->generateTableWithResult($output, [], $message);

            return -1;
        }

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

        $this->expiredVideoDeleteService->sendAdministratorEmail($result);

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
