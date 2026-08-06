<?php

declare(strict_types=1);

namespace App\Tests\Account\Infrastructure;

use App\Account\Infrastructure\Auth\DeviceLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Le résumé d'un User-Agent doit rester juste sur les pièges classiques : Edge/Opera se déclarent
 * « Chrome », Chrome se déclare « Safari », iOS et Android portent « Mac OS X »/« Linux ».
 * Une erreur d'ordre afficherait « Safari · Linux » pour Chrome sur Android — donc une session
 * que l'utilisatrice ne reconnaît pas, ou pire, qu'elle croit reconnaître.
 */
final class DeviceLabelTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string, ?string}>
     */
    public static function userAgents(): iterable
    {
        yield 'Firefox sur Linux' => [
            'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0',
            'Firefox', 'Linux',
        ];
        yield 'Chrome sur Windows' => [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'Chrome', 'Windows',
        ];
        yield 'Edge se déclare Chrome' => [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0',
            'Edge', 'Windows',
        ];
        yield 'Opera se déclare Chrome' => [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 OPR/112.0.0.0',
            'Opera', 'macOS',
        ];
        yield 'Safari sur iPhone (porte « Mac OS X »)' => [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
            'Safari', 'iPhone',
        ];
        yield 'Chrome sur iOS (CriOS)' => [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/126.0.0.0 Mobile/15E148 Safari/604.1',
            'Chrome', 'iPhone',
        ];
        yield 'Chrome sur Android (porte « Linux »)' => [
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
            'Chrome', 'Android',
        ];
        yield 'agent non reconnu' => ['PlumeBot/1.0', null, null];
    }

    #[DataProvider('userAgents')]
    public function testSummarizes(string $userAgent, ?string $browser, ?string $platform): void
    {
        $label = DeviceLabel::fromUserAgent($userAgent);

        self::assertSame($browser, $label->browser);
        self::assertSame($platform, $label->platform);
    }

    public function testEmptyOrMissingAgentYieldsNothing(): void
    {
        foreach ([null, '', '   '] as $userAgent) {
            $label = DeviceLabel::fromUserAgent($userAgent);
            self::assertNull($label->browser);
            self::assertNull($label->platform);
        }
    }
}
