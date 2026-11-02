<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Command;

use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoConfigurationService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pumukit:expired:video:list', description: 'Expired video list')]
class ExpiredVideoListCommand extends Command
{
    public function __construct(
        private readonly ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        private readonly ExpiredVideoService $expiredVideoService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(
            <<<'EOT'
                Lists the ID and the expiration date of the multimedia objects whose expiration date is before now.

                Example:
                    php bin/console pumukit:expired:video:list
                EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $expiredVideos = $this->expiredVideoService->getExpiredVideos();

        $elements = [];
        $i = 1;
        foreach ($expiredVideos as $multimediaObject) {
            $elements[] = $this->generateMessageByMultimediaObject($multimediaObject, $i);
            ++$i;
        }

        if (!$elements) {
            $output->writeln("There aren't expired videos.");

            return Command::FAILURE;
        }

        $this->generateTableWithResult($output, $elements);

        return Command::SUCCESS;
    }

    private function generateMessageByMultimediaObject(MultimediaObject $multimediaObject, int $count): array
    {
        return [
            $count,
            $multimediaObject->getId(),
            $multimediaObject->getProperty($this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey()),
        ];
    }

    private function generateTableWithResult(OutputInterface $output, array $elements): void
    {
        $output->writeln([
            '',
            '<info>***** Command: Expired video list *****</info>',
            '<comment>Criteria: </comment>',
            '<comment>    Expiration date < '.new \DateTime()->format('c').'</comment>',
        ]);
        new Table($output)
            ->setHeaders(['Nº', 'MultimediaObject', 'Expiration Date'])
            ->setRows($elements)
            ->render()
        ;
    }
}
