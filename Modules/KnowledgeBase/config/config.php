<?php

return [
    'name' => 'KnowledgeBase',

    /*
    |--------------------------------------------------------------------------
    | Document upload limits
    |--------------------------------------------------------------------------
    */
    'documents' => [
        'max_file_size' => env('KNOWLEDGEBASE_MAX_FILE_SIZE_KB', 10240), // 10 MB in KB
        'allowed_mimes' => ['application/pdf'],
    ],
];
