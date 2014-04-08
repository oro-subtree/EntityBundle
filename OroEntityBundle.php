<?php

namespace Oro\Bundle\EntityBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use Doctrine\DBAL\Types\Type;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;

use Oro\Bundle\EntityBundle\Entity\Type\MoneyType;
use Oro\Bundle\EntityBundle\Entity\Type\PercentType;

use Oro\Bundle\EntityBundle\DependencyInjection\Compiler\DoctrineSqlFiltersConfigurationPass;

class OroEntityBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new DoctrineSqlFiltersConfigurationPass());
    }

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        if (!Type::hasType(MoneyType::TYPE)) {
            Type::addType(MoneyType::TYPE, 'Oro\Bundle\EntityBundle\Entity\Type\MoneyType');
        }

        if (!Type::hasType(PercentType::TYPE)) {
            Type::addType(PercentType::TYPE, 'Oro\Bundle\EntityBundle\Entity\Type\PercentType');
        }

        /** @var ManagerRegistry $registry */
        $registry = $this->container->get('doctrine');
        foreach ($registry->getConnections() as $con) {
            if ($con instanceof Connection) {
                $con->getDatabasePlatform()->markDoctrineTypeCommented(MoneyType::TYPE);
                $con->getDatabasePlatform()->markDoctrineTypeCommented(PercentType::TYPE);
            }
        }
    }
}
