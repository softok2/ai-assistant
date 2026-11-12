<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Fuentes de conocimiento por club
    |--------------------------------------------------------------------------
    |
    | Cada club trae sus documentos de un sitio distinto. `bi_knowledge` lee el manifiesto que
    | publica `bi:knowledge` en el panel del club.
    |
    */

    'sources' => [
        'ccm' => [
            'driver' => 'bi_knowledge',
            'base_url' => env('CCM_KNOWLEDGE_URL'),
            'username' => env('CCM_KNOWLEDGE_USERNAME'),
            'password' => env('CCM_KNOWLEDGE_PASSWORD'),
        ],
        'vallealto' => [
            'driver' => 'bi_knowledge',
            'base_url' => env('VALLEALTO_KNOWLEDGE_URL'),
            'username' => env('VALLEALTO_KNOWLEDGE_USERNAME'),
            'password' => env('VALLEALTO_KNOWLEDGE_PASSWORD'),
        ],
    ],

    /*
    | Sustituye por club el mapa rol -> grupos de RoleName::sourceGroups().
    */
    'role_groups' => [
        'ccm' => [
            'massage_manager' => ['wellness-masaje-de-caballeros'],
            'aesthetics_manager' => ['wellness-estetica'],
            'podiatry_manager' => ['wellness-podologia'],
        ],
    ],

    // Cadencia de `assistant-files:sync`. La lee el scheduler en
    // bootstrap/app.php y la franja de salud de Fuentes del asistente.
    'sync' => [
        'cron' => '0 */2 * * *',
        'from' => '08:00',
        'to' => '22:00',
        'label' => 'Cada 2 h',
    ],

];
