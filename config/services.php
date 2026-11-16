<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'ai' => [
        'engine_url' => env('AI_ENGINE_URL', env('TREND_ENGINE_URL', 'http://127.0.0.1:8080')),
        'internal_token' => env('AI_ENGINE_INTERNAL_TOKEN', env('TREND_ENGINE_INTERNAL_TOKEN')),
        'default_provider' => env('AI_PROVIDER', 'internal'),
        'providers' => [
            'internal' => [
                'enabled' => true,
                'label' => 'SocialPilot AI',
                'model' => env('AI_MODEL', 'socialpilot-lite'),
            ],
            'openai' => [
                'enabled' => filled(env('OPENAI_API_KEY')),
                'api_key' => env('OPENAI_API_KEY'),
                'label' => 'OpenAI',
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            ],
            'gemini' => [
                'enabled' => filled(env('GEMINI_API_KEY')),
                'api_key' => env('GEMINI_API_KEY'),
                'label' => 'Google Gemini',
                'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
            ],
            'openrouter' => [
                'enabled' => filled(env('OPENROUTER_API_KEY')),
                'api_key' => env('OPENROUTER_API_KEY'),
                'label' => 'OpenRouter',
                'model' => env('OPENROUTER_MODEL', 'liquid/lfm-3-1.7b:free'),
                'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            ],
            'groq' => [
                'enabled' => filled(env('GROQ_API_KEY')),
                'api_key' => env('GROQ_API_KEY'),
                'label' => 'Groq',
                'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
                'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            ],
            'ollama' => [
                'enabled' => filled(env('OLLAMA_BASE_URL')) || file_exists('/var/run/docker.sock'),
                'label' => 'Ollama',
                'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
                'model' => env('OLLAMA_MODEL', 'llama3.2:3b'),
            ],
        ],
    ],

    'trends' => [
        'engine_url' => env('TREND_ENGINE_URL'),
        'internal_token' => env('TREND_ENGINE_INTERNAL_TOKEN'),
        'rss_feeds' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TREND_RSS_FEEDS', ''))
        ))),
        'news_api_key' => env('NEWSAPI_API_KEY', env('NEWS_API_KEY')),
        'reddit_client_id' => env('REDDIT_CLIENT_ID'),
        'reddit_client_secret' => env('REDDIT_CLIENT_SECRET'),
        'youtube_api_key' => env('YOUTUBE_API_KEY', env('YOUTUBE_DATA_API_KEY')),
        'google_trends_api_key' => env('GOOGLE_TRENDS_API_KEY'),
    ],

    'social' => [
        'linkedin' => [
            'client_id' => env('LINKEDIN_CLIENT_ID'),
            'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
            'redirect' => env('LINKEDIN_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost'), '/').'/social/oauth/linkedin/callback'),
            'scopes' => ['openid', 'profile', 'email', 'w_member_social'],
        ],
        'x' => [
            'client_id' => env('X_CLIENT_ID'),
            'client_secret' => env('X_CLIENT_SECRET'),
            'redirect' => env('X_REDIRECT_URI'),
        ],
        'facebook' => [
            'client_id' => env('META_APP_ID', env('FACEBOOK_CLIENT_ID')),
            'client_secret' => env('META_APP_SECRET', env('FACEBOOK_CLIENT_SECRET')),
            'redirect' => env('META_REDIRECT_URI', env('FACEBOOK_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost'), '/').'/social/oauth/facebook/callback')),
            'graph_version' => env('META_GRAPH_API_VERSION', env('FACEBOOK_GRAPH_VERSION', 'v22.0')),
            'scopes' => ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts', 'publish_video'],
        ],
        'instagram' => [
            'client_id' => env('META_APP_ID', env('INSTAGRAM_CLIENT_ID')),
            'client_secret' => env('META_APP_SECRET', env('INSTAGRAM_CLIENT_SECRET')),
            'redirect' => env('META_REDIRECT_URI', env('INSTAGRAM_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost'), '/').'/social/oauth/instagram/callback')),
            'graph_version' => env('META_GRAPH_API_VERSION', env('INSTAGRAM_GRAPH_VERSION', 'v22.0')),
            'scopes' => ['pages_show_list', 'instagram_basic', 'instagram_content_publish'],
        ],
        'tiktok' => [
            'client_id' => env('TIKTOK_CLIENT_KEY'),
            'client_secret' => env('TIKTOK_CLIENT_SECRET'),
            'redirect' => env('TIKTOK_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost'), '/').'/social/oauth/tiktok/callback'),
            'scopes' => ['user.info.basic', 'video.upload', 'video.publish'],
        ],
        'youtube' => [
            'client_id' => env('GOOGLE_CLIENT_ID', env('YOUTUBE_CLIENT_ID')),
            'client_secret' => env('GOOGLE_CLIENT_SECRET', env('YOUTUBE_CLIENT_SECRET')),
            'redirect' => env('GOOGLE_REDIRECT_URI', env('YOUTUBE_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost'), '/').'/social/oauth/youtube/callback')),
            'api_key' => env('YOUTUBE_API_KEY', env('YOUTUBE_DATA_API_KEY')),
            'scopes' => ['https://www.googleapis.com/auth/youtube.upload', 'https://www.googleapis.com/auth/youtube.readonly'],
        ],
    ],

    'socialpilot' => [
        'terms_version' => env('TERMS_VERSION', '1.0'),
        'contact_email' => env('CONTACT_EMAIL'),
    ],

    'media' => [
        'storage_disk' => env('MEDIA_STORAGE_DISK', 'local'),
        'max_upload_size_mb' => env('MEDIA_MAX_UPLOAD_MB', 20),
        'image_provider' => env('IMAGE_GENERATION_PROVIDER', null),
        'video_provider' => env('VIDEO_GENERATION_PROVIDER', null),
        'generation_timeout' => env('MEDIA_GENERATION_TIMEOUT', 120),
    ],

];
