<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Creates a user with access rights to the admin panel',
)]
final class CreateAdminUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('phone', InputArgument::REQUIRED, 'User phone')
            ->addArgument('password', InputArgument::REQUIRED, 'Password')
            ->addArgument('name', InputArgument::OPTIONAL, 'Name', 'Admin');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $phone = (string) $input->getArgument('phone');
        $plainPassword = (string) $input->getArgument('password');
        $name = (string) $input->getArgument('name');

        $userRepo = $this->em->getRepository(User::class);

        /** @var User|null $existing */
        $existing = $userRepo->findOneBy(['phone' => $phone]);

        if ($existing instanceof User) {
            $output->writeln(sprintf('<error>User with the phone %s already exists</error>', $phone));

            return Command::FAILURE;
        }

        $user = new User();
        $user->setPhone($phone);
        $user->setName($name);
        $user->setRoles(['ROLE_ADMIN']);

        $hash = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hash);

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln(sprintf(
            '<info>Admin created: %s (%s), role: ROLE_ADMIN</info>',
            $phone,
            $name,
        ));

        return Command::SUCCESS;
    }
}
