<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\ApiResource\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** Corps de POST /candidate-leads/{id}/merge — rattache à une organisation existante + piste. */
final class CandidateMergeInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $organizationId = '';

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-zA-Z]{2}>[a-zA-Z]{2}$/', message: 'Paire de langues attendue au format « en>fr ».')]
    public string $languagePair = '';

    #[Assert\Choice(['PUBLISHING', 'AUDIOVISUAL', 'TECHNICAL', 'OTHER'])]
    public string $segment = 'OTHER';

    #[Assert\Choice(['LOW', 'MEDIUM', 'HIGH'])]
    public string $priority = 'MEDIUM';
}
