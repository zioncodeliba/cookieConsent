<?php
/**
 * CC Detection Heuristics
 * Provides intelligent category suggestions for scripts and iframes
 * 
 * @package WP_Cookie_Consent_Manager
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Guess consent category for a script or iframe based on domain/URL patterns
 * 
 * @param string $src_or_domain The source URL or domain to analyze
 * @param string|null $handle Optional script handle for additional context
 * @return string|null Category suggestion or null if no match
 */
function cc_detect_guess_category($src_or_domain, $handle = null) {
    if (empty($src_or_domain)) {
        return null;
    }
    
    // Extract domain from URL if needed
    $domain = cc_detect_extract_domain($src_or_domain);
    if (!$domain) {
        return null;
    }
    
    // Convert to lowercase for comparison
    $domain_lower = strtolower($domain);
    $handle_lower = $handle ? strtolower($handle) : '';
    
    // Analytics services
    if (cc_detect_matches_patterns($domain_lower, [
        'googletagmanager.com',
        'google-analytics.com',
        'analytics.google.com',
        'gtag',
        'ga.js',
        'analytics.js'
    ]) || cc_detect_matches_patterns($handle_lower, [
        'google-analytics',
        'gtag',
        'ga',
        'analytics'
    ])) {
        return 'analytics';
    }
    
    // Marketing and advertising
    if (cc_detect_matches_patterns($domain_lower, [
        'doubleclick.net',
        'googleadservices.com',
        'googlesyndication.com',
        'facebook.com',
        'fbcdn.net',
        'facebook.net',
        'instagram.com',
        'twitter.com',
        't.co',
        'linkedin.com',
        'pinterest.com',
        'tiktok.com',
        'snapchat.com'
    ]) || cc_detect_matches_patterns($handle_lower, [
        'facebook',
        'fb',
        'instagram',
        'twitter',
        'linkedin',
        'pinterest',
        'tiktok',
        'snapchat',
        'ads',
        'advertising',
        'pixel'
    ])) {
        return 'marketing';
    }
    
    // Video platforms (usually marketing/analytics)
    if (cc_detect_matches_patterns($domain_lower, [
        'youtube.com',
        'ytimg.com',
        'youtu.be',
        'vimeo.com',
        'dailymotion.com',
        'twitch.tv',
        'netflix.com'
    ]) || cc_detect_matches_patterns($handle_lower, [
        'youtube',
        'vimeo',
        'dailymotion',
        'twitch',
        'netflix',
        'video'
    ])) {
        return 'marketing';
    }
    
    // Payment and e-commerce (functional)
    if (cc_detect_matches_patterns($domain_lower, [
        'stripe.com',
        'paypal.com',
        'square.com',
        'shopify.com',
        'woocommerce.com',
        'magento.com',
        'bigcommerce.com'
    ]) || cc_detect_matches_patterns($handle_lower, [
        'stripe',
        'paypal',
        'square',
        'shopify',
        'woocommerce',
        'magento',
        'payment',
        'checkout'
    ])) {
        return 'functional';
    }
    
    // Chat and support (functional)
    if (cc_detect_matches_patterns($domain_lower, [
        'intercom.com',
        'zendesk.com',
        'freshdesk.com',
        'helpscout.com',
        'crisp.chat',
        'tawk.to',
        'livechat.com'
    ]) || cc_detect_matches_patterns($handle_lower, [
        'intercom',
        'zendesk',
        'freshdesk',
        'helpscout',
        'crisp',
        'tawk',
        'livechat',
        'chat',
        'support'
    ])) {
        return 'functional';
    }
    
    // Maps and location (functional)
    if (cc_detect_matches_patterns($domain_lower, [
        'maps.google.com',
        'maps.googleapis.com',
        'openstreetmap.org',
        'mapbox.com',
        'here.com',
        'bing.com/maps'
    ]) || cc_detect_matches_patterns($handle_lower, [
        'google-maps',
        'maps',
        'mapbox',
        'openstreetmap',
        'location'
    ])) {
        return 'functional';
    }
    
    // Forms and surveys (functional)
    if (cc_detect_matches_patterns($domain_lower, [
        'typeform.com',
        'survey monkey.com',
        'googleforms.com',
        'forms.office.com',
        'jotform.com',
        'wufoo.com'
    ]) || cc_detect_matches_patterns($handle_lower, [
        'typeform',
        'surveymonkey',
        'googleforms',
        'forms',
        'survey',
        'form'
    ])) {
        return 'functional';
    }
    
    // Performance and optimization
    if (cc_detect_matches_patterns($domain_lower, [
        'cloudflare.com',
        'jsdelivr.net',
        'unpkg.com',
        'cdnjs.cloudflare.com',
        'googleapis.com',
        'gstatic.com'
    ]) || cc_detect_matches_patterns($handle_lower, [
        'cloudflare',
        'cdn',
        'optimize',
        'performance',
        'lazy',
        'minify'
    ])) {
        return 'performance';
    }
    
    // Social sharing and widgets (functional)
    if (cc_detect_matches_patterns($domain_lower, [
        'addthis.com',
        'sharethis.com',
        'addtoany.com',
        'floating-share-buttons.com'
    ]) || cc_detect_matches_patterns($handle_lower, [
        'addthis',
        'sharethis',
        'addtoany',
        'share',
        'social'
    ])) {
        return 'functional';
    }
    
    // WordPress core and essential (necessary)
    if (cc_detect_matches_patterns($domain_lower, [
        'wordpress.org',
        'wp.org',
        'wp-content',
        'wp-includes'
    ]) || cc_detect_matches_patterns($handle_lower, [
        'wp-',
        'wordpress',
        'wp-admin',
        'wp-content',
        'wp-includes'
    ])) {
        return 'necessary';
    }
    
    // Security and authentication (necessary)
    if (cc_detect_matches_patterns($domain_lower, [
        'recaptcha.google.com',
        'hcaptcha.com',
        'turnstile.cloudflare.com',
        'auth0.com',
        'okta.com'
    ]) || cc_detect_matches_patterns($handle_lower, [
        'recaptcha',
        'hcaptcha',
        'turnstile',
        'captcha',
        'auth',
        'security'
    ])) {
        return 'necessary';
    }
    
    // No match found
    return null;
}

/**
 * Extract domain from URL
 * 
 * @param string $url The URL to extract domain from
 * @return string|false Domain name or false on failure
 */
function cc_detect_extract_domain($url) {
    // Skip data URLs
    if (strpos($url, 'data:') === 0) {
        return false;
    }
    
    // If it's already a domain (no protocol), return as is
    if (!preg_match('/^https?:\/\//', $url)) {
        return $url;
    }
    
    // Parse URL
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host'])) {
        return false;
    }
    
    return $parsed['host'];
}

/**
 * Check if domain/handle matches any of the given patterns
 * 
 * @param string $text The text to check
 * @param array $patterns Array of patterns to match against
 * @return bool True if any pattern matches
 */
function cc_detect_matches_patterns($text, $patterns) {
    foreach ($patterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Get all available categories
 * 
 * @return array Array of category names
 */
function cc_detect_get_categories() {
    return [
        'necessary' => 'Necessary',
        'functional' => 'Functional',
        'analytics' => 'Analytics',
        'marketing' => 'Marketing',
        'performance' => 'Performance',
        'others' => 'Others'
    ];
}

/**
 * Get category display name
 * 
 * @param string $category Category key
 * @return string Display name
 */
function cc_detect_get_category_name($category) {
    $categories = cc_detect_get_categories();
    return isset($categories[$category]) ? $categories[$category] : ucfirst($category);
}
