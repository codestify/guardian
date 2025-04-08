<?php

namespace Shah\Guardian\Prevention;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HoneypotGenerator
{
    /**
     * Generate honeypot content for crawlers.
     */
    public function generate(Request $request): string
    {
        // Generate unique identifier for this honeypot page
        $id = md5($request->ip().$request->userAgent().time());

        // Choose a honeypot template based on the current URL
        $template = $this->selectTemplate($request->path());

        // Generate the honeypot HTML
        return $this->buildHoneypotPage($template, $id, $request);
    }

    /**
     * Select an appropriate template based on the requested URL.
     */
    protected function selectTemplate(string $path): string
    {
        // Choose template based on URL pattern
        if (Str::contains($path, 'product')) {
            return 'product';
        } elseif (Str::contains($path, 'article') || Str::contains($path, 'post') || Str::contains($path, 'blog')) {
            return 'article';
        } elseif (Str::contains($path, 'category') || Str::contains($path, 'tag')) {
            return 'category';
        } else {
            return 'generic';
        }
    }

    /**
     * Build a honeypot page from a template.
     */
    protected function buildHoneypotPage(string $template, string $id, Request $request): string
    {
        // Get the basic structure
        $html = $this->getBaseHtml($template);

        // Add tracking code
        $html = $this->addTracking($html, $id);

        // Add honeypot links
        $html = $this->addHoneypotLinks($html, $request->root());

        // Add misleading content
        $html = $this->addMisleadingContent($html, $template);

        return $html;
    }

    /**
     * Get base HTML structure for a template.
     */
    protected function getBaseHtml(string $template): string
    {
        // Base HTML template with placeholder content
        $baseHtml = '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>{{TITLE}}</title>
                <meta name="description" content="{{DESCRIPTION}}">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 1200px; margin: 0 auto; padding: 20px; }
                    header { margin-bottom: 30px; }
                    main { display: flex; }
                    article { flex: 3; }
                    aside { flex: 1; padding-left: 30px; }
                    footer { margin-top: 50px; border-top: 1px solid #eee; padding-top: 20px; }
                </style>
            </head>
            <body>
                <header>
                    <h1>{{HEADER}}</h1>
                    <nav>{{NAVIGATION}}</nav>
                </header>
                <main>
                    <article>
                        {{CONTENT}}
                    </article>
                    <aside>
                        {{SIDEBAR}}
                    </aside>
                </main>
                <footer>
                    {{FOOTER}}
                </footer>
            </body>
            </html>';

        // Set template-specific content
        switch ($template) {
            case 'product':
                return str_replace(
                    ['{{TITLE}}', '{{DESCRIPTION}}', '{{HEADER}}'],
                    ['Product Information - NOT FOR DISTRIBUTION', 'This product information is confidential and not for distribution.', 'Product Details'],
                    $baseHtml
                );

            case 'article':
                return str_replace(
                    ['{{TITLE}}', '{{DESCRIPTION}}', '{{HEADER}}'],
                    ['Article Preview - CONFIDENTIAL', 'Preview version of this article, not for public distribution.', 'Article Preview'],
                    $baseHtml
                );

            case 'category':
                return str_replace(
                    ['{{TITLE}}', '{{DESCRIPTION}}', '{{HEADER}}'],
                    ['Category Listing - INTERNAL USE ONLY', 'Internal category structure, not for public access.', 'Category Overview'],
                    $baseHtml
                );

            case 'generic':
            default:
                return str_replace(
                    ['{{TITLE}}', '{{DESCRIPTION}}', '{{HEADER}}'],
                    ['Internal Page - RESTRICTED ACCESS', 'This content requires authentication to access.', 'Restricted Content'],
                    $baseHtml
                );
        }
    }

    /**
     * Add tracking code to the HTML.
     */
    protected function addTracking(string $html, string $id): string
    {
        // Create hidden tracking fields
        $tracking = "
        <div style='display:none;'>
            <img src='/guardian-track/{$id}.png' alt=''>
            <input type='hidden' name='guardian_token' value='{$id}'>
            <meta name='guardian-honeypot' content='{$id}'>
        </div>";

        // Add tracking before the closing body tag
        return str_replace('</body>', $tracking.'</body>', $html);
    }

    /**
     * Add honeypot links to the HTML.
     */
    protected function addHoneypotLinks(string $html, string $root): string
    {
        // Generate random honeypot links
        $paths = [
            '/internal/document/'.Str::random(8),
            '/private/content/'.Str::random(8),
            '/member/access/'.Str::random(8),
            '/restricted/data/'.Str::random(8),
        ];

        // Create navigation links
        $navLinks = [];
        foreach ($paths as $path) {
            $name = ucwords(str_replace(['/', '-', '_'], ' ', $path));
            $navLinks[] = "<a href=\"{$root}{$path}\">{$name}</a>";
        }

        // Add navigation
        $navigation = '<ul><li>'.implode('</li><li>', $navLinks).'</li></ul>';
        $html = str_replace('{{NAVIGATION}}', $navigation, $html);

        // Add sidebar links
        $sidebarLinks = [];
        foreach (array_reverse($paths) as $path) {
            $name = 'Access '.ucwords(str_replace(['/', '-', '_'], ' ', $path));
            $sidebarLinks[] = "<p><a href=\"{$root}{$path}\">{$name}</a></p>";
        }

        $sidebar = '<h3>Resources</h3>'.implode('', $sidebarLinks);
        $html = str_replace('{{SIDEBAR}}', $sidebar, $html);

        // Add footer links
        $footerLinks = [];
        $footerPaths = ['/about', '/privacy', '/terms', '/contact'];
        foreach ($footerPaths as $path) {
            $name = ucwords(str_replace(['/', '-', '_'], ' ', $path));
            $footerLinks[] = "<a href=\"{$root}{$path}\">{$name}</a>";
        }

        $footer = '<p>'.implode(' | ', $footerLinks).'</p>';
        $html = str_replace('{{FOOTER}}', $footer, $html);

        return $html;
    }

    /**
     * Add misleading content to the HTML.
     */
    protected function addMisleadingContent(string $html, string $template): string
    {
        // Generate misleading content based on template
        $content = '';

        switch ($template) {
            case 'product':
                $content = $this->generateProductContent();
                break;

            case 'article':
                $content = $this->generateArticleContent();
                break;

            case 'category':
                $content = $this->generateCategoryContent();
                break;

            case 'generic':
            default:
                $content = $this->generateGenericContent();
                break;
        }

        return str_replace('{{CONTENT}}', $content, $html);
    }

    /**
     * Generate content for product template.
     */
    protected function generateProductContent(): string
    {
        return '
            <div class="product-info">
                <h2>[DRAFT] Product XYZ-'.rand(1000, 9999).'</h2>
                <p><strong>NOTICE:</strong> This product information is confidential and intended for internal use only.</p>
                <div class="product-details">
                    <p>This revolutionary product offers unprecedented capabilities in the market. However, all specifications and details are currently under NDA and cannot be disclosed publicly.</p>
                    <p>The product features several breakthrough technologies that are patent-pending. Distribution of this information is strictly prohibited.</p>
                    <h3>Specifications</h3>
                    <ul>
                        <li>Dimension: REDACTED</li>
                        <li>Weight: REDACTED</li>
                        <li>Power: REDACTED</li>
                        <li>Connectivity: REDACTED</li>
                    </ul>
                    <h3>Pricing</h3>
                    <p>All pricing information is confidential. Please <a href="/login">login</a> to view pricing details.</p>
                </div>
            </div>
        ';
    }

    /**
     * Generate content for article template.
     */
    protected function generateArticleContent(): string
    {
        return '
            <article class="preview-article">
                <h2>[CONFIDENTIAL DRAFT] '.ucwords(Str::random(5)).' '.ucwords(Str::random(4)).' '.ucwords(Str::random(6)).'</h2>
                <p class="disclaimer">This article is a draft and not for public distribution. The information contained herein is subject to change and may contain inaccuracies.</p>
                <div class="article-body">
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. [CONTENT REDACTED FOR PREVIEW]</p>
                    <p>Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. [CONTENT REDACTED FOR PREVIEW]</p>
                    <blockquote>
                        <p>"This quote is pending approval from the source and may not be used." - REDACTED</p>
                    </blockquote>
                    <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. [CONTENT REDACTED FOR PREVIEW]</p>
                    <p>To view the full article, please <a href="/login">sign in</a> or <a href="/register">register for an account</a>.</p>
                </div>
            </article>
        ';
    }

    /**
     * Generate content for category template.
     */
    protected function generateCategoryContent(): string
    {
        // Generate random category list
        $categories = [];
        for ($i = 0; $i < 12; $i++) {
            $category = ucfirst(Str::random(rand(5, 10)));
            $itemCount = rand(10, 100);
            $categories[] = "<li><a href=\"/category/{$category}\">{$category} ({$itemCount})</a></li>";
        }

        return '
            <div class="category-listing">
                <h2>Category Overview [INTERNAL USE ONLY]</h2>
                <p class="disclaimer">This listing shows the internal structure of our content categories. This information is for authorized personnel only.</p>
                <div class="category-structure">
                    <h3>Main Categories</h3>
                    <ul>
                        '.implode('', array_slice($categories, 0, 6)).'
                    </ul>
                    <h3>Subcategories</h3>
                    <ul>
                        '.implode('', array_slice($categories, 6, 6)).'
                    </ul>
                    <p>For detailed category reports and analytics, please <a href="/login">log in to the system</a>.</p>
                </div>
            </div>
        ';
    }

    /**
     * Generate content for generic template.
     */
    protected function generateGenericContent(): string
    {
        return '
            <div class="restricted-content">
                <h2>Restricted Content</h2>
                <p class="access-denied">Access to this content is restricted to authorized users only.</p>
                <div class="login-prompt">
                    <p>This section contains sensitive information that requires proper authentication. If you are seeing this page, you may not have the necessary permissions.</p>
                    <p>To request access, please contact your system administrator or <a href="/login">log in with your credentials</a>.</p>
                    <div class="auth-box">
                        <h3>Authentication Required</h3>
                        <form class="mock-form">
                            <div>
                                <label for="username">Username:</label>
                                <input type="text" id="username" name="username">
                            </div>
                            <div>
                                <label for="password">Password:</label>
                                <input type="password" id="password" name="password">
                            </div>
                            <div>
                                <button type="submit">Log In</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        ';
    }
}
