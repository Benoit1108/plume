<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Console;

use App\Account\Infrastructure\Persistence\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(name: 'app:user:create', description: 'Crée un utilisateur (avec son tenant).')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe')
            ->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'UUID du tenant (généré si absent)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $email */
        $email = $input->getArgument('email');
        /** @var string $plain */
        $plain = $input->getArgument('password');
        /** @var string|null $tenantOpt */
        $tenantOpt = $input->getOption('tenant');

        $tenantId = \is_string($tenantOpt) && '' !== $tenantOpt ? Uuid::fromString($tenantOpt) : Uuid::v7();

        $user = new User(Uuid::v7(), $tenantId, $email);
        $user->setPassword($this->hasher->hashPassword($user, $plain));

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Utilisateur "%s" créé (tenant %s).', $email, $tenantId->toRfc4122()));

        return Command::SUCCESS;
    }
}
