<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Recipe Import
    |--------------------------------------------------------------------------
    |
    | Where RecipeSeeder finds the extracted recipe data: a `recipes.json`
    | file plus the photos it references, under `path` on the given disk.
    | The data isn't committed to the repository, so locally it lives on the
    | private `local` disk; in production point this at a private bucket.
    |
    */

    'import' => [
        'disk' => env('RECIPE_IMPORT_DISK', 'local'),
        'path' => env('RECIPE_IMPORT_PATH', 'recipe-import'),
    ],

    /*
    | Disk that imported photos are copied to and served from.
    */

    'images_disk' => env('RECIPE_IMAGES_DISK', 'public'),

];
