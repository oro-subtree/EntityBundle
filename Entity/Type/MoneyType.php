<?php

namespace Oro\Bundle\EntityBundle\Entity\Type;

use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class MoneyType extends DecimalType
{
    const MONEY_TYPE = 'money';
    const TYPE_PRECISION = 19;
    const TYPE_SCALE = 4;

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::MONEY_TYPE;
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        $fieldDeclaration['precision'] = self::TYPE_PRECISION;
        $fieldDeclaration['scale'] = self::TYPE_SCALE;
        return $platform->getDecimalTypeDeclarationSQL($fieldDeclaration);
    }
}
