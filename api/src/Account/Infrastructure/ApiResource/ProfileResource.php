<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Account\Infrastructure\ApiResource\State\ProfileProcessor;
use App\Account\Infrastructure\ApiResource\State\ProfileProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Profil de la traductrice (singleton par tenant) — M1.3 : objectif hebdo,
 * M1.4 : présentation (bio, spécialités, signature) pour la rédaction assistée.
 */
#[ApiResource(
    shortName: 'Profile',
    normalizationContext: ['groups' => ['profile:read']],
    denormalizationContext: ['groups' => ['profile:write']],
    operations: [
        new Get(uriTemplate: '/profile', provider: ProfileProvider::class),
        new Patch(uriTemplate: '/profile', provider: ProfileProvider::class, processor: ProfileProcessor::class),
    ],
)]
final class ProfileResource
{
    /** Ressource singleton (une par tenant courant). */
    #[ApiProperty(identifier: true)]
    #[Groups(['profile:read'])]
    public string $id = 'me';

    #[Assert\Range(min: 1, max: 99)]
    #[Groups(['profile:read', 'profile:write'])]
    public int $weeklyGoal = 5;

    /** Seuil de dormance des clients gagnés en jours (0 = réactivation désactivée). */
    #[Assert\Range(min: 0, max: 730)]
    #[Groups(['profile:read', 'profile:write'])]
    public int $dormantClientThresholdDays = 120;

    /** Bilan hebdomadaire par email (opt-out). */
    #[Groups(['profile:read', 'profile:write'])]
    public bool $weeklyReportEnabled = true;

    #[Groups(['profile:read'])]
    public string $timezone = 'Europe/Paris';

    /** Fréquence du digest email des notifications (NONE = désactivé). */
    #[Assert\Choice(['NONE', 'DAILY', 'WEEKLY'])]
    #[Groups(['profile:read', 'profile:write'])]
    public string $digestFrequency = 'DAILY';

    /**
     * Séquence de relance : délais en jours entre étapes (ex. [7, 21, 45]). Vide = aucune relance auto.
     *
     * @var int[]
     */
    #[Assert\Count(max: 10)]
    #[Assert\All([new Assert\Type('integer'), new Assert\Range(min: 1, max: 365)])]
    #[Groups(['profile:read', 'profile:write'])]
    public array $followUpCadence = [7, 21, 45];

    /**
     * Libellés d'étapes du pipeline personnalisés (statut → libellé). Cosmétique (ADR-0031) : n'altère
     * ni le comportement ni l'API/export (qui restent sur les codes de statut).
     *
     * @var array<string, string>
     */
    #[Assert\Count(max: 20)]
    #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 40)])]
    #[Groups(['profile:read', 'profile:write'])]
    public array $pipelineLabels = [];

    /**
     * Préférences fines de notification par type et par canal (in-app / email). Défaut = tout activé :
     * on ne renvoie/n'accepte que les coupures. Ex. `{"candidate_to_triage": {"email": false}}`.
     *
     * @var array<string, array{inApp: bool, email: bool}>
     */
    #[Assert\Count(max: 20)]
    #[Assert\All([new Assert\Collection(
        fields: [
            'inApp' => new Assert\Optional(new Assert\Type('bool')),
            'email' => new Assert\Optional(new Assert\Type('bool')),
        ],
        allowExtraFields: false,
        allowMissingFields: true,
    )])]
    #[Groups(['profile:read', 'profile:write'])]
    public array $notificationPreferences = [];

    /** Présentation courte, matière première des brouillons générés. */
    #[Assert\Length(max: 2000)]
    #[Groups(['profile:read', 'profile:write'])]
    public ?string $bio = null;

    #[Assert\Length(max: 1000)]
    #[Groups(['profile:read', 'profile:write'])]
    public ?string $specialties = null;

    #[Assert\Length(max: 500)]
    #[Groups(['profile:read', 'profile:write'])]
    public ?string $signature = null;

    /** Identité affichée (« Bonjour {prénom} », en-tête). */
    #[Assert\Length(max: 100)]
    #[Groups(['profile:read', 'profile:write'])]
    public ?string $firstName = null;

    #[Assert\Length(max: 100)]
    #[Groups(['profile:read', 'profile:write'])]
    public ?string $lastName = null;
}
