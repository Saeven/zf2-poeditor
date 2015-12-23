<?php


return [
    'circlical' => [
        'translation_editor' => [
            'database_handle' => 'Zend\Db\Adapter\Adapter',
            'xgettext' => '/usr/local/bin/xgettext',
            'msgcat' => '/usr/local/bin/msgcat',
            'backup_dir' =>  getcwd() . '/data/cache/translator/backups',
            'cache_dir' =>  getcwd() . '/data/cache/translator',
        ],
    ],
];