<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Console;

use App\Account\Infrastructure\Persistence\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Crée un ADMINISTRATEUR du back-office (décision 2026-07-28) : compte dédié HORS tenant métier
 * (son tenant, généré, ne portera jamais de données), ROLE_ADMIN, créé UNIQUEMENT par cette CLI —
 * jamais par l'inscription publique. Accède aux routes /api/v1/admin/*.
 */
#[AsCommand(name: 'app:admin:create', description: 'Crée un administrateur du back-office (ROLE_ADMIN, hors tenant).')]
final class CreateAdminCommand extends Command
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
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'administrateur')
            ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe (déconseillé en argument — demandé sinon)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $email */
        $email = $input->getArgument('email');
        /** @var string|null $passwordArg */
        $passwordArg = $input->getArgument('password');
        $plain = $passwordArg ?? $io->askHidden('Mot de passe');
        if (!\is_string($plain) || '' === $plain) {
            $io->error('Mot de passe requis.');

            return Command::FAILURE;
        }

        $user = new User(Uuid::v7(), Uuid::v7(), $email);
        $user->setPassword($this->hasher->hashPassword($user, $plain));
        $user->setRoles(['ROLE_ADMIN']);

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Administrateur "%s" créé (back-office).', $email));

        return Command::SUCCESS;
    }
}
