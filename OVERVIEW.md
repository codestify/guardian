# Guardian Package Overview

This document provides an overview of the Guardian package architecture and how its components work together.

## Package Structure

The Guardian package is structured according to Laravel package development best practices:

```
packages/guardian/
├── config/                   # Package configuration
├── database/                 # Database migrations
│   └── migrations/
├── dist/                     # Compiled JavaScript assets
├── resources/                # Frontend resources
│   ├── js/                   # JavaScript source
│   └── views/                # Blade templates
├── src/                      # PHP source code
│   ├── Console/              # Artisan commands
│   ├── Detection/            # Bot detection modules
│   ├── Http/                 # Controllers & middleware
│   │   ├── Controllers/
│   │   └── Middleware/
│   ├── Models/               # Database models
│   └── Prevention/           # Prevention strategies
├── tests/                    # Test suite
└── vendor/                   # Dependencies
```

## Core Components

### 1. Detection System

The detection system consists of multiple analyzers that examine different aspects of user requests:

-   **HeaderAnalyzer**: Examines HTTP headers for signs of automation
-   **RequestPatternAnalyzer**: Identifies suspicious request patterns
-   **RateLimitAnalyzer**: Detects high-frequency requests
-   **BehavioralAnalyzer**: Analyzes user behavior for bot-like patterns

Each analyzer returns a confidence score that contributes to the overall detection score.

### 2. Prevention System

Once a bot is detected, Guardian can respond in different ways:

-   **Block**: Returns an error status (403)
-   **Challenge**: Shows a CAPTCHA or other verification
-   **Monitor**: Allows the request but logs the activity
-   **Throttle**: Slows down the request processing

The `PreventionEngine` class coordinates these responses based on the detection scores and configured thresholds.

### 3. Client-Side Detection

The JavaScript module (`guardian.js`) provides browser-based detection that complements the server-side approach:

-   **Environment Checks**: Detects automation frameworks and browser inconsistencies
-   **Behavior Monitoring**: Tracks mouse movements, clicks, and scroll patterns
-   **Canvas/WebGL Fingerprinting**: Creates browser fingerprints to detect bots
-   **Signal Reporting**: Reports detection signals back to the server

### 4. Dashboard

The dashboard provides real-time insights into bot traffic:

-   **Statistics**: Overview of total visitors, detected bots, and blocked requests
-   **Charts**: Visual representation of traffic patterns over time
-   **Logs Table**: Detailed logs of detected bots and their characteristics
-   **Export**: Ability to export detection data for further analysis

## Request Flow

1. A request arrives at the Laravel application
2. The `GuardianMiddleware` intercepts the request
3. The middleware calls `Guardian::analyze()` to evaluate the request
4. If a bot is detected, the configured response strategy is applied
5. The request is logged to the database for reporting
6. The dashboard displays the detection data

## Installation Process

When a user runs `php artisan guardian:install`:

1. Configuration files are published to `config/guardian.php`
2. Migrations are published to create the necessary database tables
3. JavaScript assets are compiled and published to `public/vendor/guardian/`
4. Necessary directories are created for logs and caching

## JavaScript Integration

Guardian's JavaScript is automatically injected into responses (if configured) using a Blade directive:

```blade
@guardian
```

This adds the script with proper configuration based on the package settings.

## Customization Points

The package is designed to be highly customizable:

1. **Configuration**: Extensive configuration options in `guardian.php`
2. **Response Handler**: Custom response handling via `Guardian::setResponseHandler()`
3. **Event Listeners**: Events dispatched for bot detection and challenge completion
4. **Middleware**: Can be applied selectively to specific routes
5. **Blade Directive**: For manual inclusion of scripts where needed

## Extension Points

Developers can extend Guardian through:

1. **Custom Analyzers**: By implementing the `Analyzer` interface
2. **Custom Prevention Strategies**: By extending the `PreventionStrategy` class
3. **Custom Signals**: By adding detection signals via JavaScript
4. **Middleware Customization**: By extending or replacing the middleware

## Dashboard Authentication

The dashboard is protected by a Gate definition that can be customized. By default, it allows:

1. Users with 'admin' or 'super-admin' roles (if using role-based permissions)
2. Users with an 'is_admin' or 'admin' flag
3. The user with ID 1 (typically the first admin)

This can be overridden in a service provider by redefining the gate.
