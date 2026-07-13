<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Uid;

use App\Shared\Application\IdGenerator;
use Symfony\Component\Uid\Uuid;

final class UuidV7IdGenerator implements IdGenerator
{
    public function generate(): string
    {
        return Uuid::v7()->toRfc4122();
    }
}
