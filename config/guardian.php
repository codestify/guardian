<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Guardian Protection Settings
    |--------------------------------------------------------------------------
    |
    | Configure the main settings for Guardian.
    */
    'enabled' => env('GUARDIAN_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Detection Settings
    |--------------------------------------------------------------------------
    |
    | Configure how Guardian detects AI crawlers.
    | Server-side and client-side detection options available.
    | Adjust the detection threshold and provide known AI crawler signatures.
    */
    'detection' => [
        'server_enabled' => env('GUARDIAN_SERVER_DETECTION', true),
        'client_enabled' => env('GUARDIAN_CLIENT_DETECTION', true),
        'threshold' => env('GUARDIAN_THRESHOLD', 60), // Lowered from 60 to ensure detection works well
        'use_cache' => true,
        'cache_duration' => 3600, // 1 hour
        'detailed_fingerprinting' => true,

        /*
        |--------------------------------------------------------------------------
        | Analyzer settings
        |--------------------------------------------------------------------------
        */
        'analyzers' => [
            'header' => true,
            'pattern' => true,
            'rate_limit' => true,
            'behavioral' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Known AI crawler signatures - these are definite matches
        |--------------------------------------------------------------------------
        */
        'ai_crawler_signatures' => [
            'AdsBot-Google',
            'Amazonbot',
            'Anthropic-AI',
            'Anthropic',
            'anthropic',
            'Applebot',
            'Bytespider',
            'CCBot',
            'ChatGPT-User',
            'ChatGPT',
            'Claude-Web',
            'Claude',
            'claude',
            'ClaudeBot',
            'cohere-ai',
            'cohere',
            'Cohere',
            'cohere-training-data-crawler',
            'Crawlspace',
            'Diffbot',
            'DuckAssistBot',
            'FacebookBot',
            'FriendlyCrawler',
            'Google-Extended',
            'GoogleOther',
            'GoogleOther-Image',
            'GoogleOther-Video',
            'GPTBot',
            'gptbot',
            'iaskspider/2.0',
            'ICC-Crawler',
            'ImagesiftBot',
            'img2dataset',
            'ISSCyberRiskCrawler',
            'Kangaroo Bot',
            'Meta-ExternalAgent',
            'Meta-ExternalFetcher',
            'OAI-SearchBot',
            'omgili',
            'omgilibot',
            'PanguBot',
            'Perplexity-User',
            'PerplexityBot',
            'Perplexity',
            'perplexity',
            'PetalBot',
            'Scrapy',
            'SemrushBot-OCOB',
            'SemrushBot-SWA',
            'Sidetrade indexer bot',
        ],

        /*
        |--------------------------------------------------------------------------
        | Known AI crawlers from CrawlerDetect
        |--------------------------------------------------------------------------
        */
        'ai_crawlers' => [
            'GPTBot',
            'gptbot',
            'CCBot',
            'anthropic',
            'Anthropic',
            'Claude',
            'claude',
            'Claude-Web',
            'Cohere',
            'cohere',
            'Perplexity',
            'perplexity',
            'Diffbot',
            'DuckAssistBot',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Prevention Settings
    |--------------------------------------------------------------------------
    |
    | Configure how Guardian responds to detected crawlers.
    | Choose strategies like blocking, serving alternate content, or honeypots.
    */
    'prevention' => [
        'strategy' => env('GUARDIAN_STRATEGY', 'alternate_content'),
        'adaptive' => true,
        'block_status_code' => 403,
        'block_message' => 'Access Denied',
        'delay_seconds' => 2,
        'protect_ajax' => true,  // Set to true
        'protect_content' => true,  // Make sure this is true, but note that it might not be used directly

        /*
        |--------------------------------------------------------------------------
        | Content protection techniques
        |--------------------------------------------------------------------------
        */
        'content_protection' => [
            'text_fragmentation' => true,
            'css_obfuscation' => true,
            'add_honeytokens' => true,
            'css_layers' => false, // Keep false for broader browser compatibility
            'add_meta_tags' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Protection
    |--------------------------------------------------------------------------
    |
    | Configure protection for API routes.
    */
    'api' => [
        'protect_api' => true, // Changed to true to ensure API routes are protected
        'api_prevention_strategy' => 'block', // 'block', 'limit', 'delay'
    ],

    /*
    |--------------------------------------------------------------------------
    | Whitelist and Blacklist
    |--------------------------------------------------------------------------
    |
    | Configure IPs and user agents that should be excluded or included.
    */
    'whitelist' => [
        'ips' => [
            '127.0.0.1',
        ],

        'ip_ranges' => [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ],

        'paths' => [
            '^/api/public',
            '^/admin/login',
            '^/guardian/',
            '^/guardian-test/api/detection', // Add this so the API endpoint works for testing
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Client Detection
    |--------------------------------------------------------------------------
    |
    | Configure client-side detection settings.
    | Define endpoints, sample rate and whether to auto-include scripts.
    */
    'client' => [
        'report_endpoint' => '/__guardian__/report', // Updated to match the default endpoint in code
        'sample_rate' => 1.0, // Increased to ensure client detection runs on all visits
        'auto_include_script' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for Guardian activities.
    */
    'logging' => [
        'enabled' => env('GUARDIAN_LOGGING', true),
        'database' => env('GUARDIAN_DATABASE_LOGGING', true),
        'channel' => env('GUARDIAN_LOG_CHANNEL', 'daily'), // Set a default channel
        'high_confidence' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    | Configure the Guardian dashboard.
    */
    'dashboard' => [
        'enabled' => true,
        'path' => env('GUARDIAN_DASHBOARD_PATH', '/guardian'),
        'middleware' => ['web', 'auth'],  // Default middleware for the dashboard
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | Testing-specific configurations
    */
    'testing' => [
        'analyze_mode' => false,
        'return_protected' => false,
        'force_protect' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Debugging
    |--------------------------------------------------------------------------
    |
    | Debug settings to help troubleshoot issues
    */
    'debug' => [
        'enabled' => env('GUARDIAN_DEBUG', true),
        'log_detection' => true,
        'log_prevention' => true,
        'log_protection' => true,
    ],
];
