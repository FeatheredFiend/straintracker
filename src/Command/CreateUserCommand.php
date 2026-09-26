<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-user', description: 'Create a user, or reset an existing one\'s password (use --admin for access to the admin section)')]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
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
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim($input->getArgument('email')));

        $user = $this->userRepository->findOneBy(['email' => $email]) ?? new User();
        $user
            ->setEmail($email)
            ->setDisplayName($input->getArgument('name'))
            ->setAdmin($input->getOption('admin'));
        $user->setPassword($this->passwordHasher->hashPassword($user, $input->getArgument('password')));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('%s "%s" is ready.', $user->isAdmin() ? 'Admin' : 'User', $email));

        return Command::SUCCESS;
    }
}
