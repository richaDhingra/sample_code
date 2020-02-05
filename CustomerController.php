<?php

namespace App\CoreBundle\Controller;

use Sylius\Bundle\UserBundle\Controller\CustomerController as Base;
use App\CoreBundle\Entity\Address;
use Sylius\Component\Core\Model\Customer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class CustomerController
 *
 * @package App\CoreBundle\Controller
 */
class CustomerController extends Base
{
    /**
     * @param Request $request
     *
     * @return Response
     */
    public function searchAction(Request $request)
    {
        if (!$request->query->has('criteria')) {
            throw new NotFoundHttpException();
        }

        /** @var $items Address[] */
        $results  = array();

        /** @var Customer[] $items */
        $items = $this->get('sylius.repository.customer')->createFilterPaginator($request->query->get('criteria'));

        foreach ($items as $item) {
            $results[] = [
                'id' => $item->getId(),
                'email' => $item->getEmail(),
                'first_name' => $item->getFirstName(),
                'last_name' => $item->getLastName()
            ];
        }

        return new JsonResponse($results);
    }
}
