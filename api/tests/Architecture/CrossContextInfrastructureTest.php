<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Frontière inter-contextes côté INFRASTRUCTURE (angle mort de deptrac, revue pré-V2).
 *
 * deptrac ne couvre que Domain+Application. Rien n'empêche donc `Sourcing\Infrastructure` de
 * faire `$em->find(Prospecting\Domain\Lead::class)` ou `new Lead(...)` — accès direct à
 * l'agrégat d'un autre contexte. Ce test interdit à l'Infrastructure d'un contexte d'IMPORTER
 * le `Domain` d'un AUTRE contexte, SAUF ses events publiés (`Domain\...\Event\...`, langage
 * publié consommé par les projecteurs/policies). Les échanges cross-contexte légitimes passent
 * par les events ou les ports Application (commandes/queries via bus) — non concernés ici.
 */
final class CrossContextInfrastructureTest extends TestCase
{
    public function testInfrastructureDoesNotReachIntoAnotherContextDomain(): void
    {
        $finder = (new Finder())->files()->in(\dirname(__DIR__, 2).'/src')->path('/Infrastructure/')->name('*.php');

        $violations = [];
        foreach ($finder as $file) {
            $code = $file->getContents();
            if (1 !== preg_match('/namespace\s+App\\\\([A-Za-z]+)\\\\/', $code, $ns)) {
                continue;
            }
            $context = $ns[1];
            // SharedKernel n'est pas un contexte métier : c'est le noyau/racine partagé (et l'hôte
            // du seeder de dev, qui construit légitimement les fixtures de tous les contextes).
            if ('Shared' === $context) {
                continue;
            }

            preg_match_all('/^use\s+(App\\\\[A-Za-z0-9_\\\\]+)\s*;/m', $code, $uses);
            foreach ($uses[1] as $import) {
                if (1 !== preg_match('/^App\\\\([A-Za-z]+)\\\\Domain\\\\/', $import, $m)) {
                    continue; // pas un import de Domain
                }
                $importedContext = $m[1];
                if ($importedContext === $context || 'Shared' === $importedContext) {
                    continue; // même contexte ou noyau partagé : autorisé
                }
                if (str_contains($import, '\\Event\\')) {
                    continue; // event publié : autorisé (langage publié)
                }
                $violations[] = \sprintf('%s importe %s', $file->getRelativePathname(), $import);
            }
        }

        self::assertSame([], $violations, "L'Infrastructure d'un contexte ne doit dépendre du Domaine d'un autre que par ses events :\n".implode("\n", $violations));
    }
}
