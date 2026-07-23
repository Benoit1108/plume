<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

/**
 * Nettoie l'aperçu d'une réponse email pour n'en garder (au mieux) que le TEXTE FRAIS : les
 * snippets/bodyPreview des fournisseurs embarquent souvent la signature de l'expéditeur puis
 * l'email d'origine cité (en-tête « De :/From: », attribution « Le … a écrit : »). On coupe au
 * premier marqueur connu.
 *
 * Best-effort et heuristique (l'extraction parfaite du texte d'une réponse est un problème
 * ouvert) : si le nettoyage ne laisse rien, on retombe sur l'aperçu brut (borné). Aperçu
 * uniquement — jamais utilisé pour une logique métier.
 */
final class ReplyPreviewCleaner
{
    private const int MAX = 280;

    /** Marqueurs (minuscule) à partir desquels on coupe : signatures mobiles + en-têtes cités. */
    private const array CUT_MARKERS = [
        'envoyé à partir de',
        'envoyé de mon',
        'sent from my',
        'sent from outlook',
        'obtenez outlook',
        'get outlook',
        '-----original message-----',
        '________________________________',
        'de :',
        'from:',
    ];

    public static function clean(string $raw): string
    {
        // Une seule ligne, sans balises ni entités (jamais de HTML dans un aperçu).
        $text = trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($raw), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')));
        if ('' === $text) {
            return '';
        }

        // Attribution du message cité (« Le <date> … a écrit : », « On <date> … wrote: ») → on tronque.
        // Lazy (`.{0,N}?`) pour traverser les deux-points d'une heure (« 10:00 ») sans s'arrêter.
        $text = (string) preg_replace(['/\ble\b.{0,150}?a écrit\s*:.*/iu', '/\bon\b.{0,150}?wrote\s*:.*/iu'], '', $text);

        $low = mb_strtolower($text);
        $cut = mb_strlen($text);
        foreach (self::CUT_MARKERS as $marker) {
            $pos = mb_strpos($low, $marker);
            if (false !== $pos) {
                $cut = min($cut, $pos);
            }
        }

        $cleaned = trim(mb_substr($text, 0, $cut));
        if ('' === $cleaned) {
            $cleaned = trim($text); // rien de frais isolable → on garde l'aperçu tel quel
        }

        return mb_substr($cleaned, 0, self::MAX);
    }
}
