<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most templating systems load templates from disk. Here you may specify
    | an array of paths that should be checked for your views. Of course
    | the usual Laravel view path has already been registered for you.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | This option determines where all the compiled Blade templates will be
    | stored for your application. Typically, this is within the storage
    | directory. However, as usual, you are free to change this value.
    |
    | This file is published purely to drop the `realpath()` the framework
    | default wraps this path in. `realpath()` returns **false** for a path that
    | does not exist yet, and a falsy compiled path makes every Blade-touching
    | process die at boot with "Please provide a valid cache path."
    | (Illuminate\View\Compilers\Compiler::__construct).
    |
    | That is not hypothetical here: mounting a persistent volume at
    | `storage/` (Railway, Fly, any container host) shadows the image's storage
    | tree with an empty directory, so `storage/framework/views` is genuinely
    | absent the first time the app boots after the mount — taking down
    | php-fpm, `queue:work` and `schedule:work` together, with an error that
    | names a cache path and gives no hint that a volume is involved.
    |
    | Handing Blade the plain path instead lets it heal itself: the compiler
    | calls `ensureCompiledDirectoryExists()` and creates the directory on
    | first use (Compiler::ensureCompiledDirectoryExists). docker/entrypoint.sh
    | still pre-creates it, but this no longer depends on the entrypoint having
    | run *after* the volume was mounted.
    |
    */

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        storage_path('framework/views')
    ),

];
