<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // LightOnOCR-3 を動かす OpenAI 互換サーバー（Ollama / vLLM）
    'ocr' => [
        'base_url' => env('OCR_BASE_URL', 'http://localhost:8000/v1'),
        'api_key' => env('OCR_API_KEY', 'dummy'), // vLLM は --api-key 未指定なら何でもOK
        'model' => env('OCR_MODEL', 'lightonai/LightOnOCR-3-0.8B'),
    ],

    // OCR テキスト → 食品情報の変換。OCR を vLLM に向けても変換は Ollama で行えるよう接続先は別に持つ
    'food' => [
        'base_url' => env('FOOD_BASE_URL', 'http://localhost:11434/v1'),
        'api_key' => env('FOOD_API_KEY', 'dummy'),
        'model' => env('FOOD_MODEL', 'gemma4:e4b'),
    ],

];
