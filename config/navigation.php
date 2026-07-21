<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Primary Navigation
    |--------------------------------------------------------------------------
    |
    | Core and custom business modules can add section/view items here. Each
    | item accepts label plus route or url, and may define children. Do not
    | put actions like create, approve, delete, or export in this menu; actions
    | belong as buttons inside the relevant view. The layout renders up to
    | three menu levels: top item, submenu item, and nested submenu item.
    |
    */
    'main' => [
        [
            'label' => 'People',
            'route' => 'employees.index',
            'children' => [
                ['label' => 'Employees', 'route' => 'employees.index', 'can' => 'employees.view'],
                ['label' => 'Org chart', 'route' => 'employees.chart', 'can' => 'employees.view'],
                [
                    'label' => 'Setup',
                    'route' => 'departments.index',
                    'can' => 'employees.view',
                    'children' => [
                        ['label' => 'Departments', 'route' => 'departments.index', 'can' => 'employees.view'],
                        ['label' => 'Department chart', 'route' => 'departments.chart', 'can' => 'employees.view'],
                        ['label' => 'Positions', 'route' => 'positions.index', 'can' => 'employees.view'],
                        ['label' => 'Compensation packages', 'route' => 'compensation-packages.index', 'can' => 'hr.compensation.view'],
                    ],
                ],
                [
                    'label' => 'Time off',
                    'route' => 'pto.index',
                    'can' => 'pto.view',
                    'children' => [
                        ['label' => 'PTO requests', 'route' => 'pto.index', 'can' => 'pto.view'],
                        ['label' => 'PTO policies', 'route' => 'pto-policies.index', 'can' => 'pto.view'],
                    ],
                ],
            ],
        ],
        [
            'label' => 'Operations',
            'route' => 'assets.index',
            'children' => [
                ['label' => 'Assets', 'route' => 'assets.index', 'can' => 'assets.view'],
                [
                    'label' => 'Setup',
                    'route' => 'asset-tags.index',
                    'can' => 'assets.view',
                    'children' => [
                        ['label' => 'Asset tags', 'route' => 'asset-tags.index', 'can' => 'assets.view'],
                    ],
                ],
            ],
        ],
        [
            'label' => 'Knowledge',
            'route' => 'knowledge.index',
            'can' => 'knowledge.view',
            'children' => [
                ['label' => 'Articles', 'route' => 'knowledge.index', 'can' => 'knowledge.view'],
                ['label' => 'Browse categories', 'route' => 'knowledge-categories.index', 'can' => 'knowledge.view'],
                [
                    'label' => 'Setup',
                    'route' => 'knowledge-taxonomy.index',
                    'can' => 'knowledge.manage',
                    'children' => [
                        ['label' => 'Categories & tags', 'route' => 'knowledge-taxonomy.index', 'can' => 'knowledge.manage'],
                    ],
                ],
            ],
        ],
        [
            'label' => 'Platform',
            'route' => 'webhooks.index',
            'children' => [
                [
                    'label' => 'Integrations',
                    'route' => 'webhooks.index',
                    'can' => 'webhooks.manage',
                    'children' => [
                        ['label' => 'Webhooks', 'route' => 'webhooks.index', 'can' => 'webhooks.manage'],
                    ],
                ],
                [
                    'label' => 'Observability',
                    'route' => 'audit.index',
                    'can' => 'audit.view',
                    'children' => [
                        ['label' => 'Audit log', 'route' => 'audit.index', 'can' => 'audit.view'],
                    ],
                ],
                [
                    'label' => 'Access',
                    'route' => 'access.users.index',
                    'can' => 'api.clients.manage',
                    'children' => [
                        ['label' => 'Users & roles', 'route' => 'access.users.index', 'can' => 'api.clients.manage'],
                        ['label' => 'API tokens', 'route' => 'access.tokens.index', 'can' => 'api.clients.manage'],
                    ],
                ],
                [
                    'label' => 'Settings',
                    'route' => 'settings.edit',
                    'can' => 'api.clients.manage',
                    'children' => [
                        ['label' => 'System settings', 'route' => 'settings.edit', 'can' => 'api.clients.manage'],
                    ],
                ],
                [
                    'label' => 'API',
                    'url' => '/docs',
                    'can' => 'api.clients.manage',
                    'children' => [
                        ['label' => 'Docs', 'url' => '/docs', 'can' => 'api.clients.manage'],
                        ['label' => 'OpenAPI JSON', 'url' => '/openapi.json', 'can' => 'api.clients.manage'],
                    ],
                ],
            ],
        ],
    ],
];
