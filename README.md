# Guardian - AI & Bot Detection for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/codemystify/guardian.svg?style=flat-square)](https://packagist.org/packages/codemystify/guardian)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/codemystify/guardian/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/codemystify/guardian/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/codemystify/guardian.svg?style=flat-square)](https://packagist.org/packages/codemystify/guardian)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](https://opensource.org/licenses/MIT)

**Guardian** is a powerful bot and AI crawler detection system for Laravel applications that's incredibly easy to set up. It works right out of the box with sensible defaults, requiring just two simple commands to install.

Guardian protects your content from being scraped by AI crawlers, detects malicious bots, and identifies automated requests - all while providing a smooth experience for genuine users. Install it in minutes and get immediate protection for your application.

## 🌟 Features

-   **Zero Configuration Required**: Works out of the box with sensible defaults
-   **Quick Installation**: Just two commands to get up and running
-   **Dual Detection Layers**: Server-side PHP detection combined with client-side JavaScript monitoring
-   **AI Pattern Recognition**: Advanced behavioral pattern analysis to detect sophisticated bots
-   **Built-in Dashboard**: Monitor bot activity at `/guardian`
-   **Simple Integration**: Automatically protects web routes with minimal setup
-   **Low Footprint**: Optimized for minimal performance impact on genuine users

## 🔍 The Problem Guardian Solves

Modern websites face increasing challenges from:

-   **AI Content Scrapers**: Bots that harvest content for AI training without consent
-   **Automated Crawlers**: That increase server load and costs
-   **Form Spam Bots**: That submit fake registrations and spam
-   **Content Theft**: Competitors scraping your valuable content
-   **Fake Traffic**: Bots that skew your analytics with non-human visitors

Guardian provides an easy solution without affecting real users.

## 📦 Installation

You can install the package via Composer:

```bash
composer require codemystify/guardian
```

After installation, publish the configuration file and migrations:

```bash
php artisan guardian:install
```

Run the migrations:

```bash
php artisan migrate
```

That's it! Guardian is now fully installed and ready to protect your application.

## ⚙️ Configuration

The package works out of the box with sensible defaults, but you can customize it by editing the configuration file at `config/guardian.php`:

```php
return [
    // Enable or disable Guardian completely
    'enabled' => true,

    // Dashboard settings
    'dashboard' => [
        'enabled' => true,
        'path' => '/guardian',           // Dashboard is accessible at this path
        'middleware' => ['web', 'auth'], // Protected by authentication
    ],

    // Response options for detected bots
    'response' => [
        'type' => 'challenge',  // Options: 'block', 'challenge', 'monitor', 'throttle'
    ],

    // Whitelist to exclude specific IPs, user agents or paths from detection
    'whitelist' => [
        'ips' => ['127.0.0.1'],
        'user_agents' => ['GoogleBot', 'BingBot', 'YandexBot'],
        'paths' => ['/healthcheck', '/ping', '/api/webhook'],
    ],
];
```

> **Note:** The JavaScript reporting endpoint is fixed at `/__guardian__/report` and cannot be changed.

## 📚 Usage

### Quick Start

After installation, Guardian will automatically monitor all web routes in your application for bot activity.

### Global Protection

For most applications, Guardian already works out of the box without further configuration.

### Route-Specific Protection

You can add the `guardian` middleware to specific routes or route groups:

```php
// Protect single routes
Route::get('/protected-content', function () {
    return view('protected-content');
})->middleware('guardian');

// Protect route groups
Route::middleware(['guardian'])->group(function () {
    Route::get('/member-area', 'MemberController@index');
    Route::get('/premium-content', 'ContentController@premium');
});
```

### PHP API

You can use the Guardian facade in your code:

```php
use Shah\Guardian\Facades\Guardian;

// Check if current request is a bot
if (Guardian::isBot()) {
    // Handle bot detection
}

// Get detailed detection information
$botInfo = Guardian::analyze();
$score = $botInfo->score;
$signals = $botInfo->signals;
```

## 📊 Dashboard

Guardian includes a built-in dashboard that provides insights into bot traffic and detection events.

Access the dashboard at `/guardian` after installation.

The dashboard shows:

-   Recent bot detection events
-   Statistics about detected bots and crawlers
-   Signal types that triggered detections
-   Traffic patterns and trends

> **Note:** The dashboard is protected by authentication. Only logged-in users can access it. You can customize the path and middleware in the configuration file.

## 🌎 JavaScript Module

Guardian includes a JavaScript module that runs in the browser to detect bot-like behavior.

### Automatic Integration

The JavaScript is automatically included when Guardian middleware is active - you don't need to do anything!

### Manual Integration

If you want to manually include the script:

```html
<script src="{{ asset('vendor/guardian/guardian.js') }}"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const guardian = new Guardian();
        guardian.init();
    });
</script>
```

### Advanced Usage

You can customize the Guardian instance with configuration options:

```javascript
// Create a new instance with custom configuration
const guardian = new Guardian({
    sampleRate: 0.5, // Percentage of traffic to analyze (0-1)
    threshold: 15, // Detection threshold score
    debug: true, // Enable debug logging
});

// Initialize detection
guardian.init();

// Add a custom signal manually
guardian.addCustomSignal("my_custom_signal", 5, "My custom detection logic");

// Get detection results
const results = guardian.getResults();
console.log(results);
```

## 🛠️ Development

```bash
# Clone and install
git clone https://github.com/codemystify/guardian.git
cd guardian
composer install
npm install

# Run tests
composer test
npm test

# Build assets
npm run build
```

## 🤝 Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## 🔒 Security

If you discover any security related issues, please email security@codemystify.com instead of using the issue tracker.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 🙏 Credits

-   [Muhammad Ali Shah](https://github.com/codemystify)
-   [All Contributors](../../contributors)

Guardian relies on several open-source packages:

-   [Crawler-Detect](https://github.com/JayBizzle/Crawler-Detect)
-   [Device Detector](https://github.com/matomo-org/device-detector)
-   [IP-Lib](https://github.com/mlocati/ip-lib)
