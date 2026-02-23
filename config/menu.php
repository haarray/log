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
            'label' => 'Roles & Access',
            'icon' => 'fa-solid fa-user-lock',
            'route' => 'settings.rbac',
            'params' => [],
            'permission' => 'manage settings',
            'active_route' => 'settings.rbac*',
        ],
        [
            'label' => 'Global Search',
            'icon' => 'fa-solid fa-magnifying-glass',
            'route' => 'settings.search.index',
            'params' => [],
            'permission' => 'manage settings',
            'active_route' => 'settings.search.*',
        ],
        [
            'label' => 'Media Manager',
            'icon' => 'fa-solid fa-photo-film',
            'route' => 'settings.media.index',
            'params' => [],
            'permission' => 'view settings',
            'active_route' => 'settings.media.*',
        ],
    ],
];
