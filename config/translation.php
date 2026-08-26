<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Exam Question Translation
    |--------------------------------------------------------------------------
    |
    | Config for the Question/Option translation feature (see
    | App\Services\Translation\TranslationProviderInterface). Business logic
    | never talks to a provider driver directly - it resolves one instance
    | of TranslationProviderInterface, bound in AppServiceProvider from the
    | 'driver' selected here, so swapping providers later never touches
    | calling code.
    |
    */

    'driver' => env('TRANSLATION_PROVIDER', 'libretranslate'),

    /*
    | The single global source language every Question/Option is authored
    | in. No per-Question or per-Course "authoring language" field exists
    | in this schema (confirmed against the questions/courses migrations) -
    | all Question content is written in one language app-wide, matching
    | APP_LOCALE. A per-Question source language would only be worth adding
    | if that assumption is ever confirmed false for a real course.
    */
    'source_language' => env('TRANSLATION_SOURCE_LANGUAGE', 'en'),

    'providers' => [
        'libretranslate' => [
            'base_url' => env('LIBRETRANSLATE_BASE_URL', 'https://libretranslate.com'),
            'api_key' => env('LIBRETRANSLATE_API_KEY'),
            'timeout' => (int) env('LIBRETRANSLATE_TIMEOUT', 10),
        ],
    ],

];
