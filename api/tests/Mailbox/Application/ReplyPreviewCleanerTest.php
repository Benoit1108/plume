<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Application;

use App\Mailbox\Application\ReplyPreviewCleaner;
use PHPUnit\Framework\TestCase;

/**
 * Nettoyage de l'aperçu de réponse : on garde le texte frais, on écarte signature + message cité.
 * Best-effort (heuristique) — on couvre les cas fréquents Gmail/Outlook FR & EN.
 */
final class ReplyPreviewCleanerTest extends TestCase
{
    public function testStripsMobileSignatureAndQuotedHeader(): void
    {
        // Cas RÉEL capté sur le compte de test (réponse courte + signature + en-tête cité).
        $raw = 'Test mail Envoyé à partir de Outlook pour iOS De : plume.dev.test@gmail.com <plume.dev.test@gmail.com> '
            .'Envoyé : Thursday, 23 July 2026 14:11:10 À : USER CONTACT <benoit.2001@hotmail.fr>';

        self::assertSame('Test mail', ReplyPreviewCleaner::clean($raw));
    }

    public function testStripsFrenchGmailAttribution(): void
    {
        $raw = 'Merci beaucoup, je regarde ça. Le lun. 21 juil. 2026 à 10:00, Plume <x@y.com> a écrit : > proposition';

        self::assertSame('Merci beaucoup, je regarde ça.', ReplyPreviewCleaner::clean($raw));
    }

    public function testStripsEnglishAttribution(): void
    {
        $raw = 'Sounds good to me. On Mon, Jul 21, 2026 at 10:00 AM Plume <x@y.com> wrote: > original';

        self::assertSame('Sounds good to me.', ReplyPreviewCleaner::clean($raw));
    }

    public function testStripsOutlookWebQuotedHeader(): void
    {
        $raw = 'Bonjour, je suis intéressé. De : Plume <x@y.com> Envoyé : jeudi À : moi';

        self::assertSame('Bonjour, je suis intéressé.', ReplyPreviewCleaner::clean($raw));
    }

    public function testStripsHtmlAndCollapsesWhitespace(): void
    {
        self::assertSame('Ok pour moi', ReplyPreviewCleaner::clean("<p>Ok\n   pour   moi</p>"));
    }

    public function testFallsBackToRawWhenNothingFreshRemains(): void
    {
        // Que du cité (pas de texte frais avant le marqueur) → on garde l'aperçu brut plutôt que vide.
        $raw = 'De : Plume <x@y.com> Envoyé : jeudi À : moi';

        self::assertNotSame('', ReplyPreviewCleaner::clean($raw));
    }

    public function testEmptyStaysEmpty(): void
    {
        self::assertSame('', ReplyPreviewCleaner::clean('   '));
    }

    public function testCapsLength(): void
    {
        self::assertLessThanOrEqual(280, mb_strlen(ReplyPreviewCleaner::clean(str_repeat('a', 400))));
    }
}
