<?php

namespace Oro\Bundle\EntityBundle\Form\EntityField\Handler;

use Doctrine\Bundle\DoctrineBundle\Registry;

use Symfony\Component\Form\FormInterface;

use Oro\Bundle\EntityBundle\Form\EntityField\Handler\Processor\EntityApiHandlerProcessor;

class EntityApiBaseHandler
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var EntityApiHandlerProcessor
     */
    protected $processor;

    /**
     * @param Registry $registry
     * @param EntityApiHandlerProcessor $processor
     */
    public function __construct(Registry $registry, EntityApiHandlerProcessor $processor)
    {
        $this->registry = $registry;
        $this->processor = $processor;
    }

    /**
     * Process form
     *
     * @param $entity
     * @param FormInterface $form
     * @param array $data
     * @param string $method
     *
     * @return bool True on successful processing, false otherwise
     */
    public function process($entity, FormInterface $form, $data, $method)
    {
        $this->processor->preProcess($entity);
        $form->setData($entity);

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $form->submit($data);

            if ($form->isValid()) {
                $this->processor->beforeProcess($entity);
                $this->onSuccess($entity);
                $this->processor->afterProcess($entity);

                return true;
            } else {
                $this->processor->invalidateProcess($entity);
            }
        }

        return false;
    }

    /**
     * "Success" form handler
     *
     * @param $entity
     */
    protected function onSuccess($entity)
    {
        $this->registry->getManager()->persist($entity);
        $this->registry->getManager()->flush();
    }
}
