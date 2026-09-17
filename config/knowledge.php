<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Fuentes de conocimiento por club
    |--------------------------------------------------------------------------
    |
    | Cada club trae sus documentos de un sitio distinto. `pentaho` es el driver
    | de transición (MDS de Softok2); `bi_knowledge` lee el manifiesto que
    | publica `bi:knowledge` en el panel del club. Cuando ccm migre, basta con
    | cambiar aquí su driver.
    |
    */

    'sources' => [
        'ccm' => [
            'driver' => 'pentaho',
            'base_url' => env('SOFTOK2MDS_BASE_URL', 'https://softok2mds.example.com/api/'),
            'username' => env('SOFTOK2MDS_USERNAME'),
            'password' => env('SOFTOK2MDS_PASSWORD'),
            'files' => explode(',', env('SOFTOK2MDS_FILES', 'golf-output')),
        ],
        'vallealto' => [
            'driver' => 'bi_knowledge',
            'base_url' => env('VALLEALTO_KNOWLEDGE_URL'),
            'username' => env('VALLEALTO_KNOWLEDGE_USERNAME'),
            'password' => env('VALLEALTO_KNOWLEDGE_PASSWORD'),
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
