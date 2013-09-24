<?php

namespace Oro\Bundle\EntityBundle\Datagrid;

use Oro\Bundle\GridBundle\Datagrid\DatagridManager;
use Oro\Bundle\GridBundle\Datagrid\ORM\EntityProxyQuery;
use Oro\Bundle\GridBundle\Datagrid\ResultRecord;
use Oro\Bundle\GridBundle\Field\FieldDescription;
use Oro\Bundle\GridBundle\Field\FieldDescriptionCollection;
use Oro\Bundle\GridBundle\Action\ActionInterface;
use Oro\Bundle\GridBundle\Property\CallbackProperty;

use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigIdInterface;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer;

use Oro\Bundle\EntityExtendBundle\Extend\ExtendManager;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendConfigDumper;

class CustomEntityDatagrid extends DatagridManager
{
    protected $configManager;

    /** @var null original entity class */
    protected $entityClass = null;

    /** @var array fields to be shown on grid */
    protected $queryFields = array();

    /** @var  integer parent entity id */
    protected $parentId;

    protected $filterMap = array(
        'string'   => 'oro_grid_orm_string',
        'integer'  => 'oro_grid_orm_number',
        'smallint' => 'oro_grid_orm_number',
        'bigint'   => 'oro_grid_orm_number',
        'boolean'  => 'oro_grid_orm_boolean',
        'decimal'  => 'oro_grid_orm_number',
        'date'     => 'oro_grid_orm_date_range',
        'text'     => 'oro_grid_orm_string',
        'float'    => 'oro_grid_orm_number',
    );

    protected $typeMap = array(
        'string'   => 'text',
        'integer'  => 'integer',
        'smallint' => 'integer',
        'bigint'   => 'integer',
        'boolean'  => 'boolean',
        'decimal'  => 'decimal',
        'date'     => 'date',
        'text'     => 'text',
        'float'    => 'decimal',
    );

    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }

    public function setCustomEntityClass($className)
    {
        $this->entityClass = $className;
    }

    /**
     * {@inheritDoc}
     */
    protected function configureFields(FieldDescriptionCollection $fieldsCollection)
    {
        $this->getDynamicFields($fieldsCollection);
    }

    /**
     * @return array
     */
    protected function getProperties()
    {
        return array(
            new CallbackProperty('view_link', $this->getLinkProperty('oro_entity_view')),
            new CallbackProperty('update_link', $this->getLinkProperty('oro_entity_update')),
            new CallbackProperty('delete_link', $this->getLinkProperty('oro_entity_delete')),
        );
    }

    protected function getLinkProperty($route)
    {
        $router    = $this->router;
        $className = $this->entityClass;

        return function (ResultRecord $record) use ($router, $className, $route) {
            return $router->generate(
                $route,
                array(
                    'entity_id' => str_replace('\\', '_', $className),
                    'id' => $record->getValue('id')
                )
            );
        };
    }

    /**
     * {@inheritDoc}
     */
    protected function getRowActions()
    {
        $aclDescriptor = 'entity:' . $this->entityName;

        $clickAction = array(
            'name'         => 'rowClick',
            'type'         => ActionInterface::TYPE_REDIRECT,
            'acl_resource' => 'VIEW;' . $aclDescriptor,
            'options'      => array(
                'label'         => 'View',
                'link'          => 'view_link',
                'route'         => 'oro_entity_view',
                'runOnRowClick' => true,
            )
        );

        $viewAction = array(
            'name'         => 'view',
            'type'         => ActionInterface::TYPE_REDIRECT,
            'acl_resource' => 'VIEW;' . $aclDescriptor,
            'options'      => array(
                'label' => 'View',
                'icon'  => 'file',
                'link'  => 'view_link',
            )
        );

        $updateAction = array(
            'name'         => 'update',
            'type'         => ActionInterface::TYPE_REDIRECT,
            'acl_resource' => 'EDIT;' . $aclDescriptor,
            'options'      => array(
                'label' => 'Update',
                'icon'  => 'edit',
                'link'  => 'update_link',
            )
        );

        $deleteAction = array(
            'name'         => 'delete',
            'type'         => ActionInterface::TYPE_DELETE,
            'acl_resource' => 'DELETE;' . $aclDescriptor,
            'options'      => array(
                'label' => 'Delete',
                'icon'  => 'trash',
                'link'  => 'delete_link',
            )
        );

        return array($clickAction, $viewAction, $updateAction, $deleteAction);
    }

    /**
     * @param FieldDescriptionCollection $fieldsCollection
     */
    protected function getDynamicFields(FieldDescriptionCollection $fieldsCollection)
    {
        $fields = array();

        /** @var ConfigProvider $extendConfigProvider */
        $extendConfigProvider = $this->configManager->getProvider('extend');
        $extendConfigs        = $extendConfigProvider->getConfigs($this->entityClass);

        foreach ($extendConfigs as $extendConfig) {
            if ($extendConfig->get('state') == ExtendManager::STATE_ACTIVE
                && !$extendConfig->get('is_deleted')

            ) {
                /** @var FieldConfigIdInterface $fieldConfig */
                $fieldConfig = $extendConfig->getId();

                /** @var ConfigProvider $datagridProvider */
                $datagridConfigProvider = $this->configManager->getProvider('datagrid');
                $datagridConfig         = $datagridConfigProvider->getConfig(
                    $this->entityClass,
                    $fieldConfig->getFieldName()
                );

                if ($datagridConfig->is('is_visible')) {
                    /** @var ConfigProvider $entityConfigProvider */
                    $entityConfigProvider = $this->configManager->getProvider('entity');
                    $entityConfig         = $entityConfigProvider->getConfig(
                        $this->entityClass,
                        $fieldConfig->getFieldName()
                    );

                    $label               = $entityConfig->get('label') ?: $fieldConfig->getFieldName();
                    $code                = ExtendConfigDumper::PREFIX . $fieldConfig->getFieldName();
                    $this->queryFields[] = $code;

                    $fieldObject = new FieldDescription();
                    $fieldObject->setName($code);

                    $fieldObject->setOptions(
                        array(
                            'type'        => $this->typeMap[$fieldConfig->getFieldType()],
                            'label'       => $label,
                            'field_name'  => $code,
                            'filter_type' => $this->filterMap[$fieldConfig->getFieldType()],
                            'required'    => false,
                            'sortable'    => true,
                            'filterable'  => true,
                            'show_filter' => true,
                        )
                    );

                    $fields[] = $fieldObject;
                }
            }
        }

        foreach ($fields as $field) {
            $fieldsCollection->add($field);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function createQuery()
    {
        /** @var EntityProxyQuery $query */
        $query = $this->queryFactory->createQuery();

        $queryBuilder = $query->getQueryBuilder();
        $queryBuilder->resetDQLPart('from')->from($this->entityClass, 'ce');

        foreach ($this->queryFields as $field) {
            $query->addSelect('ce.' . $field . ' as ' . $field, false);
        }

        $this->prepareQuery($query);

        return $query;
    }
}
