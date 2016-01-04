<?php


return [
    'circlical' => [
        'translation_editor' => [

            /*
             * The path to xgettext on your local machine. Make sure this is installed, or it won't work
             */
            'xgettext' => '/usr/local/bin/xgettext',

            /*
             * The path to msgcat on your local machine. Make sure this is installed, or it won't work
             */
            'msgcat' => '/usr/local/bin/msgcat',

            /*
             * The folder where existing po files are backed up, just in case
             */
            'backup_dir' =>  getcwd() . '/data/cache/translator/backups',

            /*
             * The path where cache and config are stored
             */
            'cache_dir' =>  getcwd() . '/data/cache/translator',


            /*
             * Custom stubs for Twig functions you might have created
             */
            'stub_functions' => [
                'ConfigHelper',
                'formRow',
                'formElement',
                'AssetHelper',
                'productScript',
            ],

            /*
             * Custom stubs for Twig filters you might have created
             */
            'stub_filters' => [
                'localizedcurrency',
            ],
        ],
    ],
];