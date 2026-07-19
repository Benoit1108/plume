<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\ApiResource\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** Corps de POST /candidate-leads/{id}/accept — crée une nouvelle organisation + piste. */
final class CandidateAcceptInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    public string $organizationName = '';

    #[Assert\Choice(['PUBLISHER', 'AV_STUDIO', 'AGENCY', 'OTHER'])]
    public string $organizationType = 'OTHER';

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-zA-Z]{2}>[a-zA-Z]{2}$/', message: 'Paire de langues attendue au format « en>fr ».')]
    public string $languagePair = '';

    #[Assert\Choice(['PUBLISHING', 'AUDIOVISUAL', 'TECHNICAL', 'OTHER'])]
    public string $segment = 'OTHER';

    #[Assert\Choice(['LOW', 'MEDIUM', 'HIGH'])]
    public string $priority = 'MEDIUM';

    #[Assert\Length(max: 2000)]
    public ?string $website = null;
}
