<?php

namespace Oro\Bundle\EntityBundle\Manager\Api;

use FOS\RestBundle\Util\Codes;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

use Oro\Bundle\EntityBundle\Tools\EntityRoutingHelper;
use Oro\Bundle\EntityBundle\Entity\Manager\Field\EntityFieldManager;

class EntityDataApiManager
{
    /** @var  EntityFieldManager */
    protected $entityDataManager;

    /** @var  AuthorizationChecker */
    protected $securityService;

    /** @var EntityRoutingHelper */
    protected $entityRoutingHelper;

    /**
     * @param EntityFieldManager   $entityDataManager
     * @param AuthorizationChecker $securityService
     * @param EntityRoutingHelper  $entityRoutingHelper
     */
    public function __construct(
        EntityFieldManager $entityDataManager,
        AuthorizationChecker $securityService,
        EntityRoutingHelper $entityRoutingHelper
    ) {
        $this->entityDataManager = $entityDataManager;
        $this->securityService = $securityService;
        $this->entityRoutingHelper = $entityRoutingHelper;
    }

    /**
     * @param string $className
     * @param int    $id
     * @param array  $data
     *
     * @return array
     *
     * @throws \Exception
     */
    public function patch($className, $id, $data)
    {
        $entity = $this->getEntity($className, $id);

        if (!$this->securityService->isGranted('EDIT', $entity)) {
            throw new AccessDeniedException();
        }

        return $this->entityDataManager->update($entity, $data);
    }

    /**
     * @param string $className
     * @param int    $id
     *
     * @return object
     *
     * @throws \Exception
     */
    protected function getEntity($className, $id)
    {
        try {
            $entity = $this->entityRoutingHelper->getEntity($className, $id);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), Codes::HTTP_NOT_FOUND);
        }

        return $entity;
    }
}
