<?php

namespace Oro\Bundle\EntityBundle\Controller\Api\Rest;

use Rhumsaa\Uuid\Console\Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use FOS\RestBundle\Controller\Annotations\NamePrefix;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\Controller\Annotations as Rest;

use Nelmio\ApiDocBundle\Annotation\ApiDoc;

use Oro\Bundle\EntityBundle\Exception\EntityHasFieldException;
use Oro\Bundle\EntityBundle\Exception\FieldUpdateAccessException;
use Oro\Bundle\SecurityBundle\SecurityFacade;

/**
 * @RouteResource("entity_data")
 * @NamePrefix("oro_api_")
 */
class EntityDataController extends FOSRestController
{
    /**
     * Patch entity field/s data by new values
     *
     * @param int $id
     * @param int $className
     *
     * @return Response
     *
     * @throws AccessDeniedException
     *
     * @Rest\Patch("entity/{className}/{id}")
     * @ApiDoc(
     *      description="Update entity property",
     *      resource=true,
     *      requirements = {
     *          {"name"="id", "dataType"="integer"},
     *      }
     * )
     */
    public function patchAction($className, $id)
    {
        try {
            $data = json_decode($this->get('request_stack')->getCurrentRequest()->getContent(), true);
            list($form, $data) = $this->getManager()->patch($className, $id, $data);

            if ($form->getErrors()->count() > 0) {
                $view = $this->view($form, Codes::HTTP_BAD_REQUEST);
            } else {
                $view = $this->view($data, Codes::HTTP_NO_CONTENT);
            }
        } catch (\Exception $e) {
            $view = $this->view($e->getMessage(), $e->getCode());
        }
        $response = parent::handleView($view);

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function getManager()
    {
        return $this->get('oro_entity.manager.api.enitty_data_api_manager');
    }
}
