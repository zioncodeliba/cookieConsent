# Cookie Consent Manager - Testing Checklist

## Overview
This document provides a comprehensive manual testing checklist for the WordPress Cookie Consent Manager plugin. Test each component systematically to ensure proper functionality.

## Pre-Testing Setup
- [ ] WordPress site is running (local or staging environment)
- [ ] Plugin is activated
- [ ] Browser developer tools are open
- [ ] Browser console is clear of errors
- [ ] Network tab is open for monitoring requests
- [ ] Application tab is open for inspecting cookies/storage

---

## 1. Admin Settings Page Testing

### 1.1 Access Control
- [ ] Navigate to Settings → Cookie Mappings
- [ ] Verify page loads without errors
- [ ] Test with non-admin user (should be denied access)
- [ ] Test with admin user (should have full access)

### 1.2 Cookie Mappings Table
- [ ] Verify table displays correctly
- [ ] Test "Add New Cookie Mapping" button
- [ ] Add test mapping: `_ga` → `Analytics`
- [ ] Add test mapping: `/_fbp.*/` → `Advertisement`
- [ ] Test "Remove" button for each mapping
- [ ] Verify form submission saves mappings
- [ ] Refresh page and verify mappings persist

### 1.3 Script Mappings Table
- [ ] Verify table displays correctly
- [ ] Test "Add New Script Mapping" button
- [ ] Add test mapping: `google-analytics` → `Analytics`
- [ ] Add test mapping: `facebook-pixel` → `Advertisement`
- [ ] Test "Remove" button for each mapping
- [ ] Verify form submission saves mappings
- [ ] Refresh page and verify mappings persist

### 1.4 Form Validation
- [ ] Test submitting empty mappings (should be ignored)
- [ ] Test submitting mappings with only key (should be ignored)
- [ ] Test submitting mappings with only category (should be ignored)
- [ ] Verify successful save message appears

---

## 2. Client-Side Consent Storage Testing

### 2.1 Initial State
- [ ] Open browser console
- [ ] Check `localStorage.getItem('cc_prefs_v1')`
- [ ] Verify default consent: `necessary: true`, others: `false`
- [ ] Check `document.cookie` for `cc_prefs_v1`
- [ ] Verify `window.ccGetConsent()` function exists

### 2.2 Consent Updates
- [ ] Call `setConsent({necessary: true, analytics: true})`
- [ ] Verify localStorage is updated
- [ ] Verify cookie is updated
- [ ] Check console for "Consent preferences updated" message
- [ ] Verify `cc:changed` event is dispatched
- [ ] Verify dataLayer push with `cc_consent_changed` event

### 2.3 Consent Retrieval
- [ ] Call `getConsent()` and verify returns current state
- [ ] Call `isAllowed('analytics')` and verify returns boolean
- [ ] Call `getConsentForCategories(['analytics', 'advertisement'])`
- [ ] Call `areRequiredCategoriesAllowed(['necessary'])` (should return true)

### 2.4 Consent Clearing
- [ ] Call `clearConsent()`
- [ ] Verify localStorage is cleared
- [ ] Verify cookie is deleted
- [ ] Check console for "Consent preferences cleared" message
- [ ] Verify `cc:changed` event is dispatched
- [ ] Verify dataLayer push with `consent: null`

---

## 3. Script/Iframe Loading Testing

### 3.1 Script Tag Filtering
- [ ] Check if scripts with `data-cc` attributes exist in DOM
- [ ] Verify scripts have `type="text/plain"`
- [ ] Verify scripts have `data-src` attribute (if external)
- [ ] Check browser console for loader initialization messages

### 3.2 Dynamic Script Loading
- [ ] Grant consent for "Analytics" category
- [ ] Verify scripts with `data-cc="analytics"` are hydrated
- [ ] Check if `type="text/plain"` is removed
- [ ] Verify `data-src` becomes `src` (if applicable)
- [ ] Check console for "Script hydrated for category: analytics" message

### 3.3 Iframe Loading
- [ ] Check if iframes with `data-cc` attributes exist
- [ ] Grant consent for relevant category
- [ ] Verify iframe `src` is set
- [ ] Check console for iframe hydration messages

### 3.4 Consent Change Handling
- [ ] Revoke consent for "Analytics" category
- [ ] Verify analytics scripts are blocked
- [ ] Check if scripts are hidden or marked as blocked
- [ ] Grant consent again and verify rehydration

---

## 4. Cookie Management Testing

### 4.1 Cookie Janitor Initialization
- [ ] Check console for "Cookie Janitor initialized" message
- [ ] Verify janitor is running (check status)
- [ ] Check if protected cookies list is loaded

### 4.2 Cookie Sweeping
- [ ] Set test cookies: `test_analytics`, `test_ad`, `cc_prefs_v1`
- [ ] Grant consent only for "Analytics"
- [ ] Wait for cookie sweep interval
- [ ] Verify `test_analytics` cookie remains
- [ ] Verify `test_ad` cookie is deleted
- [ ] Verify `cc_prefs_v1` cookie is protected

### 4.3 Manual Cookie Deletion
- [ ] Call `deleteCookieEverywhere('test_cookie')`
- [ ] Verify cookie is deleted from document.cookie
- [ ] Verify cookie is removed from localStorage
- [ ] Verify cookie is removed from sessionStorage

---

## 5. Server-Side Header Filtering Testing

### 5.1 Set-Cookie Header Filtering
- [ ] Set up test scenario with cookie mappings
- [ ] Grant consent for specific categories
- [ ] Trigger server response that sets cookies
- [ ] Check Network tab for Set-Cookie headers
- [ ] Verify only allowed cookies are set
- [ ] Check server logs for filtering messages (if WP_DEBUG enabled)

### 5.2 Cookie Name Mapping
- [ ] Add cookie mapping: `test_cookie` → `analytics`
- [ ] Grant consent for analytics
- [ ] Trigger server response setting `test_cookie`
- [ ] Verify cookie is set
- [ ] Revoke analytics consent
- [ ] Trigger server response setting `test_cookie`
- [ ] Verify cookie is NOT set

---

## 6. AJAX Cookie Deletion Testing

### 6.1 AJAX Endpoint Access
- [ ] Check if AJAX endpoint is accessible
- [ ] Verify nonce verification works
- [ ] Test with invalid nonce (should fail)
- [ ] Test with valid nonce (should succeed)

### 6.2 Cookie Deletion Process
- [ ] Set test cookies: `test_http_only`, `test_regular`
- [ ] Call AJAX endpoint with cookie names
- [ ] Verify cookies are deleted
- [ ] Check response for success/error messages
- [ ] Verify HttpOnly cookies are handled correctly

---

## 7. DataLayer Integration Testing

### 7.1 Initial Consent State
- [ ] Check if `window.dataLayer` exists
- [ ] Verify `cc_consent_initialized` event is pushed
- [ ] Verify consent data structure is correct

### 7.2 Consent Change Events
- [ ] Change consent preferences
- [ ] Verify `cc_consent_changed` event is pushed
- [ ] Verify consent data is updated
- [ ] Check GTM/GA4 triggers (if configured)

### 7.3 Consent Clear Events
- [ ] Clear consent preferences
- [ ] Verify `cc_consent_changed` event with `consent: null`
- [ ] Verify GTM/GA4 receives clear event

---

## 8. Cross-Browser Compatibility Testing

### 8.1 Chrome/Chromium
- [ ] Test all functionality
- [ ] Verify console messages
- [ ] Check cookie handling
- [ ] Test localStorage functionality

### 8.2 Firefox
- [ ] Test all functionality
- [ ] Verify console messages
- [ ] Check cookie handling
- [ ] Test localStorage functionality

### 8.3 Safari
- [ ] Test all functionality
- [ ] Verify console messages
- [ ] Check cookie handling
- [ ] Test localStorage functionality

### 8.4 Edge
- [ ] Test all functionality
- [ ] Verify console messages
- [ ] Check cookie handling
- [ ] Test localStorage functionality

---

## 9. Mobile Device Testing

### 9.1 iOS Safari
- [ ] Test consent banner display
- [ ] Verify touch interactions
- [ ] Check cookie storage
- [ ] Test script loading

### 9.2 Android Chrome
- [ ] Test consent banner display
- [ ] Verify touch interactions
- [ ] Check cookie storage
- [ ] Test script loading

---

## 10. Performance Testing

### 10.1 Page Load Impact
- [ ] Measure page load time without plugin
- [ ] Measure page load time with plugin
- [ ] Verify acceptable performance impact
- [ ] Check for memory leaks

### 10.2 Script Loading Performance
- [ ] Test with multiple blocked scripts
- [ ] Verify hydration doesn't block page
- [ ] Check for performance bottlenecks

---

## 11. Error Handling Testing

### 11.1 Invalid Consent Data
- [ ] Corrupt localStorage data
- [ ] Corrupt cookie data
- [ ] Verify graceful fallback to defaults
- [ ] Check console for error messages

### 11.2 Network Issues
- [ ] Test with slow network
- [ ] Test with network failures
- [ ] Verify graceful degradation

### 11.3 JavaScript Errors
- [ ] Introduce JavaScript errors
- [ ] Verify plugin continues functioning
- [ ] Check error logging

---

## 12. Security Testing

### 12.1 Nonce Verification
- [ ] Test AJAX endpoints with invalid nonces
- [ ] Verify requests are rejected
- [ ] Test with expired nonces

### 12.2 XSS Prevention
- [ ] Test with malicious input in cookie names
- [ ] Test with malicious input in script handles
- [ ] Verify proper escaping

### 12.3 CSRF Protection
- [ ] Test form submissions without proper tokens
- [ ] Verify requests are rejected

---

## 13. Integration Testing

### 13.1 WordPress Core
- [ ] Test with default WordPress theme
- [ ] Test with popular themes
- [ ] Verify no conflicts with core functionality

### 13.2 Popular Plugins
- [ ] Test with WooCommerce
- [ ] Test with Yoast SEO
- [ ] Test with Contact Form 7
- [ ] Verify no conflicts

### 13.3 Third-Party Services
- [ ] Test with Google Analytics
- [ ] Test with Facebook Pixel
- [ ] Test with Google Tag Manager
- [ ] Verify proper integration

---

## 14. Final Verification

### 14.1 Complete Workflow
- [ ] Test complete user journey
- [ ] Verify all components work together
- [ ] Check for any remaining issues

### 14.2 Documentation
- [ ] Verify all features are documented
- [ ] Check for missing information
- [ ] Update documentation if needed

---

## Testing Notes

### Environment Details
- **WordPress Version:** _______
- **PHP Version:** _______
- **Browser:** _______
- **Device:** _______
- **Date:** _______

### Issues Found
1. _______
2. _______
3. _______

### Recommendations
1. _______
2. _______
3. _______

---

## Sign-off

- [ ] All tests completed
- [ ] Issues documented
- [ ] Recommendations provided
- [ ] Ready for production deployment

**Tester:** _______  
**Date:** _______  
**Status:** _______
