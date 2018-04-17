<?php

namespace CirclicalTranslationEditor;

use CirclicalTranslationEditor\Controller\IndexController;


return [

    'router' => [

        'routes' => [

            'circlical-translator-gui' => [
                'type' => 'Literal',
                'options' => [
                    'route' => '/translate',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action' => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'controller-route' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/[:action]',
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                        ],
                    ],
                ],
            ],

            'circlical-translator-save' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/translate/save/[:locale]',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action' => 'save',
                    ],
                    'constraints' => [
                        'locale' => '[a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'circlical-translator-save-config' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/translate/save-config',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action' => 'save-config',
                    ],
                ],
            ],

            'circlical-translator-save-language-config' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/translate/save-language-config/[:locale]',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action' => 'save-language-config',
                    ],
                    'constraints' => [
                        'locale' => '[a-zA-Z0-9_-]*',
                    ],
                ],
            ],

        ],
    ],

    'form_elements' => [
        'factories' => [
        ],
    ],

    'view_helpers' => [
        'invokables' => [
        ],
    ],

    'validators' => [
        'factories' => [
        ],
    ],

    'controllers' => [
        'invokables' => [
            IndexController::class => IndexController::class,
        ],
    ],

    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
];