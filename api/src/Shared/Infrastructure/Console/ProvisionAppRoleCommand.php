<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provisionne le rôle applicatif runtime (défaut `plume_app`) : un rôle NON-propriétaire, seul
 * moyen d'être soumis à la RLS Postgres (le propriétaire `plume` la contourne et reste réservé
 * aux migrations/tests/console). Idempotent, à lancer via la connexion propriétaire (DATABASE_URL) :
 *
 *   - crée le rôle (LOGIN) s'il n'existe pas ;
 *   - lui donne USAGE sur le schéma + DML sur les tables/séquences EXISTANTES ;
 *   - pose des DEFAULT PRIVILEGES pour que les tables FUTURES (créées par les migrations, sous
 *     le rôle propriétaire courant) lui soient automatiquement accordées.
 *
 * Ordre indifférent vis-à-vis des migrations (couvre l'existant ET le futur). Source unique
 * utilisée en dev (`make provision-app-role`), en CI et en E2E — pas de dépendance à psql.
 */
#[AsCommand(name: 'app:db:provision-app-role', description: 'Crée/actualise le rôle applicatif runtime (RLS) + ses privilèges')]
final class ProvisionAppRoleCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire(env: 'APP_DB_USER')]
        private readonly string $appUser,
        #[Autowire(env: 'APP_DB_PASSWORD')]
        private readonly string $appPassword,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Identifiant SQL strict : autorise l'inline sûr dans les GRANT (qui n'acceptent pas de bind).
        if (1 !== preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->appUser)) {
            $io->error(\sprintf('Nom de rôle invalide : "%s" (identifiant SQL attendu).', $this->appUser));

            return Command::FAILURE;
        }
        $role = '"'.$this->appUser.'"';

        // Un bloc DO n'accepte pas de bind : on inline. Le nom est validé (regex ci-dessus), le mot
        // de passe est échappé et repasse par format(%L) côté serveur → double barrière anti-injection.
        $nameLiteral = "'".str_replace("'", "''", $this->appUser)."'";
        $pwdLiteral = "'".str_replace("'", "''", $this->appPassword)."'";
        $this->connection->executeStatement(
            <<<SQL
                DO \$do\$
                BEGIN
                    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = $nameLiteral) THEN
                        EXECUTE format('CREATE ROLE %I LOGIN PASSWORD %L', $nameLiteral, $pwdLiteral);
                    END IF;
                END
                \$do\$;
                SQL,
        );

        // Privilèges sur la base courante : existant + futur (DEFAULT PRIVILEGES du rôle courant).
        $this->connection->executeStatement("GRANT USAGE ON SCHEMA public TO $role");
        $this->connection->executeStatement("GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO $role");
        $this->connection->executeStatement("GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO $role");
        $this->connection->executeStatement("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO $role");
        $this->connection->executeStatement("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO $role");

        $io->success(\sprintf('Rôle applicatif "%s" provisionné (privilèges DML + defaults).', $this->appUser));

        return Command::SUCCESS;
    }
}
