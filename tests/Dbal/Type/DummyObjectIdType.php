<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Dbal\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Meilisearch\Bundle\Tests\Entity\ObjectId\DummyObjectId;

final class DummyObjectIdType extends Type
{
    public function getName(): string
    {
        return 'dummy_object_id';
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getIntegerTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?DummyObjectId
    {
        if ($value instanceof DummyObjectId || null === $value) {
            return $value;
        }

        if (!\is_string($value) && !is_int($value)) {
            throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', 'string', 'int', DummyObjectId::class]);
        }

        return new DummyObjectId((int) $value);
    }

    /**
     * @throws ConversionException
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?int
    {
        if ($value instanceof DummyObjectId) {
            return $value->toInt();
        }

        if (null === $value || '' === $value) {
            return null;
        }

        if (!\is_string($value) && !is_int($value)) {
            throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', 'string', 'int', DummyObjectId::class]);
        }

        return (int) $value;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
