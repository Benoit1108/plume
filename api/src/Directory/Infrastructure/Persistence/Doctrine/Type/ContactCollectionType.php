<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\Persistence\Doctrine\Type;

use App\Directory\Domain\Organization\Contact;
use App\Directory\Domain\Organization\ContactId;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\LanguageCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;

/**
 * Collection de Contact (entités de l'agrégat) persistée en JSON sur la ligne Organization.
 * L'agrégat étant chargé/sauvé en bloc, pas besoin de table enfant : le domaine reste pur.
 */
final class ContactCollectionType extends JsonType
{
    public const string NAME = 'contact_collection';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): string
    {
        $rows = [];
        if (\is_array($value)) {
            foreach ($value as $contact) {
                if (!$contact instanceof Contact) {
                    continue;
                }
                $rows[] = [
                    'id' => $contact->id()->toString(),
                    'fullName' => $contact->fullName(),
                    'role' => $contact->role(),
                    'email' => $contact->email()?->toString(),
                    'phone' => $contact->phone(),
                    'linkedinUrl' => $contact->linkedinUrl(),
                    'preferredLanguage' => $contact->preferredLanguage()?->toString(),
                    'doNotContact' => $contact->doNotContact(),
                ];
            }
        }

        return parent::convertToDatabaseValue($rows, $platform);
    }

    /** @return Contact[] */
    public function convertToPHPValue($value, AbstractPlatform $platform): array
    {
        $decoded = parent::convertToPHPValue($value, $platform);
        if (!\is_array($decoded)) {
            return [];
        }

        $contacts = [];
        foreach ($decoded as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $id = isset($row['id']) && \is_string($row['id']) ? $row['id'] : '';
            if ('' === $id) {
                continue;
            }

            $contact = new Contact(
                ContactId::fromString($id),
                isset($row['fullName']) && \is_string($row['fullName']) ? $row['fullName'] : '',
                isset($row['role']) && \is_string($row['role']) ? $row['role'] : null,
                isset($row['email']) && \is_string($row['email']) ? EmailAddress::fromString($row['email']) : null,
                isset($row['phone']) && \is_string($row['phone']) ? $row['phone'] : null,
                isset($row['linkedinUrl']) && \is_string($row['linkedinUrl']) ? $row['linkedinUrl'] : null,
                isset($row['preferredLanguage']) && \is_string($row['preferredLanguage']) ? LanguageCode::fromString($row['preferredLanguage']) : null,
            );
            if (true === ($row['doNotContact'] ?? false)) {
                $contact->markDoNotContact();
            }

            $contacts[] = $contact;
        }

        return $contacts;
    }
}
