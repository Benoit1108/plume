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

    #[Groups(['profile:read'])]
    public string $timezone = 'Europe/Paris';

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
}
