<?php

namespace Oro\Bundle\EntityBundle\Routing;

use Symfony\Component\Routing\Route;

use Oro\Component\Routing\Resolver\RouteCollectionAccessor;
use Oro\Component\Routing\Resolver\RouteOptionsResolverInterface;

use Oro\Bundle\EntityBundle\ORM\EntityAliasResolver;
use Oro\Bundle\EntityBundle\Provider\ChainDictionaryValueListProvider;

class DictionaryEntityRouteOptionsResolver implements RouteOptionsResolverInterface
{
    const ROUTE_GROUP = 'dictionary_entity';
    const ENTITY_ATTRIBUTE = 'dictionary';
    const ENTITY_PLACEHOLDER = '{dictionary}';

    /** @var ChainDictionaryValueListProvider */
    protected $dictionaryProvider;

    /** @var EntityAliasResolver */
    protected $entityAliasResolver;

    /**
     * @param ChainDictionaryValueListProvider $dictionaryProvider
     * @param EntityAliasResolver              $entityAliasResolver
     */
    public function __construct(
        ChainDictionaryValueListProvider $dictionaryProvider,
        EntityAliasResolver $entityAliasResolver
    ) {
        $this->dictionaryProvider  = $dictionaryProvider;
        $this->entityAliasResolver = $entityAliasResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Route $route, RouteCollectionAccessor $routes)
    {
        if ($route->getOption('group') !== self::ROUTE_GROUP) {
            return;
        }

        if ($this->hasAttribute($route, self::ENTITY_PLACEHOLDER)) {
            $entities = array_map(
                function ($className) {
                    // convert to entity alias
                    return $this->entityAliasResolver->getPluralAlias($className);
                },
                $this->dictionaryProvider->getSupportedEntityClasses()
            );

            if (!empty($entities)) {
                $entities = $this->adjustRoutes($route, $routes, $entities);
                $route->setRequirement(self::ENTITY_ATTRIBUTE, implode('|', $entities));
            }
        }
    }

    /**
     * @param Route                   $route
     * @param RouteCollectionAccessor $routes
     * @param string[]                $entities
     *
     * @return string[] The list of entities handled by the default controller
     */
    protected function adjustRoutes(Route $route, RouteCollectionAccessor $routes, $entities)
    {
        $result    = [];
        $routeName = $routes->getName($route);

        foreach ($entities as $entity) {
            $existingRoute = $routes->getByPath(
                str_replace(self::ENTITY_PLACEHOLDER, $entity, $route->getPath()),
                $route->getMethods()
            );
            if ($existingRoute) {
                // move existing route before the current route
                $existingRouteName = $routes->getName($existingRoute);
                $routes->remove($existingRouteName);
                $routes->insert(
                    $existingRouteName,
                    $existingRoute,
                    $routeName,
                    true
                );
            } else {
                // add an additional strict route based on the base route and current entity
                $strictRoute = $routes->cloneRoute($route);
                $strictRoute->setPath(str_replace(self::ENTITY_PLACEHOLDER, $entity, $strictRoute->getPath()));
                $strictRoute->setDefault(self::ENTITY_ATTRIBUTE, $entity);
                $routes->insert(
                    $routes->generateRouteName($routeName),
                    $strictRoute,
                    $routeName,
                    true
                );
                $result[] = $entity;
            }
        }

        return $result;
    }

    /**
     * Checks if a route has the given attribute
     *
     * @param Route  $route
     * @param string $placeholder
     *
     * @return bool
     */
    protected function hasAttribute(Route $route, $placeholder)
    {
        return false !== strpos($route->getPath(), $placeholder);
    }
}
