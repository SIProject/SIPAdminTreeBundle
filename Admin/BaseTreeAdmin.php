<?php
/*
 * (c) Alexandr Chichin <alex.chichin@gmail.com>
 */
namespace SIP\AdminTreeBundle\Admin;

use Sonata\AdminBundle\Route\RouteCollection;

use Sonata\AdminBundle\Admin\Admin as BaseAdmin;

abstract class BaseTreeAdmin extends BaseAdmin
{

    /**
     * @param \Sonata\AdminBundle\Route\RouteCollection $collection
     */
    protected function configureRoutes(RouteCollection $collection)
    {
        parent::configureRoutes($collection);

        $collection->add('nodes', 'nodes',
            array('_controller' => $this->baseControllerName . ':nodes'));
        $collection->add('nodes_by_id', 'nodes/{id}',
            array('_controller' => $this->baseControllerName . ':nodes'));
        $collection->add('tree', 'tree',
            array('_controller' => $this->baseControllerName . ':tree'));
        $collection->add('createchild', '{id}/createchild',
            array('_controller' => $this->baseControllerName . ':createchild'));
        $collection->add('movePageNodeEmpty', 'movePageNode/{id}',
            array('_controller' => $this->baseControllerName . ':movePageNode'));
        $collection->add('movePageNode', 'movePageNode/{id}/{targetId}/{dropPosition}',
            array('_controller' => $this->baseControllerName . ':movePageNode'));
    }
}