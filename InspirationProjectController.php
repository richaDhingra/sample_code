<?php

namespace App\InspirationBundle\Controller;

use App\CoreBundle\Controller\SortActionTrait;
use App\CoreBundle\Entity\InspirationProject;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class InspirationProjectController
 *
 * @package App\SampleBundle\Controller
 */
class InspirationProjectController extends ResourceController
{
    use SortActionTrait;

    public function getRepository()
    {
        return $this->manager->getRepository(InspirationProject::class);
    }

}