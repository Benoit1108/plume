<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\ApiResource\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** Corps de POST /sources — ajoute un flux d'annonces (RSS en M3.1b). */
final class AlertFeedInput
{
    #[Assert\Choice(['RSS'])]
    public string $source = 'RSS';

    #[Assert\NotBlank]
    #[Assert\Url(requireTld: true)]
    #[Assert\Length(max: 2000)]
    public string $url = '';

    #[Assert\Length(max: 120)]
    public ?string $label = null;
}
