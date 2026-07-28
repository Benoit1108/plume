<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Emails SYSTÈME (transactionnels) du compte : réinitialisation de mot de passe, et à venir
 * vérification d'email / bienvenue / rappel avant purge. À NE PAS confondre avec la passerelle
 * Mailbox qui envoie AU NOM DE la traductrice via son OAuth : ici l'expéditeur est le produit
 * (`MAIL_FROM`), via le mailer Symfony (`MAILER_DSN` — `null://null` en dev, réel en prod).
 */
final class AccountMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(MAIL_FROM)%')]
        private readonly string $from,
        #[Autowire('%env(APP_FRONTEND_URL)%')]
        private readonly string $frontendUrl,
    ) {
    }

    public function sendEmailVerification(string $email, string $token): void
    {
        $url = rtrim($this->frontendUrl, '/').'/verify-email?token='.rawurlencode($token);

        $this->mailer->send(
            (new Email())
                ->from($this->from)
                ->to($email)
                ->subject('Confirmez votre adresse email / Confirm your email')
                ->text(
                    "Bienvenue !\n\n"
                    ."Confirmez votre adresse email pour activer votre compte (lien valable 24 h) :\n"
                    .$url."\n\n"
                    ."— \n\n"
                    ."Welcome!\n\n"
                    ."Confirm your email address to activate your account (link valid for 24h):\n"
                    .$url."\n",
                ),
        );
    }

    public function sendPasswordReset(string $email, string $token): void
    {
        $url = rtrim($this->frontendUrl, '/').'/reset-password?token='.rawurlencode($token);

        $this->mailer->send(
            (new Email())
                ->from($this->from)
                ->to($email)
                ->subject('Réinitialisation de votre mot de passe / Reset your password')
                ->text(
                    "Bonjour,\n\n"
                    ."Vous avez demandé à réinitialiser votre mot de passe. Ce lien est valable 1 heure :\n"
                    .$url."\n\n"
                    ."Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.\n\n"
                    ."— \n\n"
                    ."Hello,\n\n"
                    ."You requested a password reset. This link is valid for 1 hour:\n"
                    .$url."\n\n"
                    ."If you didn't request this, please ignore this email.\n",
                ),
        );
    }
}
