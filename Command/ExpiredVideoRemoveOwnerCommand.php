<?php

namespace Pumukit\ExpiredVideoBundle\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\ExpiredVideoBundle\Exception\ExpiredVideoException;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Services\PersonService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExpiredVideoRemoveOwnerCommand extends ContainerAwareCommand
{
    public const EXPIRED_OWNER_CODE = 'expired_owner';
    /** @var DocumentManager */
    private $dm;
    private $user_code;
    /** @var ExpiredVideoService */
    private $expiredVideoService;
    private $sendMail;
    private $days;

    protected function configure(): void
    {
        $this
            ->setName('video:expired:remove')
            ->setDescription('This command delete role owner when the video was timed out')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Set this parameter force the execution of this action')
            ->setHelp(
                <<<'EOT'
Expired video remove delete owner people on multimedia object id when the expiration_date is less than now. This command send email to web administrator when delete data.
EOT
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->dm = $this->getContainer()->get('doctrine_mongodb.odm.document_manager');
        $this->expiredVideoService = $this->getContainer()->get('pumukit_expired_video.expired_video');
        /** @var PersonService */
        $personService = $this->getContainer()->get('pumukitschema.person');
        if (!$personService) {
            throw new ExpiredVideoException('PersonService not found.');
        }
        $this->user_code = $personService->getPersonalScopeRoleCode();
        $this->sendMail = $this->getContainer()->getParameter('pumukit_notification.sender_email');

        $this->days = (int) $this->getContainer()->getParameter('pumukit_expired_video.expiration_date_days');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('force')) {
            $output->writeln('The option force must be set to remove owner videos timed out');

            return;
        }

        if (0 === $this->days) {
            $output->writeln('Expiration date days is 0, it means deactivate expired video functionality.');

            return;
        }

        $multimediaObjectsExpired = $this->expiredVideoService->getExpiredVideos();

        if (0 === count($multimediaObjectsExpired)) {
            $output->writeln('No videos timed out.');

            return;
        }

        foreach ($multimediaObjectsExpired as $multimediaObject) {
            $this->removeOwnersFromMultimediaObject($output, $multimediaObject);
        }
    }

    private function removeOwnersFromMultimediaObject(OutputInterface $output, MultimediaObject $multimediaObject): void
    {
        $expiredOwnerRole = $this->getRoleWithCode(self::EXPIRED_OWNER_CODE);
        $removeOwner = false;
        if (0 === count($multimediaObject->getRoles())) {
            $output->writeln('There aren\'t roles on multimedia object id - '.$multimediaObject->getId());

            return;
        }

        foreach ($multimediaObject->getRoles() as $role) {
            if ($role->getCod() !== $this->user_code) {
                continue;
            }

            foreach ($multimediaObject->getPeopleByRoleCod($this->user_code, true) as $person) {
                $multimediaObject->addPersonWithRole($person, $expiredOwnerRole);
                $multimediaObject->removePersonWithRole($person, $role);
            }
            $removeOwner = true;
        }

        $this->dm->flush();

        if ($removeOwner) {
            $output->writeln('Remove owner people from multimedia object id - '.$multimediaObject->getId());
        }
    }

    private function getRoleWithCode(string $code): Role
    {
        $role = $this->dm->getRepository(Role::class)->findOneBy(['cod' => $code]);
        if (!$role) {
            throw new ExpiredVideoException("Role with code '".$code."' not found. Please, init pumukit roles.");
        }

        return $role;
    }
}
