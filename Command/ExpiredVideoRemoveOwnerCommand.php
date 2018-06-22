<?php

namespace Pumukit\ExpiredVideoBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;

class ExpiredVideoRemoveOwnerCommand extends ContainerAwareCommand
{
    private $dm = null;
    private $mmobjRepo = null;
    private $user_code;
    private $type = 'removeOwner';

    protected function configure()
    {
        $this
            ->setName('video:expired:remove')
            ->setDescription('This command delete role owner when the video was timed out')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Set this parameter force the execution of this action')
            ->setHelp(<<<'EOT'
Expired video remove delete owner people on multimedia object id when the expiration_date is less than now. This command send email to web administrator when delete data.
EOT
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();

        $this->expiredVideoService = $this->getContainer()->get('pumukit_expired_video.notification');
        $this->user_code = $this->getContainer()->get('pumukitschema.person')->getPersonalScopeRoleCode();
        $this->sendMail = $this->getContainer()->getParameter('pumukit_notification.sender_email');

        $this->mmobjRepo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        $this->roleRepo = $this->dm->getRepository('PumukitSchemaBundle:Role');

        $this->days = $this->getContainer()->getParameter('pumukit_expired_video.expiration_date_days');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('force')) {
            if (0 === $this->days) {
                $output->writeln('Expiration date days is 0, it means deactivate expired video functionality.');

                return;
            }

            $mmobjExpired = $this->getExpiredVideos();

            $expiredOwnerRole = $this->getRoleWithCode('expired_owner');

            if (count($mmobjExpired) > 0) {
                $aMultimediaObject = array();
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
                            $aMultimediaObject[] = $mmObj->getId();
                            $output->writeln('Remove owner people from multimedia object id - '.$mmObj->getId());
                        }
                    } else {
                        $output->writeln('There aren\'t roles on multimedia object id - '.$mmObj->getId());
                    }
                }
                try {
                    $this->expiredVideoService->generateNotification($this->sendMail, $this->type, $mmObj);
                } catch (\Exception $e) {
                    $output->writeln('<error>'.$e->getMessage().'</error>');
                }
            } else {
                $output->writeln('No videos timed out.');
            }
        } else {
            $output->writeln('The option force must be set to remove owner videos timed out');
        }
    }

    /**
     * @return mixed
     */
    private function getExpiredVideos()
    {
        $now = new \DateTime();

        return $this->mmobjRepo->createQueryBuilder()
            ->field('properties.expiration_date')->exists(true)
            ->field('properties.expiration_date')->lte($now->format('c'))
            ->getQuery()
            ->execute();
    }

    /**
     * @param $code
     *
     * @return mixed
     * @throws \Exception
     */
    private function getRoleWithCode($code)
    {
        $role = $this->roleRepo->findOneByCod($code);
        if (null == $role) {
            throw new \Exception("Role with code '".$code."' not found. Please, init pumukit roles.");
        }

        return $role;
    }
}
