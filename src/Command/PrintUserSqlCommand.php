<?php

namespace App\Command;

use App\Entity\User;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:print-user-sql',
    description: 'Print SQL that creates or resets a user, for hosts with no console access (paste into phpMyAdmin)',
)]
class PrintUserSqlCommand extends Command
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email address used to sign in')
            ->addArgument('name', InputArgument::REQUIRED, 'Display name, e.g. Martyn')
            ->addArgument('password', InputArgument::REQUIRED, 'Password')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Grant access to the admin section')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = (new User())
            ->setEmail($input->getArgument('email'))
            ->setDisplayName($input->getArgument('name'))
            ->setAdmin($input->getOption('admin'));
        $hash = $this->passwordHasher->hashPassword($user, $input->getArgument('password'));

        $output->writeln(sprintf(
            'INSERT INTO app_user (email, display_name, roles, password) VALUES (%s, %s, %s, %s) '
            .'ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), roles = VALUES(roles), password = VALUES(password);',
            $this->quote($user->getEmail()),
            $this->quote($user->getDisplayName()),
            $this->quote(json_encode($user->isAdmin() ? ['ROLE_ADMIN'] : [])),
            $this->quote($hash),
        ));

        return Command::SUCCESS;
    }

    private function quote(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "''"], $value)."'";
    }
}
