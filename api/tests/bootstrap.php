<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

// Clés JWT éphémères pour les tests fonctionnels (indépendantes des clés de dev,
// passphrase déclarée dans phpunit.dist.xml — rien de secret ici).
$jwtDir = dirname(__DIR__).'/var/jwt-test';
if (!is_file($jwtDir.'/private.pem')) {
    if (!is_dir($jwtDir)) {
        mkdir($jwtDir, 0o775, true);
    }
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
    if (false === $key) {
        throw new RuntimeException('Impossible de générer la paire de clés JWT de test.');
    }
    openssl_pkey_export($key, $privateKey, 'test-only-not-a-secret');
    $details = openssl_pkey_get_details($key);
    if (!is_string($privateKey) || false === $details) {
        throw new RuntimeException('Impossible d\'exporter la paire de clés JWT de test.');
    }
    file_put_contents($jwtDir.'/private.pem', $privateKey);
    file_put_contents($jwtDir.'/public.pem', $details['key']);
}
