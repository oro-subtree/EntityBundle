<?php

namespace Oro\Bundle\EntityBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\OptionsResolver\Options;
use Oro\Bundle\EntityBundle\Provider\EntityFieldProvider;
use Oro\Bundle\FormBundle\Form\Type\ChoiceListItem;

class EntityFieldChoiceType extends AbstractType
{
    const NAME = 'oro_entity_field_choice';

    /**
     * @var EntityFieldProvider
     */
    protected $provider;

    /**
     * Constructor
     *
     * @param EntityFieldProvider $provider
     */
    public function __construct(EntityFieldProvider $provider)
    {
        $this->provider       = $provider;
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $that    = $this;
        $choices = function (Options $options) use ($that) {
            return empty($options['entity'])
                ? array() // return empty list if entity is not specified
                : $that->getChoices($options['entity'], $options['with_relations']);
        };

        $resolver->setDefaults(
            array(
                'entity'         => null,
                'with_relations' => false,
                'choices'        => $choices,
                'empty_value'    => '',
                'configs'        => array(
                    'is_translate_option'     => false,
                    'placeholder'             => 'oro.entity.form.choose_entity_field',
                    'result_template_twig'    => 'OroEntityBundle:Choice:entity_field/result.html.twig',
                    'selection_template_twig' => 'OroEntityBundle:Choice:entity_field/selection.html.twig',
                )
            )
        );
    }

    /**
     * Returns a list of choices
     *
     * @param string $entityName    Entity name. Can be full class name or short form: Bundle:Entity.
     * @param bool   $withRelations Indicates whether fields of related entities should be returned as well.
     * @return array of entity fields
     *                              key = field name, value = ChoiceListItem
     */
    protected function getChoices($entityName, $withRelations)
    {
        $choices = array();
        $fields  = $this->provider->getFields($entityName, $withRelations, true);
        foreach ($fields as $field) {
            $attributes = [];
            foreach ($field as $key => $val) {
                if (!in_array($key, ['name'])) {
                    $attributes['data-' . str_replace('_', '-', $key)] = $val;
                }
            }
            $choices[$field['name']] = new ChoiceListItem($field['label'], $attributes);
        }

        return $choices;
    }

    /**
     * @param string $className
     * @param string $fieldName
     * @param bool   $withRelations
     * @return string
     */
    protected function getChoiceKey($className, $fieldName, $withRelations)
    {
        return $withRelations
            ? sprintf('%s::%s', $className, $fieldName)
            : $fieldName;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'genemu_jqueryselect2_choice';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }
}