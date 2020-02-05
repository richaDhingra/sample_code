<?php

namespace App\CoreBundle\Controller;

use App\CoreBundle\Entity\Address;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\Serializer;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Address controller
 */
class AddressController extends ResourceController
{

    /**
     * Search address by criteria
     *
     * @param Request $request
     *
     * @return Response
     */
    public function searchAction(Request $request)
    {
        if (!$request->query->has('criteria')) {
            throw $this->createNotFoundException();
        }

        /** @var $items Address[] */
        $results  = array();
        $items = $this->get('sylius.repository.address')->createFilterPaginator($request->query->get('criteria'));

        foreach ($items as $item) {
            $results[] = $item;
        }
        /** @var Serializer $serializer */
        $serializer = $this->get('serializer');
        $context = new SerializationContext();
        $context->setGroups(["API"]);

        $json = $serializer->serialize($results, 'json', $context);

        return new Response($json);
    }

}