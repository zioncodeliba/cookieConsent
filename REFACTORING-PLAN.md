# Cookie Consent Manager - Refactoring Plan

## Overview
This document outlines the comprehensive refactoring plan for the WordPress Cookie Consent Manager plugin. The goal is to move from a monolithic structure to a modular, maintainable architecture while preserving all existing functionality.

## Current State
The plugin currently has scattered code across multiple files with mixed concerns and dependencies. The refactoring will organize this into a clean, modular structure.

## Refactoring Goals
1. **Preserve all working functionality** - No features should be lost
2. **Improve maintainability** - Clear separation of concerns
3. **Enhance testability** - Modular components easier to test
4. **Reduce dependencies** - Clear dependency management
5. **Improve performance** - Optimized loading and execution

## New Structure

### Frontend JavaScript Structure
```
assets/js/
├── stores/           # State management
│   ├── consent.js    # ✅ COMPLETED - Consent preferences
│   └── consent-legacy.js  # 🔄 LEGACY - Old consent logic
├── loaders/          # Content loading
│   ├── cc-loader.js  # ✅ COMPLETED - Script/iframe loader
│   └── consent-legacy.js  # 🔄 LEGACY - Old loader logic
├── cleaners/         # Cookie management
│   ├── cookie-janitor.js  # ✅ COMPLETED - Cookie cleanup
│   └── consent-legacy.js  # 🔄 LEGACY - Old cleanup logic
├── admin/            # Admin functionality
│   ├── table-manager-legacy.js      # 🔄 LEGACY - Table management
│   ├── handle-mapper-legacy.js      # 🔄 LEGACY - Handle mapping
│   ├── scanner-legacy.js            # 🔄 LEGACY - Cookie scanning
│   └── cookie-suggestions-legacy.js # 🔄 LEGACY - Cookie suggestions
└── utils/            # Utility functions
    └── (to be created)
```

### Backend PHP Structure
```
├── inc/              # Core functionality
│   ├── header-filter.php      # ✅ COMPLETED - Header filtering
│   ├── filters-script-tag.php # ✅ COMPLETED - Script filtering
│   ├── ajax-delete-http-only.php # ✅ COMPLETED - AJAX deletion
│   ├── enqueue.php            # ✅ COMPLETED - Script enqueuing
│   └── (to be created)
├── admin/            # Admin functionality
│   ├── class-cc-settings-page.php # ✅ COMPLETED - Settings page
│   ├── views/cc-settings-page.php # ✅ COMPLETED - Settings view
│   └── (to be created)
├── includes/         # Legacy classes (to be refactored)
│   ├── Consent-legacy.php     # 🔄 LEGACY - Consent management
│   ├── Admin-legacy.php       # 🔄 LEGACY - Admin functionality
│   └── Dashboard-legacy.php   # 🔄 LEGACY - Dashboard functionality
└── (to be created)
```

## Refactoring Phases

### Phase 1: ✅ COMPLETED - Core Infrastructure
- [x] Create new modular structure
- [x] Implement consent storage system
- [x] Implement script/iframe loader
- [x] Implement cookie janitor
- [x] Implement header filtering
- [x] Implement script tag filtering
- [x] Implement AJAX cookie deletion
- [x] Implement script enqueuing
- [x] Create admin settings page
- [x] Add consent state exposure
- [x] Harden code with security checks
- [x] Create testing checklist

### Phase 2: 🔄 IN PROGRESS - Legacy Code Preservation
- [x] Move existing consent.js to consent-legacy.js
- [x] Move existing table-manager.js to admin/table-manager-legacy.js
- [x] Move existing handle-mapper.js to admin/handle-mapper-legacy.js
- [x] Move existing scanner.js to admin/scanner-legacy.js
- [x] Move existing cookie-suggestions.js to admin/cookie-suggestions-legacy.js
- [x] Move existing Consent.php to includes/Consent-legacy.php
- [x] Move existing Admin.php to includes/Admin-legacy.php
- [x] Move existing Dashboard.php to includes/Dashboard-legacy.php

### Phase 3: ⏳ PLANNED - Function Migration
- [ ] **Consent Management**
  - [ ] Move `getCookie`, `setCookie`, `deleteCookie` to `utils/cookie-utils.js`
  - [ ] Move `currentState`, `storeState`, `storeNewState` to `stores/consent-state.js`
  - [ ] Move `isResolved` to `stores/consent-validation.js`
  - [ ] Move `activateDeferredScripts` to `loaders/script-loader.js`

- [ ] **Admin Table Management**
  - [ ] Move jQuery event handlers to `admin/events.js`
  - [ ] Move AJAX calls to `admin/api.js`
  - [ ] Move table manipulation to `admin/table-utils.js`
  - [ ] Move cookie management to `admin/cookie-manager.js`
  - [ ] Move UI updates to `admin/ui-updater.js`

- [ ] **Handle Mapping**
  - [ ] Move handle scanning to `admin/handle-scanner.js`
  - [ ] Move category selection to `admin/category-manager.js`
  - [ ] Move mapping storage to `admin/mapping-storage.js`

- [ ] **Cookie Scanning**
  - [ ] Move cookie parsing to `admin/cookie-parser.js`
  - [ ] Move category suggestions to `admin/category-suggester.js`
  - [ ] Move table rendering to `admin/table-renderer.js`

- [ ] **Cookie Suggestions**
  - [ ] Move suggestion logic to `admin/cookie-suggester.js`
  - [ ] Move AJAX handling to `admin/api.js`
  - [ ] Move UI rendering to `admin/ui-renderer.js`

### Phase 4: ⏳ PLANNED - PHP Class Refactoring
- [ ] **Consent Class**
  - [ ] Move `categories()` to `inc/consent-categories.php`
  - [ ] Move `get_categories_with_details()` to `inc/consent-categories.php`
  - [ ] Move `default_options()` to `inc/consent-defaults.php`

- [ ] **Admin Class**
  - [ ] Move menu management to `admin/class-admin-menu.php`
  - [ ] Move page rendering to `admin/class-admin-pages.php`
  - [ ] Move AJAX handlers to `admin/class-admin-ajax.php`
  - [ ] Move settings to `admin/class-admin-settings.php`
  - [ ] Move tables to `admin/class-admin-tables.php`
  - [ ] Move forms to `admin/class-admin-forms.php`
  - [ ] Move notices to `admin/class-admin-notices.php`

- [ ] **Dashboard Class**
  - [ ] Move dashboard functionality to `admin/class-dashboard.php`
  - [ ] Move widgets to `admin/class-dashboard-widgets.php`
  - [ ] Move statistics to `admin/class-dashboard-stats.php`
  - [ ] Move overview to `admin/class-dashboard-overview.php`

### Phase 5: ⏳ PLANNED - Integration & Testing
- [ ] **Backward Compatibility**
  - [ ] Create compatibility wrappers
  - [ ] Maintain old function signatures
  - [ ] Test all existing functionality

- [ ] **Performance Optimization**
  - [ ] Optimize script loading
  - [ ] Reduce bundle sizes
  - [ ] Implement lazy loading

- [ ] **Testing & Validation**
  - [ ] Run complete testing checklist
  - [ ] Validate all functionality
  - [ ] Performance testing
  - [ ] Cross-browser testing

### Phase 6: ⏳ PLANNED - Cleanup
- [ ] **Remove Legacy Files**
  - [ ] Remove all `*-legacy.js` files
  - [ ] Remove all `*-legacy.php` files
  - [ ] Clean up old includes directory

- [ ] **Documentation Update**
  - [ ] Update README files
  - [ ] Update inline documentation
  - [ ] Create developer guides

## Dependencies to Check

### JavaScript Dependencies
- [ ] jQuery library availability
- [ ] WordPress globals (WPCCM, WPCCM_TABLE, etc.)
- [ ] DOM manipulation functions
- [ ] Cookie management functions
- [ ] AJAX communication functions

### PHP Dependencies
- [ ] WordPress core functions
- [ ] Custom functions (wpccm_text, etc.)
- [ ] Options and settings management
- [ ] AJAX endpoint handlers
- [ ] Admin hooks and filters

## Integration Points

### Frontend Integration
- [ ] Replace old consent system calls
- [ ] Update script loading mechanisms
- [ ] Update cookie management calls
- [ ] Update admin interface calls

### Backend Integration
- [ ] Replace old class instantiations
- [ ] Update function calls throughout codebase
- [ ] Update hook and filter registrations
- [ ] Update AJAX endpoint registrations

## Testing Strategy

### Unit Testing
- [ ] Test individual modules in isolation
- [ ] Test function inputs and outputs
- [ ] Test error handling and edge cases

### Integration Testing
- [ ] Test module interactions
- [ ] Test WordPress integration
- [ ] Test admin functionality

### End-to-End Testing
- [ ] Test complete user workflows
- [ ] Test consent management flow
- [ ] Test admin configuration flow

## Risk Mitigation

### Backward Compatibility
- [ ] Maintain old function signatures during transition
- [ ] Use compatibility wrappers if needed
- [ ] Gradual migration to avoid breaking changes

### Rollback Plan
- [ ] Keep backups of all original files
- [ ] Version control all changes
- [ ] Ability to revert to previous state

### Testing Strategy
- [ ] Comprehensive testing at each phase
- [ ] Staging environment testing
- [ ] User acceptance testing

## Success Criteria

### Functional Requirements
- [ ] All existing functionality preserved
- [ ] No breaking changes for users
- [ ] Improved performance metrics
- [ ] Better error handling

### Technical Requirements
- [ ] Clean, modular architecture
- [ ] Clear separation of concerns
- [ ] Reduced code duplication
- [ ] Improved maintainability

### Quality Requirements
- [ ] All tests passing
- [ ] No linting errors
- [ ] Proper documentation
- [ ] Performance improvements

## Timeline Estimate

### Phase 1: ✅ COMPLETED (Week 1)
- Core infrastructure implementation

### Phase 2: 🔄 IN PROGRESS (Week 2)
- Legacy code preservation

### Phase 3: ⏳ PLANNED (Weeks 3-4)
- Function migration

### Phase 4: ⏳ PLANNED (Weeks 5-6)
- PHP class refactoring

### Phase 5: ⏳ PLANNED (Weeks 7-8)
- Integration & testing

### Phase 6: ⏳ PLANNED (Week 9)
- Cleanup & documentation

**Total Estimated Time: 9 weeks**

## Next Steps

### Immediate Actions
1. ✅ Complete Phase 1 (DONE)
2. 🔄 Complete Phase 2 (IN PROGRESS)
3. ⏳ Begin Phase 3 planning

### This Week's Goals
1. Complete legacy file preservation
2. Create detailed migration plan for Phase 3
3. Identify all function dependencies

### Next Week's Goals
1. Begin function migration
2. Create new utility modules
3. Test migrated functionality

## Notes

### Important Considerations
- **Never remove working logic** - Only move and refactor
- **Maintain backward compatibility** - Use wrappers if needed
- **Test thoroughly** - Each phase must pass all tests
- **Document everything** - Clear migration path for future developers

### Questions to Resolve
- Are there any critical dependencies we missed?
- What is the impact on existing users?
- How do we handle database migrations?
- What is the rollback strategy?

### Resources Needed
- Development environment
- Testing environment
- User acceptance testing
- Performance monitoring tools
