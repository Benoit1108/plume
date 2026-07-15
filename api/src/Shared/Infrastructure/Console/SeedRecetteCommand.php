<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Account\Domain\Profile\Profile;
use App\Account\Domain\Profile\ProfileRepository;
use App\Account\Infrastructure\Persistence\User;
use App\Directory\Domain\Organization\Contact;
use App\Directory\Domain\Organization\ContactId;
use App\Directory\Domain\Organization\Organization;
use App\Directory\Domain\Organization\OrganizationId;
use App\Directory\Domain\Organization\OrganizationRepository;
use App\Directory\Domain\Organization\OrganizationType;
use App\Drafting\Domain\Draft\Draft;
use App\Drafting\Domain\Draft\DraftId;
use App\Drafting\Domain\Draft\DraftRepository;
use App\Drafting\Domain\Draft\DraftType;
use App\Drafting\Domain\Template\Template;
use App\Drafting\Domain\Template\TemplateId;
use App\Drafting\Domain\Template\TemplateRepository;
use App\Mailbox\Application\TokenCipher;
use App\Mailbox\Domain\Mailbox\ConnectedMailbox;
use App\Mailbox\Domain\Mailbox\EncryptedToken;
use App\Mailbox\Domain\Mailbox\MailboxId;
use App\Mailbox\Domain\Mailbox\MailboxRepository;
use App\Mailbox\Domain\Mailbox\MailProviderName;
use App\Mailbox\Domain\Outbound\OutboundMessage;
use App\Mailbox\Domain\Outbound\OutboundMessageId;
use App\Mailbox\Domain\Outbound\OutboundMessageRepository;
use App\Prospecting\Domain\Lead\Lead;
use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\LeadRepository;
use App\Prospecting\Domain\Lead\LeadSource;
use App\Prospecting\Domain\Lead\Priority;
use App\Shared\Application\IdGenerator;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\LanguagePair;
use App\Shared\Domain\ValueObject\Segment;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Jeu de données de RECETTE (dev/test uniquement) : peuple un tenant dédié avec
 * des données réalistes DATÉES DANS LE TEMPS, pour visualiser toutes les pages
 * « chargées » (Répertoire, pipeline, Aujourd'hui, tableau de bord, rédaction,
 * réglages, boîte email). Idempotent : purge les données métier puis reseed.
 *
 * Les agrégats sont construits via leurs repositories (le mapping gère le JSONB
 * et les VOs) avec des dates rétro-datées ; le journal `interaction` est écrit
 * en direct (SQL) pour un contrôle total des dates — indispensable au tableau
 * de bord (8 semaines, taux) et à la série hebdomadaire.
 */
#[AsCommand(name: 'app:dev:seed', description: 'Jeu de données de recette (dev only).')]
final class SeedRecetteCommand extends Command
{
    private const string EMAIL = 'recette@plume.fr';
    private const string PASSWORD = 'recette-2026';
    private const int WEEKLY_GOAL = 4;

    private \DateTimeImmutable $now;
    private string $tenant = '';
    /** @var array<string, int> actes de démarchage par semaine ISO (clé « o-WW »). */
    private array $weekOutreach = [];

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly IdGenerator $ids,
        private readonly OrganizationRepository $organizations,
        private readonly LeadRepository $leads,
        private readonly DraftRepository $drafts,
        private readonly TemplateRepository $templates,
        private readonly ProfileRepository $profiles,
        private readonly MailboxRepository $mailboxes,
        private readonly OutboundMessageRepository $outbound,
        private readonly TokenCipher $cipher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('prod' === $this->kernel->getEnvironment()) {
            $io->error('app:dev:seed est interdit en production.');

            return Command::FAILURE;
        }

        $this->now = new \DateTimeImmutable('now');
        $tenantId = $this->ensureUser();
        $this->tenant = $tenantId->toString();
        $this->purge();

        $this->seedProfile($tenantId);
        $this->seedTemplates($tenantId);
        $organizations = $this->seedOrganizations($tenantId);
        $this->seedMailbox($tenantId);
        $counts = $this->seedLeads($tenantId, $organizations);

        $io->success(sprintf(
            "Recette prête : %d organisations, %d pistes, %d interactions, %d brouillons, %d envois.\nConnexion : %s / %s",
            \count($organizations),
            $counts['leads'],
            $counts['interactions'],
            $counts['drafts'],
            $counts['emails'],
            self::EMAIL,
            self::PASSWORD,
        ));

        return Command::SUCCESS;
    }

    private function ensureUser(): TenantId
    {
        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => self::EMAIL]);
        if ($existing instanceof User) {
            return TenantId::fromString($existing->getTenantId()->toRfc4122());
        }

        $tenantId = Uuid::v7();
        $user = new User(Uuid::v7(), $tenantId, self::EMAIL);
        $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();

        return TenantId::fromString($tenantId->toRfc4122());
    }

    /** Purge des données MÉTIER (tous tenants — dev) ; les comptes utilisateurs restent. */
    private function purge(): void
    {
        $this->connection->executeStatement(
            'TRUNCATE TABLE "outbound_message", "connected_mailbox", "draft", "template", '
            .'"interaction", "lead", "organization", "profile" RESTART IDENTITY CASCADE',
        );
    }

    private function seedProfile(TenantId $tenantId): void
    {
        $profile = Profile::create($tenantId, $this->now);
        $profile->changeWeeklyGoal(self::WEEKLY_GOAL, $this->now);
        $profile->changePresentation(
            "Traductrice indépendante EN↔FR et ES→FR, dix ans d'expérience en édition (littérature "
            .'générale et jeunesse) et en audiovisuel (sous-titrage, adaptation).',
            'Édition jeunesse et YA · Sous-titrage séries · Documentaires · Sciences humaines',
            "Marie Lefèvre\nTraductrice EN·ES → FR\nmarie.lefevre@example.fr · +33 6 12 34 56 78",
            $this->now,
        );
        $profile->changeIdentity('Marie', 'Lefèvre', $this->now);
        $this->profiles->save($profile);
    }

    private function seedTemplates(TenantId $tenantId): void
    {
        $seeds = [
            ['Candidature édition (FR)', DraftType::APPLICATION_EMAIL, Segment::PUBLISHING, 'fr',
                'Candidature — traduction {{langues}}',
                "Bonjour {{contact}},\n\nTraductrice indépendante ({{langues}}), je me permets de contacter {{organisation}} pour proposer mes services de traduction littéraire.\n\n{{bio}}\n\nJe serais ravie d'échanger sur vos besoins et de réaliser un essai.\n\n{{signature}}"],
            ['Candidature audiovisuel (EN)', DraftType::APPLICATION_EMAIL, Segment::AUDIOVISUAL, 'en',
                'Freelance subtitling — {{langues}}',
                "Hello {{contact}},\n\nI am a freelance audiovisual translator ({{langues}}) reaching out to {{organisation}}.\n\n{{bio}}\n\nHappy to take a short test.\n\n{{signature}}"],
            ['Relance (FR)', DraftType::FOLLOW_UP_EMAIL, Segment::PUBLISHING, 'fr',
                'Re : candidature — traduction {{langues}}',
                "Bonjour {{contact}},\n\nJe me permets de revenir vers vous au sujet de ma candidature adressée à {{organisation}}.\n\nJe reste disponible pour un essai ou un échange.\n\n{{signature}}"],
            ['Candidature agence technique (FR)', DraftType::APPLICATION_EMAIL, Segment::TECHNICAL, 'fr',
                'Traduction technique {{langues}} — collaboration',
                "Bonjour {{contact}},\n\nJe propose mes services de traduction technique ({{langues}}) à {{organisation}}.\n\n{{specialites}}\n\n{{signature}}"],
        ];
        foreach ($seeds as [$name, $type, $segment, $lang, $subject, $body]) {
            $this->templates->save(Template::create(
                TemplateId::fromString($this->ids->generate()),
                $tenantId,
                $name,
                $type,
                $segment,
                LanguageCode::fromString($lang),
                $subject,
                $body,
                $this->now,
            ));
        }
    }

    /**
     * @return array<string, array{id: string, contactId: string, email: ?string, segment: Segment, name: string, langPair: string}>
     */
    private function seedOrganizations(TenantId $tenantId): array
    {
        // [nom, type, pays, langues, segments, doNotContact, [contact: nom, rôle, email?], paire]
        /** @var list<array{0: string, 1: OrganizationType, 2: string, 3: list<string>, 4: list<Segment>, 5: bool, 6: array{0: string, 1: string|null, 2: string|null}, 7: string}> $specs */
        $specs = [
            ['Éditions du Phare', OrganizationType::PUBLISHER, 'FR', ['fr', 'en'], [Segment::PUBLISHING], false, ['Camille Rousseau', 'Éditrice littéraire', 'c.rousseau@phare.example'], 'en>fr'],
            ['Gallimard Jeunesse', OrganizationType::PUBLISHER, 'FR', ['fr', 'en'], [Segment::PUBLISHING], false, ['Léa Fontaine', 'Responsable de collection', 'l.fontaine@gj.example'], 'en>fr'],
            ['Éditions Actes Sud', OrganizationType::PUBLISHER, 'FR', ['fr', 'es'], [Segment::PUBLISHING], false, ['Pauline Marchand', 'Directrice éditoriale', 'p.marchand@actes.example'], 'es>fr'],
            ['Nord-Sud Verlag', OrganizationType::PUBLISHER, 'DE', ['fr', 'en', 'de'], [Segment::PUBLISHING], false, ['Hans Weber', 'Rights Manager', 'h.weber@nordsud.example'], 'en>fr'],
            ['Studio Dubbing Paris', OrganizationType::AV_STUDIO, 'FR', ['fr', 'en'], [Segment::AUDIOVISUAL], false, ['Thomas Girard', 'Chef de projet doublage', 't.girard@sdp.example'], 'en>fr'],
            ['Titra Films', OrganizationType::AV_STUDIO, 'FR', ['fr', 'en', 'es'], [Segment::AUDIOVISUAL], false, ['Sophie Bernard', 'Coordinatrice sous-titrage', 's.bernard@titra.example'], 'en>fr'],
            ['VSI Group', OrganizationType::AV_STUDIO, 'GB', ['fr', 'en'], [Segment::AUDIOVISUAL], false, ['James Cooper', 'Localization Manager', 'j.cooper@vsi.example'], 'en>fr'],
            ['LinguaTech Solutions', OrganizationType::AGENCY, 'FR', ['fr', 'en'], [Segment::TECHNICAL], false, ['Nadia Benali', 'Vendor Manager', 'n.benali@linguatech.example'], 'en>fr'],
            ['TransPerfect', OrganizationType::AGENCY, 'US', ['fr', 'en', 'es'], [Segment::TECHNICAL], false, ['Karen Miller', 'Project Manager', 'k.miller@transperfect.example'], 'en>fr'],
            ['Agence Traduco', OrganizationType::AGENCY, 'FR', ['fr', 'es'], [Segment::TECHNICAL], false, ['Julien Faure', 'Responsable achats', null], 'es>fr'],
            ['Éditions Fermées', OrganizationType::PUBLISHER, 'FR', ['fr'], [Segment::PUBLISHING], true, ['Service RH', null, null], 'en>fr'],
            ['Média Docs & Cie', OrganizationType::OTHER, 'BE', ['fr', 'en'], [Segment::OTHER], false, ['Élise Dupont', 'Contact général', 'contact@mediadocs.example'], 'en>fr'],
        ];

        $map = [];
        foreach ($specs as [$name, $type, $country, $langs, $segments, $dnc, $contact, $langPair]) {
            $org = Organization::create(
                OrganizationId::fromString($this->ids->generate()),
                $tenantId,
                $name,
                $type,
                $this->now,
                null,
                CountryCode::fromString($country),
                array_map(static fn (string $l): LanguageCode => LanguageCode::fromString($l), $langs),
                $segments,
            );
            [$fullName, $role, $email] = $contact;
            $cid = ContactId::fromString($this->ids->generate());
            $org->addContact(new Contact(
                $cid,
                $fullName,
                $role,
                null !== $email ? EmailAddress::fromString($email) : null,
            ), $this->now);
            if ($dnc) {
                $org->markDoNotContact($this->now);
            }
            $this->organizations->save($org);

            $map[$name] = [
                'id' => $org->id()->toString(),
                'contactId' => $cid->toString(),
                'email' => $email,
                'segment' => $segments[0],
                'name' => $name,
                'langPair' => $langPair,
            ];
        }

        return $map;
    }

    private function seedMailbox(TenantId $tenantId): void
    {
        $mailbox = ConnectedMailbox::connect(
            MailboxId::fromString($this->ids->generate()),
            $tenantId,
            MailProviderName::GMAIL,
            EmailAddress::fromString('marie.lefevre@gmail.example'),
            EncryptedToken::fromCiphertext($this->cipher->encrypt('seed-access-token')),
            EncryptedToken::fromCiphertext($this->cipher->encrypt('seed-refresh-token')),
            $this->daysAgo(40),
        );
        $mailbox->markSyncSucceeded(null, $this->daysAgo(0));
        $this->mailboxes->save($mailbox);
    }

    /**
     * @param array<string, array{id: string, contactId: string, email: ?string, segment: Segment, name: string, langPair: string}> $orgs
     *
     * @return array{leads: int, interactions: int, drafts: int, emails: int}
     */
    private function seedLeads(TenantId $tenantId, array $orgs): array
    {
        // [orgKey, source, priorité, contactJoursAvant (null = jamais contactée),
        //  issue, brouillon (type|null), envoyé]
        /** @var list<array{0: string, 1: LeadSource, 2: Priority, 3: int|null, 4: string, 5: DraftType|null, 6: bool}> $scenarios */
        // Une seule piste ACTIVE (statut != WON/LOST) par organisation (invariant M1.2) :
        // les 11 pistes actives sont sur des orgs distinctes ; les terminales (won/lost)
        // réutilisent librement des orgs.
        $scenarios = [
            // À contacter (récentes) — pour « Aujourd'hui » et la colonne TO_CONTACT.
            ['Éditions du Phare', LeadSource::DIRECT, Priority::HIGH, null, 'tocontact', DraftType::APPLICATION_EMAIL, false],
            ['Nord-Sud Verlag', LeadSource::REFERRAL, Priority::MEDIUM, null, 'tocontact', null, false],
            // Contactées, relance en attente (due aujourd'hui / en retard / à venir).
            ['Gallimard Jeunesse', LeadSource::DIRECT, Priority::HIGH, 7, 'contacted', DraftType::APPLICATION_EMAIL, true],
            ['Studio Dubbing Paris', LeadSource::DIRECT, Priority::HIGH, 10, 'contacted', null, true],
            ['Média Docs & Cie', LeadSource::JOB_BOARD, Priority::LOW, 2, 'contacted', DraftType::APPLICATION_EMAIL, false],
            // Relancées.
            ['LinguaTech Solutions', LeadSource::DIRECT, Priority::MEDIUM, 22, 'followed', DraftType::FOLLOW_UP_EMAIL, true],
            ['Titra Films', LeadSource::REFERRAL, Priority::MEDIUM, 16, 'followed', null, true],
            // En discussion.
            ['Éditions Actes Sud', LeadSource::DIRECT, Priority::HIGH, 18, 'discussion', DraftType::APPLICATION_EMAIL, true],
            ['VSI Group', LeadSource::REFERRAL, Priority::HIGH, 12, 'discussion', null, true],
            // Test / échantillon.
            ['TransPerfect', LeadSource::DIRECT, Priority::HIGH, 30, 'sample', null, true],
            // En pause.
            ['Agence Traduco', LeadSource::DIRECT, Priority::LOW, 20, 'paused', null, false],
            // Gagnées (terminales — réutilisent des orgs déjà porteuses d'une piste active).
            ['Gallimard Jeunesse', LeadSource::REFERRAL, Priority::HIGH, 33, 'won', null, true],
            ['Titra Films', LeadSource::DIRECT, Priority::MEDIUM, 40, 'won', null, true],
            // Perdues (terminales).
            ['Nord-Sud Verlag', LeadSource::DIRECT, Priority::LOW, 28, 'lost', null, true],
            ['VSI Group', LeadSource::JOB_BOARD, Priority::LOW, 45, 'lost', null, false],
        ];

        $interactions = 0;
        $draftCount = 0;
        $emailCount = 0;
        /** @var list<string> $followUpLeads pistes plausiblement relancées (ossature d'activité). */
        $followUpLeads = [];

        foreach ($scenarios as [$orgKey, $source, $priority, $contactDaysAgo, $outcome, $draftType, $emailed]) {
            $org = $orgs[$orgKey];
            $leadId = $this->ids->generate();
            if (\in_array($outcome, ['followed', 'discussion', 'won'], true)) {
                $followUpLeads[] = $leadId;
            }
            $createdDaysAgo = null !== $contactDaysAgo ? $contactDaysAgo + 3 : random_int(1, 4);
            $createdAt = $this->daysAgo($createdDaysAgo);

            $lead = Lead::create(
                LeadId::fromString($leadId),
                $tenantId,
                $org['id'],
                $org['contactId'],
                LanguagePair::fromString($org['langPair']),
                $source,
                $priority,
                $org['segment'],
                $createdAt,
            );
            $interactions += $this->interaction($leadId, 'created', $createdAt, ['organizationId' => $org['id']]);

            if (null !== $contactDaysAgo) {
                $contactAt = $this->daysAgo($contactDaysAgo);
                $lead->contact($contactAt);
                $interactions += $this->interaction($leadId, 'contacted', $contactAt);
                $interactions += $this->interaction($leadId, 'follow_up_scheduled', $contactAt, ['dueAt' => $contactAt->modify('+7 days')->format('Y-m-d'), 'auto' => true]);

                if ('followed' === $outcome) {
                    $fuAt = $this->daysAgo(max(1, $contactDaysAgo - 7));
                    $lead->recordFollowUp($fuAt);
                    $interactions += $this->interaction($leadId, 'followed_up', $fuAt);
                    $interactions += $this->interaction($leadId, 'follow_up_scheduled', $fuAt, ['dueAt' => $fuAt->modify('+21 days')->format('Y-m-d'), 'auto' => true]);
                } elseif (\in_array($outcome, ['discussion', 'sample', 'won'], true)) {
                    // Relances intermédiaires (cadence J+7/J+21) avant la réponse —
                    // réaliste et densifie l'activité hebdomadaire (série).
                    $followUpOffsets = $contactDaysAgo >= 21 ? [7, 14] : ($contactDaysAgo >= 12 ? [7] : []);
                    $lastTouch = $contactDaysAgo;
                    foreach ($followUpOffsets as $offset) {
                        $fuAt = $this->daysAgo($contactDaysAgo - $offset);
                        $lead->recordFollowUp($fuAt);
                        $interactions += $this->interaction($leadId, 'followed_up', $fuAt);
                        $lastTouch = $contactDaysAgo - $offset;
                    }
                    $replyAt = $this->daysAgo(max(1, $lastTouch - 3));
                    $lead->recordReply($replyAt, 'Bonjour, merci pour votre message — pouvez-vous nous transmettre vos références et vos disponibilités ?');
                    $interactions += $this->interaction($leadId, 'reply', $replyAt, ['preview' => 'Bonjour, merci pour votre message — pouvez-vous nous transmettre vos références et vos disponibilités ?']);
                    if ('sample' === $outcome) {
                        $sampleAt = $this->daysAgo(max(1, $contactDaysAgo - 8));
                        $lead->moveToSampleTest($sampleAt);
                        $interactions += $this->interaction($leadId, 'sample_test', $sampleAt);
                    } elseif ('won' === $outcome) {
                        $wonAt = $this->daysAgo(max(1, $contactDaysAgo - 10));
                        $lead->markWon($wonAt);
                        $interactions += $this->interaction($leadId, 'won', $wonAt);
                        $interactions += $this->interaction($leadId, 'note', $wonAt, ['text' => 'Contrat signé ! Premier volume à livrer sous 6 semaines.']);
                    }
                } elseif ('lost' === $outcome) {
                    $lostAt = $this->daysAgo(max(1, $contactDaysAgo - 6));
                    $lead->markLost($lostAt);
                    $interactions += $this->interaction($leadId, 'lost', $lostAt);
                } elseif ('paused' === $outcome) {
                    $pausedAt = $this->daysAgo(max(1, $contactDaysAgo - 3));
                    $lead->pause($pausedAt);
                    $interactions += $this->interaction($leadId, 'paused', $pausedAt, ['from' => 'CONTACTED']);
                }
            }

            $this->leads->save($lead);

            // Brouillon éventuel (READY, corps canned) + envoi éventuel.
            if (null !== $draftType) {
                $draftAt = $this->daysAgo(max(0, ($contactDaysAgo ?? 2) - 1));
                $draft = Draft::request(
                    DraftId::fromString($this->ids->generate()),
                    $tenantId,
                    $leadId,
                    $draftType,
                    LanguageCode::fromString(explode('>', $org['langPair'])[1] ?? 'fr'),
                    null,
                    $draftAt,
                );
                $draft->complete(
                    DraftType::FOLLOW_UP_EMAIL === $draftType ? 'Re : proposition de collaboration' : 'Proposition de collaboration — traduction',
                    'Bonjour '.$org['name']." ,\n\n[Brouillon de recette] Message personnalisé prêt à relire avant envoi.\n\nMarie Lefèvre",
                    $draftAt,
                );
                $this->drafts->save($draft);
                ++$draftCount;
                $interactions += $this->interaction($leadId, 'draft_generated', $draftAt, ['draftType' => $draftType->value]);
            }

            if ($emailed && null !== $contactDaysAgo && null !== $org['email']) {
                $sentAt = $this->daysAgo($contactDaysAgo);
                $message = OutboundMessage::request(
                    OutboundMessageId::fromString($this->ids->generate()),
                    $tenantId,
                    $leadId,
                    $this->ids->generate(),
                    DraftType::APPLICATION_EMAIL->value,
                    EmailAddress::fromString($org['email']),
                    $sentAt,
                );
                $message->markSent('seed-thread-'.substr(md5($leadId), 0, 12), $sentAt);
                $this->outbound->save($message);
                ++$emailCount;
                $interactions += $this->interaction($leadId, 'email_sent', $sentAt, ['draftType' => 'APPLICATION_EMAIL']);
            }
        }

        $interactions += $this->topUpWeeklyActivity($followUpLeads);

        return ['leads' => \count($scenarios), 'interactions' => $interactions, 'drafts' => $draftCount, 'emails' => $emailCount];
    }

    /**
     * Ossature d'activité : garantit que chacune des 6 dernières semaines ISO
     * atteint l'objectif hebdomadaire (série 🔥 visible, indépendante du jour où
     * le seed tourne). Complète avec des relances datées en milieu de semaine,
     * attribuées à des pistes qui en ont plausiblement (relancées/en discussion).
     *
     * @param list<string> $followUpLeads
     */
    private function topUpWeeklyActivity(array $followUpLeads): int
    {
        if ([] === $followUpLeads) {
            return 0;
        }

        $added = 0;
        $cursor = 0;
        $monday = $this->now->modify('monday this week');
        for ($week = 0; $week < 6; ++$week) {
            // Mercredi de la semaine (au milieu : à l'abri du décalage de jour).
            $wednesday = $monday->modify(sprintf('-%d weeks', $week))->modify('+2 days')->setTime(11, 0);
            $key = $wednesday->format('o-\WW');
            while (($this->weekOutreach[$key] ?? 0) < self::WEEKLY_GOAL) {
                $leadId = $followUpLeads[$cursor % \count($followUpLeads)];
                ++$cursor;
                $added += $this->interaction($leadId, 'followed_up', $wednesday);
            }
        }

        return $added;
    }

    /** @param array<string, mixed> $payload @return int toujours 1 (pour cumuler le compteur) */
    private function interaction(string $leadId, string $type, \DateTimeImmutable $occurredOn, array $payload = []): int
    {
        if (\in_array($type, ['contacted', 'followed_up'], true)) {
            $key = $occurredOn->format('o-\WW');
            $this->weekOutreach[$key] = ($this->weekOutreach[$key] ?? 0) + 1;
        }

        $this->connection->executeStatement(
            'INSERT INTO interaction (id, event_id, tenant_id, lead_id, type, payload, occurred_on)
             VALUES (:id, :event_id, :tenant, :lead, :type, :payload, :occurred_on)',
            [
                'id' => Uuid::v7()->toRfc4122(),
                'event_id' => Uuid::v7()->toRfc4122(),
                'tenant' => $this->tenant,
                'lead' => $leadId,
                'type' => $type,
                'payload' => json_encode($payload, \JSON_THROW_ON_ERROR),
                'occurred_on' => $occurredOn->format('Y-m-d H:i:s'),
            ],
        );

        return 1;
    }

    private function daysAgo(int $days): \DateTimeImmutable
    {
        return $this->now->modify(sprintf('-%d days', $days))->setTime(10, 0);
    }
}
