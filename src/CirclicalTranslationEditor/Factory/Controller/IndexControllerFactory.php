<?php

namespace CirclicalTranslationEditor\Factory\Controller;

use CirclicalTranslationEditor\Controller\IndexController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class IndexControllerFactory implements FactoryInterface
{

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new IndexController($container->get('config'));
    }
}