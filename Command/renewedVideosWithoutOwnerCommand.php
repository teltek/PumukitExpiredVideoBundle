<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoConfigurationService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoService;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\Tag;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class renewedVideosWithoutOwnerCommand extends Command
{
    private $expiredVideoService;
    private $expiredVideoConfigurationService;
    private $documentManager;
    private $force;
    private $user;
    private $addPublishTag;
    private $renewDate;

    public function __construct(
        DocumentManager $documentManager,
        ExpiredVideoService $expiredVideoService,
        ExpiredVideoConfigurationService $expiredVideoConfigurationService
    ) {
        $this->expiredVideoService = $expiredVideoService;
        $this->expiredVideoConfigurationService = $expiredVideoConfigurationService;
        $this->documentManager = $documentManager;
    }

    protected function configure(): void
    {
        $this
            ->setName('pumukit:expired:video:renewed:without:owner')
            ->setDescription('Expired video massive renew by user or all')
            ->addOption('addPublishTag', null, InputOption::VALUE_NONE, 'Set this parameter to add publish tag or not')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Set this parameter force the execution of this action')
            ->addOption('renewDate', null, InputArgument::OPTIONAL, 'Renew date for videos listed')
            ->addOption('user', null, InputArgument::OPTIONAL, 'username', 'allUsers')
            ->setHelp(
                <<<'EOT'

Example:

php bin/console pumukit:expired:video:renewed:without:owner

Options and arguments

renewDate - The new renew date for all videos listed. Format: YYYY/mm/dd
user - Filter videos by user expired_owner role
addPublishTag - add it to recover publish tag in all videos listed.
force - necessary to execute actions, if not, command will list all videos to fix

EOT
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->force = (true === $input->getOption('force'));
        $this->user = $input->getOption('user');
        $this->addPublishTag = $input->getOption('addPublishTag');
        $this->renewDate = $input->getOption('renewDate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $user = ('allUsers' === $this->user) ? null : $this->user;
        $renewedVideos = $this->expiredVideoService->renewedVideosWithoutOwner($user);

        if (!$renewedVideos) {
            $output->writeln("There aren't renewed videos.");

            return -1;
        }

        if ($this->force) {
            $this->fixVideos($renewedVideos, $this->renewDate, $this->addPublishTag);
        }

        $this->generateTableWithResult($output, $renewedVideos);

        return 0;
    }

    private function fixVideos(array $multimediaObjects, string $renewDate, bool $addPublishTag)
    {
        $renewDate = \DateTime::createFromFormat('Y/m/d', $renewDate);
        $webTVCode = 'PUCHWEBTV';
        $ownerRole = $this->documentManager->getRepository(Role::class)->findOneBy(['cod' => 'owner']);
        $expiredOwnerRole = $this->documentManager->getRepository(Role::class)->findOneBy(['cod' => ExpiredVideoConfigurationService::EXPIRED_OWNER_CODE]);

        $i = 0;
        foreach ($multimediaObjects as $multimediaObject) {
            $multimediaObject->setPropertyAsDateTime(
                $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(),
                $renewDate
            );

            if ($addPublishTag) {
                $tag = $this->documentManager->getRepository(Tag::class)->findOneBy([
                    'cod' => $webTVCode,
                ]);
                if (!$tag instanceof Tag) {
                    throw new \Exception('Tag not found');
                }
                $multimediaObject->addTag($tag);
            }

            $expiredOwner = $multimediaObject->getPeopleByRole($expiredOwnerRole, true);
            foreach ($expiredOwner as $person) {
                $multimediaObject->addPersonWithRole($person, $ownerRole);
                $multimediaObject->removePersonWithRole($person, $expiredOwnerRole);
            }

            if (0 === $i % 50) {
                $this->documentManager->flush();
            }
            ++$i;
        }

        $this->documentManager->flush();
    }

    private function generateTableWithResult(OutputInterface $output, array $elements): void
    {
        $now = new \DateTime();
        $output->writeln([
            '',
            '<info>***** Command: Expired video list *****</info>',
            '<comment>Criteria: </comment>',
            '<comment>    Expiration date > '.$now->format('c').'</comment>',
            '<comment>    addPublishTag => '.$this->addPublishTag.'</comment>',
            '<comment>    Renew Date => '.$this->renewDate.'</comment>',
            '<comment>    User => '.$this->user.'</comment>',
        ]);

        $result = [];
        $i = 1;
        foreach ($elements as $element) {
            $result[] = [
                $i,
                $element->getId(),
                $element->getProperty($this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey()),
            ];
            ++$i;
        }
        $table = new Table($output);
        $table
            ->setHeaders(['Nº', 'MultimediaObject', 'Expiration Date'])
            ->setRows($result)
        ;
        $table->render();
    }
}
