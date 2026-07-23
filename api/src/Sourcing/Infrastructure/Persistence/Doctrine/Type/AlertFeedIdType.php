<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractStringIdType;
use App\Sourcing\Domain\AlertFeed\AlertFeedId;

/** Type DBAL pour le VO AlertFeedId (persisté en chaîne). */
final class AlertFeedIdType extends AbstractStringIdType
{
    public const string NAME = 'alert_feed_id';

    public function getName(): string
    {
        return self::NAME;
    }

    protected function idClass(): string
    {
        return AlertFeedId::class;
    }
}
