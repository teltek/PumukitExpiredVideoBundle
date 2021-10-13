<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Command;

use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoConfigurationService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExpiredVideoListCommand extends ContainerAwareCommand
{
    private $expiredVideoConfigurationService;
    private $expiredVideoService;

    public function __construct(
        ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        ExpiredVideoService $expiredVideoService
    ) {
        $this->expiredVideoConfigurationService = $expiredVideoConfigurationService;
        $this->expiredVideoService = $expiredVideoService;
    }

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

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $expiredVideos = $this->expiredVideoService->getExpiredVideos();

        if (!$expiredVideos) {
            $output->writeln("There aren't expired videos.");

            return -1;
        }

        $elements = [];
        $i = 1;
        foreach ($expiredVideos as $multimediaObject) {
            $elements[] = $this->generateMessageByMultimediaObject($multimediaObject, $i);
            ++$i;
        }

        $this->generateTableWithResult($output, $elements);

        return 0;
    }

    private function generateMessageByMultimediaObject(MultimediaObject $multimediaObject, int $count): array
    {
        return [
            $count,
            $multimediaObject,
            $multimediaObject->getProperty(
                $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey()
            ),
        ];
    }

    private function generateTableWithResult(OutputInterface $output, array $elements): void
    {
        $now = new \DateTime();
        $output->writeln([
            '',
            '<info>***** Command: Expired video list *****</info>',
            '<comment>Criteria: </comment>',
            '<comment>    Expiration date < '.$now->format('c').'</comment>',
        ]);
        $table = new Table($output);
        $table
            ->setHeaders(['Nº', 'MultimediaObject', 'Expiration Date'])
            ->setRows($elements)
        ;
        $table->render();
    }
}
