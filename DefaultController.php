<?php

namespace App\InspirationBundle\Controller;

use App\ContentBundle\Document\TopDesignTeaserBlock;
use App\CoreBundle\Entity\InspirationProject;
use Sonata\BlockBundle\Model\EmptyBlock;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use App\CoreBundle\Entity\AccountSavedOptions;

class DefaultController extends Controller
{
    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */

    public function indexAction()
    {
        $product = $this->get('sylius.repository.product')->findOneBySlug('kussen');
        $projects = $this->getDoctrine()
            ->getRepository('AppCoreBundle:InspirationProject')
            ->findBy(['product'=>$product->getId()], ['position' => 'ASC']);

        return $this->render(
            'AppWebBundle:Frontend/Inspiration:index.html.twig',
            array(
                'projects' => $projects
            )
        );
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function detailAction(Request $request)
    {
        $project = $this->getDoctrine()
            ->getRepository('AppCoreBundle:InspirationProject')
            ->findOneBy(array('slug' => $request->get('slug')));

        $topdesignblock = $this->getTopDesignBlock($project);
        return $this->render(
            'AppWebBundle:Frontend/Inspiration:detail.html.twig',
            array(
                'project' => $project,
                'topdesignblock' => $topdesignblock,
                'already_saved' =>$this->ifInspirationIsSaved($project->getId())
            )
        );
    }

    /**
     * @param InspirationProject|null $project
     *
     * @return null
     */
    private function getTopDesignBlock(InspirationProject $project = null)
    {
        $block = null;
        if ($project) {
            $blockContextManager = $this->get('sonata.block.context_manager');
            $name = '/cms/blocks/inspiratie_topdesign_' . $project->getSlug();
            $meta = array('name' => $name);
            $options = array();
            $blockContext = $blockContextManager->get($meta, $options);
            $block = $blockContext->getBlock();
            if ($block === null || $block instanceof EmptyBlock) {
                $block = new TopDesignTeaserBlock();
                $block->setTitle('Kussens in deze set');
                $block->setSubTitle('Bekijk meer ontwerpen van onze klanten');
                $block->setName('inspiratie_topdesign_' . $project->getSlug());
                $block->setEnabled(true);
                $documentManager = $this->get('doctrine_phpcr.odm.document_manager');
                $parentDocument = $documentManager->find(null, '/cms/blocks');
                $block->setParentDocument($parentDocument);
                $documentManager->persist($block);
                $documentManager->flush($block);
            }

//            $block =
        }
        return $block;
    }

    /**
     * @param $id
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function saveAction($id,Request $request){
        $project = $this->getDoctrine()
            ->getRepository('AppCoreBundle:InspirationProject')
            ->findOneBy(array('id' => $id));
        $user = $this->getUser();
        $flashBag = $this->get('session')->getFlashBag();
        if(is_null($user)){
            $flashBag->add('notice', 'Je bent niet ingelogd');
            return $this->redirect($this->generateUrl('app_frontend_inspiration_detail', ['slug' => $project->getSlug()]));
        }
        $flashBag->add('notice', 'Opgeslagen in je account');
        if(!$this->ifInspirationIsSaved($id)){
            $accountSavedOptionsHelper = $this->get('app_user.account_saved_helper');
            $accountSavedOptionsHelper->saveData($user,AccountSavedOptions::TYPE_INSPIRATIE,$id);
        }
        return $this->redirect($this->generateUrl('app_frontend_inspiration_detail', ['slug' => $project->getSlug()]));

    }

    /**
     * @param $id
     * @return bool
     */
    public function ifInspirationIsSaved($id){
        $user = $this->getUser();
        if(is_null($user)){
            return false;
        }
        $inspiration = $this->getDoctrine()
            ->getRepository('AppCoreBundle:AccountSavedOptions')
            ->findOneBy(array('redirect_id' => $id,'user_id' => $user->getid()));
       return (count($inspiration) > 0 ) ? true : false;

    }
}
