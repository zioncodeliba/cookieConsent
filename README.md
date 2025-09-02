# WP Cookie Consent Manager

A comprehensive WordPress plugin for managing cookie consent and user preferences with advanced features for script filtering, cookie management, and GDPR compliance.

## Features

### 🍪 **Cookie Consent Management**
- **Granular Consent Categories**: Necessary, Functional, Performance, Analytics, Advertisement, Others
- **Persistent Storage**: localStorage + cookie mirroring with 7-day expiry
- **Global Access**: `window.ccGetConsent()` for easy integration
- **DataLayer Integration**: Automatic GTM/GA4 event pushing

### 🔒 **Advanced Security & Privacy**
- **Server-Side Header Filtering**: Prevents unauthorized cookies from being set
- **Script Tag Filtering**: Converts scripts to consent-controlled placeholders
- **HttpOnly Cookie Deletion**: AJAX endpoint for secure cookie removal
- **Nonce Verification**: All AJAX requests are secured

### ⚡ **Performance & UX**
- **Dynamic Script Loading**: Scripts load only when consent is granted
- **MutationObserver Support**: Handles dynamically added content
- **Resource Hints**: Preload critical consent scripts
- **Async Loading**: Non-blocking script execution

### 🛠️ **Admin Management**
- **Settings Page**: Settings → Cookie Mappings
- **Cookie Mappings**: Map cookie names/regex to consent categories
- **Script Mappings**: Map script handles to consent categories
- **Real-time Updates**: Immediate effect on consent changes

## Installation

### 1. Upload Plugin
Upload the plugin files to `/wp-content/plugins/wp-cookie-consent-manager/`

### 2. Activate Plugin
Activate the plugin through the 'Plugins' menu in WordPress

### 3. Configure Mappings
Go to **Settings → Cookie Mappings** to set up:
- Cookie name mappings
- Script handle mappings

## Configuration

### Cookie Mappings
Map cookie names or regex patterns to consent categories:

```php
// Example cookie mappings
'_ga' => 'analytics',
'/_fbp.*/' => 'advertisement',
'google_*' => 'analytics',
'wordpress_' => 'necessary'
```

### Script Mappings
Map script handles to consent categories:

```php
// Example script mappings
'google-analytics' => 'analytics',
'facebook-pixel' => 'advertisement',
'woocommerce' => 'functional'
```

### Consent Categories
- **Necessary**: Essential cookies (always enabled)
- **Functional**: User preference cookies
- **Performance**: Optimization cookies
- **Analytics**: Tracking cookies
- **Advertisement**: Marketing cookies
- **Others**: Miscellaneous cookies

## Usage

### Frontend Integration

#### 1. Basic Consent Check
```javascript
// Check if analytics consent is granted
if (window.ccGetConsent().analytics) {
    // Load Google Analytics
    loadGoogleAnalytics();
}
```

#### 2. Consent Change Listening
```javascript
// Listen for consent changes
document.addEventListener('cc:changed', function(event) {
    const consent = event.detail.preferences;
    //console.log('Consent updated:', consent);
    
    if (consent.analytics) {
        // User granted analytics consent
        initializeAnalytics();
    }
});
```

#### 3. Script Tag Filtering
```html
<!-- Scripts are automatically filtered based on consent -->
<script type="text/plain" data-cc="analytics" data-src="https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID">
    // This script will only load if analytics consent is granted
</script>
```

#### 4. Iframe Filtering
```html
<!-- Iframes are automatically filtered based on consent -->
<iframe data-cc="advertisement" data-src="https://example.com/ad">
    <!-- This iframe will only load if advertisement consent is granted -->
</iframe>
```

### Backend Integration

#### 1. Filter Script Tags
```php
// Automatically filters script tags based on consent
// No additional code needed - works out of the box
```

#### 2. Filter Set-Cookie Headers
```php
// Automatically filters Set-Cookie headers based on consent
// No additional code needed - works out of the box
```

#### 3. AJAX Cookie Deletion
```php
// Delete cookies including HttpOnly cookies
wp_ajax_wpccm_delete_cookies
wp_ajax_nopriv_wpccm_delete_cookies
```

## API Reference

### JavaScript Functions

#### `window.ccGetConsent()`
Returns current consent preferences:
```javascript
const consent = window.ccGetConsent();
// Returns: { necessary: true, analytics: false, advertisement: false, ... }
```

#### `setConsent(preferences)`
Sets consent preferences:
```javascript
import { setConsent } from './assets/js/stores/consent.js';

setConsent({
    necessary: true,
    analytics: true,
    advertisement: false
});
```

#### `isAllowed(category)`
Checks if a category is allowed:
```javascript
import { isAllowed } from './assets/js/stores/consent.js';

if (isAllowed('analytics')) {
    // Load analytics
}
```

### PHP Functions

#### `wpccm_get_cookie_mappings()`
Get cookie name mappings:
```php
$mappings = wpccm_get_cookie_mappings();
// Returns: ['_ga' => 'analytics', '_fbp' => 'advertisement']
```

#### `wpccm_get_script_mappings()`
Get script handle mappings:
```php
$mappings = wpccm_get_script_mappings();
// Returns: ['google-analytics' => 'analytics']
```

#### `wpccm_add_cookie_mapping($name, $category)`
Add a cookie mapping:
```php
wpccm_add_cookie_mapping('_ga', 'analytics');
```

## DataLayer Events

### `cc_consent_initialized`
Fired when consent system initializes:
```javascript
{
    event: 'cc_consent_initialized',
    consent: {
        necessary: true,
        analytics: false,
        advertisement: false
    }
}
```

### `cc_consent_changed`
Fired when consent preferences change:
```javascript
{
    event: 'cc_consent_changed',
    consent: {
        necessary: true,
        analytics: true,
        advertisement: false
    }
}
```

## File Structure

```
wp-cookie-consent-manager/
├── assets/
│   ├── js/
│   │   ├── stores/
│   │   │   ├── consent.js              # ✅ New consent system
│   │   │   └── consent-legacy.js       # 🔄 Legacy system (refactoring)
│   │   ├── loaders/
│   │   │   ├── cc-loader.js            # ✅ Script/iframe loader
│   │   │   └── consent-legacy.js       # 🔄 Legacy loader (refactoring)
│   │   ├── cleaners/
│   │   │   ├── cookie-janitor.js       # ✅ Cookie cleanup
│   │   │   └── consent-legacy.js       # 🔄 Legacy cleanup (refactoring)
│   │   └── admin/                      # Admin functionality
│   │       ├── table-manager-legacy.js # 🔄 Legacy table management
│   │       ├── handle-mapper-legacy.js # 🔄 Legacy handle mapping
│   │       ├── scanner-legacy.js       # 🔄 Legacy cookie scanning
│   │       └── cookie-suggestions-legacy.js # 🔄 Legacy suggestions
│   └── css/
│       └── consent.css                 # Consent styling
├── inc/                                # Core functionality
│   ├── header-filter.php               # ✅ Header filtering
│   ├── filters-script-tag.php          # ✅ Script filtering
│   ├── ajax-delete-http-only.php       # ✅ AJAX deletion
│   └── enqueue.php                     # ✅ Script enqueuing
├── admin/                              # Admin functionality
│   ├── class-cc-settings-page.php      # ✅ Settings page
│   └── views/
│       └── cc-settings-page.php        # ✅ Settings view
├── includes/                           # Legacy classes (refactoring)
│   ├── Consent-legacy.php              # 🔄 Legacy consent class
│   ├── Admin-legacy.php                # 🔄 Legacy admin class
│   └── Dashboard-legacy.php            # 🔄 Legacy dashboard class
├── wp-cookie-consent-manager.php       # Main plugin file
├── REFACTORING-PLAN.md                 # Detailed refactoring plan
├── TESTING.md                          # Comprehensive testing checklist
└── README.md                           # This file
```

## Development

### Current Status
- **Phase 1**: ✅ COMPLETED - Core infrastructure
- **Phase 2**: 🔄 IN PROGRESS - Legacy code preservation
- **Phase 3**: ⏳ PLANNED - Function migration
- **Phase 4**: ⏳ PLANNED - PHP class refactoring
- **Phase 5**: ⏳ PLANNED - Integration & testing
- **Phase 6**: ⏳ PLANNED - Cleanup & documentation

### Adding New Features
1. **Frontend**: Add to appropriate module in `assets/js/`
2. **Backend**: Add to appropriate module in `inc/` or `admin/`
3. **Testing**: Update `TESTING.md` with new test cases
4. **Documentation**: Update this README with new features

### Refactoring Guidelines
- **Never remove working logic** - Only move and refactor
- **Maintain backward compatibility** - Use wrappers if needed
- **Test thoroughly** - Each phase must pass all tests
- **Document everything** - Clear migration path for future developers

## Testing

### Manual Testing
Use the comprehensive testing checklist in `TESTING.md`:

1. **Admin Settings Page Testing**
2. **Client-Side Consent Storage Testing**
3. **Script/Iframe Loading Testing**
4. **Cookie Management Testing**
5. **Server-Side Header Filtering Testing**
6. **AJAX Cookie Deletion Testing**
7. **DataLayer Integration Testing**

### Automated Testing
- Unit tests for individual modules
- Integration tests for module interactions
- End-to-end tests for complete workflows

## Troubleshooting

### Common Issues

#### Scripts Not Loading
- Check if consent is granted for the required category
- Verify script has correct `data-cc` attribute
- Check browser console for errors

#### Cookies Not Being Filtered
- Verify cookie mapping exists in admin settings
- Check if user has granted consent for the category
- Enable WP_DEBUG for detailed logging

#### AJAX Errors
- Verify nonce is valid
- Check user permissions
- Review browser console for error details

### Debug Mode
Enable WordPress debug mode for detailed logging:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Support

### Documentation
- **REFACTORING-PLAN.md**: Detailed development roadmap
- **TESTING.md**: Comprehensive testing guide
- **Inline Code Comments**: Detailed function documentation

### Development
- **Modular Architecture**: Easy to extend and maintain
- **Clear Separation of Concerns**: Each module has a specific purpose
- **Comprehensive Testing**: Thorough testing at each phase

## License

GPL v2 or later

## Changelog

### Version 1.0.0
- ✅ Core consent management system
- ✅ Script and iframe filtering
- ✅ Cookie header filtering
- ✅ Admin settings page
- ✅ AJAX cookie deletion
- ✅ DataLayer integration
- ✅ Comprehensive testing framework
- 🔄 Legacy code preservation (refactoring in progress)

## Contributing

1. Follow the refactoring plan in `REFACTORING-PLAN.md`
2. Maintain backward compatibility
3. Add comprehensive tests
4. Update documentation
5. Follow WordPress coding standards

---

**Note**: This plugin is currently undergoing refactoring to improve maintainability and performance. All existing functionality is preserved and working. See `REFACTORING-PLAN.md` for detailed development roadmap.
