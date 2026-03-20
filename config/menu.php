<?php

return [
    'settings_nav' => [
        [
            'label' => 'Control Hub',
            'icon' => 'fa-solid fa-palette',
            'route' => 'settings.index',
            'params' => ['tab' => 'settings-app'],
            'permission' => 'view settings',
            'active_route' => 'settings.index',
        ],
        [
            'label' => 'Users',
            'icon' => 'fa-solid fa-users',
            'route' => 'settings.users.index',
            'params' => [],
            'permission' => 'view users',
            'active_route' => 'settings.users.*',
        ],
        [
            'label' => 'Global Search',
            'icon' => 'fa-solid fa-magnifying-glass',
            'route' => 'settings.search.index',
            'params' => [],
            'permission' => 'manage settings',
            'active_route' => 'settings.search.*',
        ],
    ],
];
