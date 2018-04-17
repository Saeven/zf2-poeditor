<?php

namespace CirclicalTranslationEditor;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;

class Module
{
    /**
     * {@inheritDoc}
     */
    public function getAutoloaderConfig()
    {
        return [
            'Zend\Loader\StandardAutoloader' => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/../../src/' . __NAMESPACE__,
                ],
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig()
    {
        return include __DIR__ . '/../../config/module.config.php';
    }


    public function onBootstrap($e)
    {
        $e->getApplication()->getEventManager()->getSharedManager()->attach(AbstractActionController::class, MvcEvent::EVENT_DISPATCH, function ($e) {
            $controller = $e->getTarget();
            $controllerClass = get_class($controller);
            $moduleNamespace = substr($controllerClass, 0, strpos($controllerClass, '\\'));

            if ($moduleNamespace === 'CirclicalTranslationEditor')
                $controller->layout('circlical-translation-editor/layout/layout');
        }, 100);
    }
}