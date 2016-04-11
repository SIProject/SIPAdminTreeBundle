<?php
/*
 * (c) Alexandr Chichin <alex.chichin@gmail.com>
 */
namespace SIP\AdminTreeBundle\Entity;

abstract class TreeNode{

    protected $parent;

    protected $children;

    protected $lft;

    protected $rgt;
}