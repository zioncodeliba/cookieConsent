# Developer Guide - WP Cookie Consent Manager

## Plugin Overview

A WordPress plugin for managing cookie consent and scripts. The plugin enables websites to comply with GDPR and CCPA requirements by giving users control over cookies and scripts loaded on the site.

## Plugin Structure

### Main Files
- `wp-cookie-consent-manager.php` - Main plugin file
- `includes/Admin.php` - Main admin interface management
- `includes/Consent.php` - Consent logic and categories
- `includes/Dashboard.php` - External dashboard connection

### Directory Structure
```
wp-cookie-consent-manager/
├── assets/                 # CSS, JS and design files
│   ├── css/                # Stylesheets
│   └── js/                 # Scripts
├── includes/               # Main PHP code
│   ├── Admin/              # Admin interface
│   │   ├── Pages/          # Admin pages
│   │   └── Ajax/           # AJAX handling
│   └── Consent.php         # Consent logic
├── inc/                   # Helper functions
├── tools/                 # Development and testing tools
└── vendor/               # External libraries
```

## Main Features

### 1. Cookie Consent Banner (Frontend)
- **Location**: Appears on site when plugin is active and license is valid
- **4 Main Buttons**:
  - **Accept All** - Approves all cookies and scripts
  - **Reject All** - Rejects non-essential cookies
  - **Clear History** - Deletes existing cookies
  - **Settings** - Opens detailed category selection window

### 2. Admin Interface
Located in the sidebar menu under "Cookie Consent" with the following submenus:

#### A. Settings
**3 Main Tabs:**

1. **General Settings**
   - License configuration
   - Banner title and content
   - Auto-sync settings (currently inactive)

2. **Design Settings**
   - Banner position
   - Banner colors
   - Banner size
   - Live preview

3. **Categories**
   - Default categories list
   - Add/delete categories option
   - **Important**: "Necessary" category cannot be deleted or modified

#### B. Cookie & Script Sync
**2 Tabs:**

1. **Cookies**
   - Sync button
   - Current cookies table
   - Sync history

2. **Scripts**
   - Sync button
   - Current scripts table
   - Sync history

#### C. Management
- Data management and manual deletion

#### D. Statistics
- Usage data and consent statistics

#### E. Activity History
- User activity logs

### 3. Category System
- **Default Categories**: necessary, functional, performance, analytics, advertisement, others
- **"Necessary" Category**: Always enabled and cannot be modified
- **Dynamic Management**: Categories stored in separate database table

### 4. Automatic Detection System
- **Cookie Detection**: Automatic scanning of cookies on site
- **Script Detection**: Detection of scripts registered in WordPress
- **Category Mapping**: Automatic assignment to categories based on heuristics

## Technical Architecture

### 1. Main Classes
- `WP_CCM_Admin` - Admin management
- `WP_CCM_Consent` - Consent logic
- `WP_CCM_Admin_Assets` - CSS/JS file management
- `WP_CCM_Admin_Ajax_Consent` - AJAX request handling

### 2. Database
- `wp_ck_categories` - Categories table
- `wp_ck_cookies` - Cookies table
- `wp_ck_scripts` - Scripts table
- `wp_options` - Plugin settings

### 3. External API
- Remote dashboard connection: `WPCCM_DASHBOARD_API_URL`
- Automatic update system

## Important Helper Functions

### Texts and Translations
```php
wpccm_text($key) // Returns translated text
```

### Categories
```php
WP_CCM_Consent::categories() // Returns categories array
WP_CCM_Consent::get_categories_with_details() // Full category details
```

### Settings
```php
get_option('wpccm_custom_categories') // Custom categories
get_option('wpccm_general_settings') // General settings
```

## Future Development Points

1. **Auto Sync**: Currently not fully functional
2. **Heuristic Detection Improvement**: Update detection algorithms
3. **Additional Language Support**: Expand translation system
4. **Optimization**: Performance and loading time improvements

## Important Development Notes

- Plugin uses WordPress hooks system
- All files protected from direct access (`!defined('ABSPATH')`)
- Extensive AJAX usage for smooth user experience
- Plugin supports automatic updates through PUC system

## Key Features Summary

### Frontend Banner Behavior
- Displays when valid license is active
- Cookie/script blocking based on user consent
- Category-based consent management
- Clear history functionality

### Admin Management
- Real-time preview of banner design
- Automatic detection and categorization
- Manual override capabilities
- Comprehensive history tracking

### Data Flow
1. User visits site → Banner appears
2. User makes consent choices → Data stored
3. Scripts/cookies loaded based on consent
4. Admin can view statistics and manage settings

---

**Version**: 1.0.32
**Author**: code&core
**License**: GPL v2 or later