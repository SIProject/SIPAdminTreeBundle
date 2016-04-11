<?php
/*
 * (c) Alexandr Chichin <alex.chichin@gmail.com>
 */
namespace SIP\AdminTreeBundle\Controller;

use Sonata\AdminBundle\Controller\CRUDController as Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;

class BaseAdminController extends Controller
{

    /**
     * Contextualize the admin class depends on the current request
     *
     * @throws \RuntimeException
     */
    protected function configure()
    {
        parent::configure();
        $this->admin->setTemplate('list', 'SIPAdminTreeBundle:Admin:list.html.twig');
        $this->admin->setTemplate('tree', 'SIPAdminTreeBundle:Admin:tree.html.twig');
        $this->admin->setTemplate('createby', 'SIPAdminTreeBundle:Admin:createby.html.twig');
        $reflectionClass = new \ReflectionClass($this->admin->getClass());
        if(!$reflectionClass->isSubclassOf('SIP\AdminTreeBundle\Entity\TreeNode')){
            throw new \Exception('Class ' . $this->admin->getClass() . ' is not subclass of SIP\AdminTreeBundle\Entity\TreeNode');
        }
    }

    /**
     * return the Response object associated to the tree action
     *
     * @return Response
     */
    public function treeAction(){
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository($this->admin->getClass());
        $roots = $repo->findByParent(null);
        $response = $this->render($this->admin->getTemplate('tree'), array(
            'action'     => 'list',
            'roots'      => $roots
        ));
        $response->headers->setCookie(new Cookie('listAction', 'tree'));
        return $response;
    }

    public function listAction(){
        $response = parent::listAction();
        $response->headers->setCookie(new Cookie('listAction', 'list'));
        return $response;
    }

    /**
     * Redirect the user depend on this choice
     *
     * @param object $object
     *
     * @return RedirectResponse
     */
    protected function redirectTo($object)
    {
        $url = false;

        if (null !== $this->get('request')->get('btn_update_and_list')) {
            if($this->get('request')->cookies->get('listAction') == 'tree'){
                $url = $this->admin->generateUrl('tree');
            }else{
                $url = $this->admin->generateUrl('list');
            }
        }
        if (null !== $this->get('request')->get('btn_create_and_list')) {
            if($this->get('request')->cookies->get('listAction') == 'tree'){
                $url = $this->admin->generateUrl('tree');
            }else{
                $url = $this->admin->generateUrl('list');
            }
        }

        if (null !== $this->get('request')->get('btn_create_and_create')) {
            $params = array();
            if ($this->admin->hasActiveSubClass()) {
                $params['subclass'] = $this->get('request')->get('subclass');
            }
            $url = $this->admin->generateUrl('create', $params);
        }

        if ($this->getRestMethod() == 'DELETE') {
            if($this->get('request')->cookies->get('listAction') == 'tree'){
                $url = $this->admin->generateUrl('tree');
            }else{
                $url = $this->admin->generateUrl('list');
            }
        }

        if (!$url) {
            $url = $this->admin->generateObjectUrl('edit', $object);
        }

        return new RedirectResponse($url);
    }

    /**
     * return json nodes
     *
     * @return Response
     */
    public function nodesAction($id = null){

        if (false === $this->admin->isGranted('LIST')) {
            throw new AccessDeniedException();
        }

        $options = array();
        $data = array();

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository($this->admin->getClass());
        $qb = $repo->createQueryBuilder('n');

        if($id){
            $qb->where('p.id = :parentId')->setParameter('parentId', $id);
        }else{
            $qb->where('p.id IS NULL');
        }

        $data = $qb->select('n', 'p', 'c')
                   ->leftJoin('n.parent', 'p')
                   ->leftJoin('n.children', 'c')
                   ->groupBy('n.id')
                   ->orderBy('n.lft')
                   ->getQuery()
                   ->getArrayResult();

        foreach($data as &$item){
            $item['editUrl'] = $this->admin->generateUrl('edit', array('id' => $item['id']));
            $item['addChildUrl'] = $this->admin->generateUrl('createchild', array('id' => $item['id']));
            $item['deleteUrl'] = $this->admin->generateUrl('delete', array('id' => $item['id']));
            $item['moveUrl'] = $this->admin->generateUrl('movePageNodeEmpty', array('id' => $item['id']));
        }

        return new Response(
            json_encode(
                array(
                    'data'   => $data,
                    'status' => 200,
                    'error'  => false
                )
            ),
            200,
            array('Content-type' => 'application/json')
        );
    }

    /**
     * Creates child item of tree node
     *
     * @param int $id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createChildAction($id = null)
    {
        return $this->createObjectByExists($id);
    }

    /**
     * @param int $id
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws AccessDeniedException If access is not granted
     */
    public function createObjectByExists($id)
    {
        if (false === $this->admin->isGranted('CREATE')) {
            throw new AccessDeniedException();
        }

        $exists_object = $this->admin->getObject($id);

        if (!$exists_object) {
            throw new NotFoundHttpException(sprintf('unable to find the object with id : %s', $id));
        }

        $object = $this->getObjectByExist($exists_object);

        $this->admin->setSubject($object);

        $form = $this->admin->getForm();
        $form->setData($object);

        if ($this->get('request')->getMethod() == 'POST') {
            $form->bind($this->get('request'));

            $isFormValid = $form->isValid();

            if ($isFormValid && (!$this->isInPreviewMode() || $this->isPreviewApproved())) {
                $this->admin->create($object);

                $this->get('session')->getFlashBag()->add('sonata_flash_success','flash_create_success');
                return $this->redirectTo($object);
            }

            if (!$isFormValid) {
                if (!$this->isXmlHttpRequest()) {
                    $this->get('session')->getFlashBag()->add('sonata_flash_error', 'flash_create_error');
                }
            }
        }

        $view = $form->createView();

        $this->get('twig')->getExtension('form')->renderer->setTheme($view, $this->admin->getFormTheme());

        return $this->render($this->admin->getTemplate('createby'), array(
            'action' => 'create',
            'id'     => $id,
            'form'   => $view,
            'object' => $object,
        ));
    }

    /**
     * @param mixed $existObj
     * @return object a new object instance
     */
    protected function getObjectByExist($existObj)
    {
        $object = $this->admin->getNewInstance();
        $object->setParent($existObj);
        return $object;
    }

    /**
     * return the Response object associated to the create action
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function movePageNodeAction($id, $targetId, $dropPosition)
    {
        if (false === $this->admin->isGranted('EDIT')) {
            throw new AccessDeniedException();
        }

        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        /** @var \SIP\CmsBundle\Entity\PageRepository $repo */
        $repo = $em->getRepository($this->admin->getClass());
        $sourceNode = $repo->find($id);
        $targetNode = $repo->find($targetId);

        try {
            switch ($dropPosition) {
                case 'before':
                    $prevSiblingNodes = $repo->getPrevSiblings($targetNode);
                    if (false == $prevSiblingNodes) {
                        $repo->persistAsFirstChildOf($sourceNode, $targetNode->getParent());
                    } else {
                        $prevSiblingNode = array_pop($prevSiblingNodes);
                        $repo->persistAsNextSiblingOf($sourceNode, $prevSiblingNode);
                    }
                    break;

                case 'after':
                    $repo->persistAsNextSiblingOf($sourceNode, $targetNode);
                    break;

                case 'append':
                    $repo->persistAsLastChildOf($sourceNode, $targetNode);
                    break;
            }
            $em->flush();

        } catch ( \Exception $e ) {
            return new Response(
                json_encode(
                    array(
                        'data'   => false,
                        'status' => 500,
                        'error'  => $e->getMessage()
                    )
                ),
                200,
                array('Content-type' => 'application/json')
            );
        }
        $data = array(
            'nodeId' => $id,
            'targetNodeId' => $targetId,
            'dropPosition' => $dropPosition
        );

        return new Response(
            json_encode(
                array(
                    'data'   => $data,
                    'status' => 200,
                    'error'  => false
                )
            ),
            200,
            array('Content-type' => 'application/json')
        );
    }

}