<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Mailbox\Application\Exception\MailSendFailed;
use App\Mailbox\Infrastructure\Token\AccessTokenMinter;

/** Frappe factice : renvoie un access token fixe (ou lève, pour tester l'échec). */
final class FakeAccessTokenMinter implements AccessTokenMinter
{
    public function __construct(private readonly bool $fails = false)
    {
    }

    public function mint(string $refreshTokenPlain): string
    {
        if ($this->fails) {
            throw MailSendFailed::because('token refresh failed');
        }

        return 'fresh-access-token';
    }
}
