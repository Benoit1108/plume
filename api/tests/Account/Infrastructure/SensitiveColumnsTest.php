<?php

declare(strict_types=1);

namespace App\Tests\Account\Infrastructure;

use App\Account\Infrastructure\Export\SensitiveColumns;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * L'export RGPD ne doit jamais sortir un secret. La détection par motif attrape les colonnes
 * secrètes ACTUELLES et FUTURES (nommées autrement), sans laisser passer les colonnes métier.
 */
final class SensitiveColumnsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function sensitiveColumns(): iterable
    {
        yield 'access_token' => ['access_token'];
        yield 'refresh_token' => ['refresh_token'];
        yield 'sync_cursor' => ['sync_cursor'];
        yield 'password' => ['password'];
        yield 'id_token (futur)' => ['id_token'];
        yield 'webhook_secret (futur)' => ['webhook_secret'];
        yield 'api_key (futur)' => ['api_key'];
        yield 'apikey (futur)' => ['apikey'];
        yield 'encrypted_payload (futur)' => ['encrypted_payload'];
        yield 'client_secret (futur)' => ['client_secret'];
    }

    #[DataProvider('sensitiveColumns')]
    public function testDetectsSensitiveColumns(string $column): void
    {
        self::assertTrue(SensitiveColumns::isSensitive($column));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function safeColumns(): iterable
    {
        yield 'name' => ['name'];
        yield 'status' => ['status'];
        yield 'email_address' => ['email_address'];
        yield 'website' => ['website'];
        yield 'tenant_id' => ['tenant_id'];
        yield 'created_at' => ['created_at'];
        yield 'language_pair' => ['language_pair'];
    }

    #[DataProvider('safeColumns')]
    public function testKeepsBusinessColumns(string $column): void
    {
        self::assertFalse(SensitiveColumns::isSensitive($column));
    }

    public function testStripRemovesOnlySensitiveKeys(): void
    {
        $row = ['name' => 'X', 'access_token' => 'secret', 'status' => 'CONNECTED', 'refresh_token' => 's', 'sync_cursor' => 'c'];

        self::assertSame(['name' => 'X', 'status' => 'CONNECTED'], SensitiveColumns::strip($row));
    }
}
