<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Business types
    |--------------------------------------------------------------------------
    */
    'business_types' => ['manufacturing', 'service', 'trading'],

    /*
    |--------------------------------------------------------------------------
    | Module catalog
    |--------------------------------------------------------------------------
    | Each module maps to the business types that enable it. '*' = always on
    | (core modules present for every company regardless of business type).
    | This drives the backend module gate and the frontend sidebar manifest.
    */
    'modules' => [
        // Core (all companies)
        'dashboard' => ['*'],
        'organization' => ['*'],
        'users' => ['*'],
        'master_data' => ['*'],
        'purchase' => ['*'],
        'sales' => ['*'],
        'accounting' => ['*'],
        'vat' => ['*'],
        'vds' => ['*'],
        'assets' => ['*'],
        'reports' => ['*'],
        'documents' => ['*'],
        'backup' => ['*'],
        'approvals' => ['*'],
        'settings' => ['*'],

        // Business-type specific
        'inventory' => ['manufacturing', 'trading'],
        'production' => ['manufacturing'],
        'job_work' => ['manufacturing'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Industry templates
    |--------------------------------------------------------------------------
    | Presets applied by the Company Setup Wizard. `business_types` seeds the
    | company's types (first entry is primary unless overridden); `enable` /
    | `disable` are explicit module overrides on top of the business-type defaults.
    */
    'industry_templates' => [
        'plastic_manufacturing' => [
            'label' => 'Plastic Manufacturing',
            'business_types' => ['manufacturing'],
            'enable' => [],
            'disable' => [],
        ],
        'general_trading' => [
            'label' => 'General Trading / Distribution',
            'business_types' => ['trading'],
            'enable' => [],
            'disable' => [],
        ],
        'professional_services' => [
            'label' => 'Professional / IT Services',
            'business_types' => ['service'],
            'enable' => [],
            'disable' => [],
        ],
        'manufacturing_trading' => [
            'label' => 'Manufacturing + Trading',
            'business_types' => ['manufacturing', 'trading'],
            'enable' => [],
            'disable' => [],
        ],
    ],
];
