<?php

namespace Pumukit\ExpiredVideoBundle\Command;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Role;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExpiredVideoRemoveOwnerCommand extends ContainerAwareCommand
{
    private $dm;
    private $mmobjRepo;
    private $user_code;
    private $type = 'removeOwner';
    private $expiredVideoService;
    private $sendMail;
    private $roleRepo;
    private $days;

    protected function configure()
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

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->dm = $this->getContainer()->get('doctrine_mongodb.odm.document_manager');

        $this->expiredVideoService = $this->getContainer()->get('pumukit_expired_video.expired_video');
        $this->user_code = $this->getContainer()->get('pumukitschema.person')->getPersonalScopeRoleCode();
        $this->sendMail = $this->getContainer()->getParameter('pumukit_notification.sender_email');

        $this->mmobjRepo = $this->dm->getRepository(MultimediaObject::class);
        $this->roleRepo = $this->dm->getRepository(Role::class);

        $this->days = $this->getContainer()->getParameter('pumukit_expired_video.expiration_date_days');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        if ($input->getOption('force')) {
            if (0 === $this->days) {
                $output->writeln('Expiration date days is 0, it means deactivate expired video functionality.');

                return null;
            }

            $mmobjExpired = $this->expiredVideoService->getExpiredVideos();

            $expiredOwnerRole = $this->getRoleWithCode('expired_owner');

            if (count($mmobjExpired) > 0) {
                foreach ($mmobjExpired as $mmObj) {
                    $removeOwner = false;
                    if (count($mmObj->getRoles()) > 0) {
                        foreach ($mmObj->getRoles() as $role) {
                            if ($role->getCod() === $this->user_code) {
                                foreach ($mmObj->getPeopleByRoleCod($this->user_code, true) as $person) {
                                    $mmObj->addPersonWithRole($person, $expiredOwnerRole);
                                    $mmObj->removePersonWithRole($person, $role);
                                }
                                $removeOwner = true;
                                $this->dm->flush();
                            }
                        }
                        if ($removeOwner) {
                            $output->writeln('Remove owner people from multimedia object id - '.$mmObj->getId());
                        }
                    } else {
                        $output->writeln('There aren\'t roles on multimedia object id - '.$mmObj->getId());
                    }
                }
            } else {
                $output->writeln('No videos timed out.');
            }
        } else {
            $output->writeln('The option force must be set to remove owner videos timed out');
        }

        return 0;
    }

    private function getRoleWithCode(string $code): Role
    {
        $role = $this->roleRepo->findOneBy(['cod' => $code]);
        if (!$role) {
            throw new \Exception("Role with code '".$code."' not found. Please, init pumukit roles.");
        }

        return $role;
    }
}
