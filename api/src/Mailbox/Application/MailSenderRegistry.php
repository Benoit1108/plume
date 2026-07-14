<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

/** Un expéditeur par fournisseur — la boîte connectée choisit lequel. */
interface MailSenderRegistry
{
    public function senderFor(string $provider): MailSender;
}
