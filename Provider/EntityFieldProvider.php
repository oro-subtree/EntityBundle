<?php

namespace Oro\Bundle\EntityBundle\Provider;

use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\Translation\Translator;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;

use Oro\Bundle\EntityBundle\ORM\EntityClassResolver;
use Oro\Bundle\EntityBundle\Exception\InvalidEntityException;

use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Tools\ConfigHelper;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendConfigDumper;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class EntityFieldProvider
{
    /**
     * @var ConfigProvider
     */
    protected $entityConfigProvider;

    /**
     * @var ConfigProvider
     */
    protected $extendConfigProvider;

    /**
     * @var EntityClassResolver
     */
    protected $entityClassResolver;

    /**
     * @var ManagerRegistry
     */
    protected $doctrine;

    /**
     * @var EntityProvider
     */
    protected $entityProvider;

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var array
     */
    protected $virtualFields;

    /**
     * @var array
     */
    protected $hiddenFields;

    /**
     * Constructor
     *
     * @param ConfigProvider      $entityConfigProvider
     * @param ConfigProvider      $extendConfigProvider
     * @param EntityClassResolver $entityClassResolver
     * @param ManagerRegistry     $doctrine
     * @param Translator          $translator
     * @param array               $virtualFields
     * @param array               $hiddenFields
     */
    public function __construct(
        ConfigProvider $entityConfigProvider,
        ConfigProvider $extendConfigProvider,
        EntityClassResolver $entityClassResolver,
        ManagerRegistry $doctrine,
        Translator $translator,
        $virtualFields,
        $hiddenFields
    ) {
        $this->entityConfigProvider = $entityConfigProvider;
        $this->extendConfigProvider = $extendConfigProvider;
        $this->entityClassResolver  = $entityClassResolver;
        $this->doctrine             = $doctrine;
        $this->translator           = $translator;
        $this->virtualFields        = $virtualFields;
        $this->hiddenFields         = $hiddenFields;
    }

    /**
     * Sets entity provider
     *
     * @param EntityProvider $entityProvider
     */
    public function setEntityProvider(EntityProvider $entityProvider)
    {
        $this->entityProvider = $entityProvider;
    }

    /**
     * Returns fields for the given entity
     *
     * @param string $entityName             Entity name. Can be full class name or short form: Bundle:Entity.
     * @param bool   $withRelations          Indicates whether association fields should be returned as well.
     * @param bool   $withVirtualFields      Indicates whether virtual fields should be returned as well.
     * @param bool   $withEntityDetails      Indicates whether details of related entity should be returned as well.
     * @param int    $deepLevel              The maximum deep level of related entities.
     * @param bool   $lastDeepLevelRelations Indicates whether fields for the last deep level of related entities
     *                                       should be returned.
     * @param bool   $translate              Flag means that label, plural label should be translated
     * @return array of fields sorted by field label (relations follows fields)
     *                                       .       'name'          - field name
     *                                       .       'type'          - field type
     *                                       .       'label'         - field label
     *                                       If a field is an identifier (primary key in terms of a database)
     *                                       .       'identifier'    - true for an identifier field
     *                                       If a field represents a relation and $withRelations = true
     *                                       the following attributes are added:
     *                                       .       'relation_type'       - relation type
     *                                       .       'related_entity_name' - entity full class name
     *                                       If a field represents a relation and $withEntityDetails = true
     *                                       the following attributes are added:
     *                                       .       'related_entity_label'        - entity label
     *                                       .       'related_entity_plural_label' - entity plural label
     *                                       .       'related_entity_icon'         - an icon associated with an entity
     *                                       If a field represents a relation and $deepLevel > 0
     *                                       the related entity fields are added:
     *                                       .       'related_entity_fields'       - array of fields
     */
    public function getFields(
        $entityName,
        $withRelations = false,
        $withVirtualFields = false,
        $withEntityDetails = false,
        $deepLevel = 0,
        $lastDeepLevelRelations = false,
        $translate = true
    ) {
        $result    = array();
        $className = $this->entityClassResolver->getEntityClass($entityName);
        $em        = $this->getManagerForClass($className);
        $this->addFields($result, $className, $em, $withVirtualFields, $translate);
        if ($withRelations) {
            $this->addRelations(
                $result,
                $className,
                $em,
                $withVirtualFields,
                $withEntityDetails,
                $deepLevel - 1,
                $lastDeepLevelRelations,
                $translate
            );
        }
        $this->sortFields($result);

        return $result;
    }

    /**
     * Adds entity fields to $result
     *
     * @param array         $result
     * @param string        $className
     * @param EntityManager $em
     * @param bool          $withVirtualFields
     * @param bool          $translate
     */
    protected function addFields(array &$result, $className, EntityManager $em, $withVirtualFields, $translate)
    {
        // only configurable entities are supported
        if ($this->entityConfigProvider->hasConfig($className)) {
            $metadata = $em->getClassMetadata($className);

            // add regular fields
            foreach ($metadata->getFieldNames() as $fieldName) {
                if ($this->isIgnoredField($metadata, $fieldName)) {
                    continue;
                }

                $fieldLabel = $this->getFieldLabel($className, $fieldName);
                $this->addField(
                    $result,
                    $fieldName,
                    $metadata->getTypeOfField($fieldName),
                    $fieldLabel,
                    $metadata->isIdentifier($fieldName),
                    $translate
                );
            }

            // add virtual fields
            if ($withVirtualFields && isset($this->virtualFields[$className])) {
                foreach ($this->virtualFields[$className] as $fieldName => $config) {
                    if ($this->isIgnoredField($metadata, $fieldName)) {
                        continue;
                    }

                    $this->addField(
                        $result,
                        $fieldName,
                        $config['query']['select']['return_type'],
                        ConfigHelper::getTranslationKey('label', $className, $fieldName),
                        false,
                        $translate
                    );
                }
            }
        }
    }

    /**
     * Checks if the given field should be ignored
     *
     * @param ClassMetadata $metadata
     * @param string        $fieldName
     * @return bool
     */
    protected function isIgnoredField(ClassMetadata $metadata, $fieldName)
    {
        // @todo: use of $this->hiddenFields is a temporary solution (https://magecore.atlassian.net/browse/BAP-4142)
        if (isset($this->hiddenFields[$metadata->name][$fieldName])) {
            return true;
        }

        return false;
    }

    /**
     * Adds a field to $result
     *
     * @param array  $result
     * @param string $name
     * @param string $type
     * @param string $label
     * @param bool   $isIdentifier
     * @param bool   $translate
     */
    protected function addField(array &$result, $name, $type, $label, $isIdentifier, $translate)
    {
        $field = array(
            'name'  => $name,
            'type'  => $type,
            'label' => $translate ? $this->translator->trans($label) : $label
        );
        if ($isIdentifier) {
            $field['identifier'] = true;
        }
        $result[] = $field;
    }

    /**
     * Adds entity relations to $result
     *
     * @param array         $result
     * @param string        $className
     * @param EntityManager $em
     * @param bool          $withVirtualFields
     * @param bool          $withEntityDetails
     * @param int           $relationDeepLevel
     * @param bool          $lastDeepLevelRelations
     * @param bool          $translate
     */
    protected function addRelations(
        array &$result,
        $className,
        EntityManager $em,
        $withVirtualFields,
        $withEntityDetails,
        $relationDeepLevel,
        $lastDeepLevelRelations,
        $translate
    ) {
        // only configurable entities are supported
        if ($this->entityConfigProvider->hasConfig($className)) {
            $metadata         = $em->getClassMetadata($className);
            $associationNames = $metadata->getAssociationNames();
            foreach ($associationNames as $associationName) {
                $targetClassName = $metadata->getAssociationTargetClass($associationName);
                if ($this->entityConfigProvider->hasConfig($targetClassName)) {
                    if ($this->isIgnoredRelation($metadata, $associationName)) {
                        continue;
                    }

                    $targetFieldName = $metadata->getAssociationMappedByTargetField($associationName);
                    $targetMetadata  = $em->getClassMetadata($targetClassName);
                    $fieldLabel      = $this->getFieldLabel($className, $associationName);
                    $relationData    = array(
                        'name'                => $associationName,
                        'type'                => $targetMetadata->getTypeOfField($targetFieldName),
                        'label'               => $fieldLabel,
                        'relation_type'       => $this->getRelationType($className, $associationName),
                        'related_entity_name' => $targetClassName
                    );
                    $this->addRelation(
                        $result,
                        $relationData,
                        $withVirtualFields,
                        $withEntityDetails,
                        $relationDeepLevel,
                        $lastDeepLevelRelations,
                        $translate
                    );
                }
            }
        }
    }

    /**
     * Checks if the given relation should be ignored
     *
     * @param ClassMetadata $metadata
     * @param string        $associationName
     * @return bool
     */
    protected function isIgnoredRelation(ClassMetadata $metadata, $associationName)
    {
        // skip 'default_' extend field
        if (strpos($associationName, ExtendConfigDumper::DEFAULT_PREFIX) === 0) {
            $guessedFieldName = substr($associationName, strlen(ExtendConfigDumper::DEFAULT_PREFIX));
            if ($this->isExtendField($metadata->name, $guessedFieldName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds a relation to $result
     *
     * @param array $result
     * @param array $relation
     * @param bool  $withVirtualFields
     * @param bool  $withEntityDetails
     * @param int   $relationDeepLevel
     * @param bool  $lastDeepLevelRelations
     * @param bool  $translate
     */
    protected function addRelation(
        array &$result,
        array $relation,
        $withVirtualFields,
        $withEntityDetails,
        $relationDeepLevel,
        $lastDeepLevelRelations,
        $translate
    ) {
        $name = $relation['name'];
        if ($translate) {
            $relation['label'] = $this->translator->trans($relation['label']);
        }
        $relatedEntityName = $relation['related_entity_name'];
        if ($withEntityDetails) {
            $entity = $this->entityProvider->getEntity($relatedEntityName, $translate);
            foreach ($entity as $key => $val) {
                if (!in_array($key, ['name'])) {
                    $relation['related_entity_' . $key] = $val;
                }
            }
        }
        if ($relationDeepLevel >= 0) {
            // set some exceptions
            // todo: we need to find more proper way to do this
            if ($relationDeepLevel > 0 && ($name === 'owner' || $name === 'createdBy' || $name === 'updatedBy')) {
                $relationDeepLevel = 0;
            }
            $relation['related_entity_fields'] =
                $this->getFields(
                    $relatedEntityName,
                    $withEntityDetails && ($relationDeepLevel > 0 || $lastDeepLevelRelations),
                    $withVirtualFields,
                    $withEntityDetails,
                    $relationDeepLevel,
                    $lastDeepLevelRelations,
                    $translate
                );
        }

        $result[] = $relation;
    }

    /**
     * Gets doctrine entity manager for the given class
     *
     * @param string $className
     * @return EntityManager
     * @throws InvalidEntityException
     */
    protected function getManagerForClass($className)
    {
        $manager = null;
        try {
            $manager = $this->doctrine->getManagerForClass($className);
        } catch (\ReflectionException $ex) {
            // ignore not found exception
        }
        if (!$manager) {
            throw new InvalidEntityException(sprintf('The "%s" entity was not found.', $className));
        }

        return $manager;
    }

    /**
     * Checks whether the given field is extend or not.
     *
     * @param string $className
     * @param string $fieldName
     * @return bool
     */
    protected function isExtendField($className, $fieldName)
    {
        if ($this->extendConfigProvider->hasConfig($className, $fieldName)) {
            if ($this->extendConfigProvider->getConfig($className, $fieldName)->is('extend')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets a field label
     *
     * @param string $className
     * @param string $fieldName
     * @return string
     */
    protected function getFieldLabel($className, $fieldName)
    {
        if ($this->entityConfigProvider->hasConfig($className, $fieldName)) {
            return $this->entityConfigProvider->getConfig($className, $fieldName)->get('label');
        }

        return $fieldName;
    }

    /**
     * Gets a relation type
     *
     * @param string $className
     * @param string $fieldName
     * @return string
     */
    protected function getRelationType($className, $fieldName)
    {
        if ($this->entityConfigProvider->hasConfig($className, $fieldName)) {
            /** @var FieldConfigId $configId */
            $configId = $this->entityConfigProvider->getConfig($className, $fieldName)->getId();

            return $configId->getFieldType();
        }

        return '';
    }

    /**
     * Sorts fields by its label (relations follows fields)
     *
     * @param array $fields
     */
    protected function sortFields(array &$fields)
    {
        usort(
            $fields,
            function ($a, $b) {
                if (isset($a['related_entity_name']) !== isset($b['related_entity_name'])) {
                    if (isset($a['related_entity_name'])) {
                        return 1;
                    }
                    if (isset($b['related_entity_name'])) {
                        return -1;
                    }
                }

                return strcasecmp($a['label'], $b['label']);
            }
        );
    }
}
