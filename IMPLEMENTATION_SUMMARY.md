# Tenant Locale and Currency Implementation for Module Pages

## Overview
Implemented tenant-specific locale and currency loading for module pages (`admin/get_modules/{company_id}` and `admin/module_details/{module}/{company_id}`) with automatic fallback to defaults when tenant settings are not configured.

## Changes Made

### 1. Controller: `/modules/saas/controllers/Gb_admin.php`

#### Function: `get_modules($company_id = null)`
**Before:** Function didn't accept company_id parameter
**After:** 
- Accepts `$company_id` parameter from route (e.g., `admin/get_modules/42`)
- Loads tenant-specific company info using `get_company_subscription_by_id($company_id)`
- Extracts tenant locale with fallback chain: `company->language` → `company->locale` → `'en'`
- Extracts tenant currency with fallback chain: `company->currency` → `saas_default_currency()`
- Passes `$tenant_locale` and `$tenant_currency` to the view

#### Function: `module_details($module, $company_id = null)`
**Before:** Function didn't accept company_id parameter
**After:**
- Accepts optional `$company_id` parameter
- Same tenant locale/currency loading logic as `get_modules()`
- Passes tenant context to the view

### 2. View: `/modules/saas/views/packages/modules/get_modules.php`

#### Module Title Localization
**Before:**
```php
$module_title = (!empty($module->module_title)) ? saas_pick_localized($module->module_title) : ...;
$raw_descr = !empty($module->descriptions) ? saas_pick_localized($module->descriptions) : '';
```

**After:**
```php
$module_title = (!empty($module->module_title)) ? saas_pick_localized($module->module_title, $tenant_locale ?? null) : ...;
$raw_descr = !empty($module->descriptions) ? saas_pick_localized($module->descriptions, $tenant_locale ?? null) : '';
```

#### Price Display with Tenant Currency
**Before:**
```php
<?= display_money($module->price) ?>
```

**After:**
```php
<?= display_money($module->price, $tenant_currency ?? null) ?>
```

### 3. View: `/modules/saas/views/packages/modules/module_details.php`

#### Module Title Localization
**Before:**
```php
$module_title = (!empty($module->module_title)) ? saas_pick_localized($module->module_title) : ...;
```

**After:**
```php
$module_title = (!empty($module->module_title)) ? saas_pick_localized($module->module_title, $tenant_locale ?? null) : ...;
```

**Note:** This view already uses `saas_tenant_currency()` for the currency selector, which provides additional fallback support.

### 4. Routes: `/modules/saas/config/my_routes.php`

**Added:**
```php
$route['admin/module_details/(:any)/(:num)'] = 'saas/gb_admin/module_details/$1/$2';
```

This allows URLs like `admin/module_details/best-database-backup/42` where:
- `best-database-backup` = module name
- `42` = company/tenant ID

## How It Works

### Locale Fallback Chain
1. **Primary:** Tenant company's `language` field
2. **Secondary:** Tenant company's `locale` field  
3. **Fallback:** `'en'` (English)

### Currency Fallback Chain
1. **Primary:** Tenant company's `currency` field (e.g., 'XAF', 'USD', 'EUR')
2. **Fallback:** System default currency via `saas_default_currency()`

### Localized Content Resolution
The `saas_pick_localized($value, $locale)` function:
1. Checks if `$value` is JSON with locale keys (e.g., `{"en": "Title", "fr": "Titre"}`)
2. Returns content for requested locale if available
3. Falls back to English (`'en'`) if available
4. Falls back to first available locale
5. Returns original string if no localization found

## URL Examples

### Module List with Tenant Context
```
https://invest-logistic.managio.africa/admin/get_modules/42
```
- Loads modules for tenant company ID 42
- Displays prices in tenant's currency (e.g., XAF if company currency is XAF)
- Shows module titles/descriptions in tenant's language (e.g., French if language is 'fr')

### Module Details with Tenant Context
```
https://invest-logistic.managio.africa/admin/module_details/best-database-backup/42
```
- Shows details for 'best-database-backup' module
- Uses tenant company 42's locale and currency

## Backward Compatibility

✅ **Fully backward compatible:**
- `admin/get_modules` (without company_id) still works - uses current company context
- `admin/module_details/{module}` (without company_id) still works - uses current company context
- All existing routes and functionality preserved

## Testing Checklist

- [ ] Visit `admin/get_modules/{valid_company_id}` - verify tenant currency and locale are used
- [ ] Visit `admin/get_modules` (without ID) - verify it still works with current context
- [ ] Check module prices display in correct currency symbol
- [ ] Check module titles/descriptions show in tenant's language when localized
- [ ] Verify fallback to English and default currency when tenant settings are empty
- [ ] Test module details page with and without company_id parameter

## Database Fields Used

From `tbl_saas_companies` table:
- `language` - Tenant's preferred language (e.g., 'fr', 'en', 'es')
- `locale` - Alternative locale field
- `currency` - Tenant's preferred currency (e.g., 'XAF', 'USD', 'EUR')

## Related Functions

- `saas_pick_localized($value, $locale)` - Picks localized content with fallback
- `display_money($amount, $currency)` - Formats amount with currency symbol
- `saas_default_currency()` - Returns system default currency
- `get_company_subscription_by_id($id)` - Fetches company by ID
- `get_company_subscription()` - Fetches current company by subdomain
