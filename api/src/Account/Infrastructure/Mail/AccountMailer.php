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

    /**
     * Digest des notifications non lues de la période (récap bilingue). Le contenu est un simple
     * comptage par type — jamais de contenu de piste/message dans l'email (minimisation).
     *
     * @param array<string, int> $counts type de notification => nombre, non vide
     */
    public function sendNotificationDigest(string $email, array $counts): void
    {
        $app = rtrim($this->frontendUrl, '/');
        $fr = [];
        $en = [];
        foreach ($counts as $type => $count) {
            [$labelFr, $labelEn] = self::DIGEST_LABELS[$type] ?? [$type, $type];
            $fr[] = '• '.$count.' '.$labelFr;
            $en[] = '• '.$count.' '.$labelEn;
        }

        $this->mailer->send(
            (new Email())
                ->from($this->from)
                ->to($email)
                ->subject('Votre récap Plume / Your Plume digest')
                ->text(
                    "Bonjour,\n\nVoici ce qui s'est passé récemment :\n"
                    .implode("\n", $fr)."\n\n"
                    .'Ouvrir Plume : '.$app."\n\n"
                    ."(Pour changer la fréquence ou couper ces emails : Réglages.)\n\n"
                    ."— \n\n"
                    ."Hello,\n\nHere's what happened recently:\n"
                    .implode("\n", $en)."\n\n"
                    .'Open Plume: '.$app."\n\n"
                    ."(To change the frequency or turn these off: Settings.)\n",
                ),
        );
    }

    /** @var array<string, array{0: string, 1: string}> type => [libellé FR, libellé EN] */
    private const array DIGEST_LABELS = [
        'reply_received' => ['réponse(s) reçue(s)', 'reply(ies) received'],
        'followup_due' => ['relance(s) due(s)', 'follow-up(s) due'],
        'email_send_failed' => ["échec(s) d'envoi d'email", 'email send failure(s)'],
        'mailbox_disconnected' => ['boîte email à reconnecter', 'mailbox to reconnect'],
    ];
}
