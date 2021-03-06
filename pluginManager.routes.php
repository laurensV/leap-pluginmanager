<?php
return [
    'admin/plugins*'                               => [
        'controller'    => 'pluginController',
        'include_slash' => 'true',
    ],
    'admin/plugins'                                => [
        'action'    => 'getPlugins',
        'page'      => 'pages/plugins.php',
        'scripts' => ['scripts/searchable_table.js'],
    ],
    'admin/plugins/enable'                         => [
        'action' => 'enablePlugin',
        'page'   => 'pages/confirm.php',
    ],
    'admin/plugins/disable'                        => [
        'action' => 'disablePlugin',
        'page'   => 'pages/confirm.php',
    ],
    'admin/plugins/mdisable,admin/plugins/menable' => [
        'action' => 'multiplePlugins',
        'page'   => 'pages/confirm.php',
    ]
];
