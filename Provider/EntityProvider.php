<?php

namespace Oro\Bundle\EntityBundle\Provider;

use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;

class EntityProvider
{
    /**
     * @var ConfigProvider
     */
    protected $entityConfigProvider;

    /**
     * Constructor
     *
     * @param ConfigProvider $entityConfigProvider
     */
    public function __construct(ConfigProvider $entityConfigProvider)
    {
        $this->entityConfigProvider = $entityConfigProvider;
    }

    /**
     * Returns entities
     *
     * @param bool $sortByPluralLabel If true entities will be sorted by 'plural_label'; otherwise, by 'label'
     * @return array of entities sorted by entity label
     *                                .    'name'          - entity full class name
     *                                .    'label'         - entity label
     *                                .    'plural_label'  - entity plural label
     *                                .    'icon'          - an icon associated with an entity
     */
    public function getEntities($sortByPluralLabel = true)
    {
        $result = array();
        $this->addEntities($result);
        $this->sortEntities($result, $sortByPluralLabel ? 'plural_label' : 'label');

        return $result;
    }

    /**
     * Adds entities to $result
     *
     * @param array $result
     */
    protected function addEntities(array &$result)
    {
        // only configurable entities are supported
        $configs = $this->entityConfigProvider->getConfigs();
        foreach ($configs as $config) {
            $this->addEntity(
                $result,
                $config->getId()->getClassName(),
                $config->get('label'),
                $config->get('plural_label'),
                $config->get('icon')
            );
        }
    }

    /**
     * Adds an entity to $result
     *
     * @param array  $result
     * @param string $name
     * @param string $label
     * @param string $pluralLabel
     * @param string $icon
     */
    protected function addEntity(array &$result, $name, $label, $pluralLabel, $icon)
    {
        $result[] = array(
            'name'         => $name,
            'label'        => $label,
            'plural_label' => $pluralLabel,
            'icon'         => $icon
        );
    }

    /**
     * Sorts entities by a value of the given attribute
     *
     * @param array  $entities
     * @param string $attrName
     */
    protected function sortEntities(array &$entities, $attrName)
    {
        usort(
            $entities,
            function ($a, $b) use (&$attrName) {
                return strcmp($a[$attrName], $b[$attrName]);
            }
        );
    }
}
