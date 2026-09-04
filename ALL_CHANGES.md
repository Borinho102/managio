# All Changes Summary

## 1. Tenant Locale and Currency Implementation

### Issue
The tenant site was not loading currency and locale based on tenant settings when accessing module pages.

### Solution
Modified the module listing and details pages to use tenant-specific locale and currency with automatic fallback.

### Files Modified

#### `/modules/saas/controllers/Gb_admin.php`
- Updated `get_modules($company_id = null)` to accept tenant company ID parameter
- Updated `module_details($module, $company_id = null)` to accept tenant company ID parameter
- Both functions now:
  - Load tenant company info using `get_company_subscription_by_id($company_id)`
  - Extract tenant locale with fallback: `company->language || company->locale || 'en'`
  - Extract tenant currency with fallback: `company->currency || saas_default_currency()`
  - Pass `$tenant_locale` and `$tenant_currency` to views

#### `/modules/saas/views/packages/modules/get_modules.php`
- Updated to use `$tenant_locale` for localized module titles and descriptions
- Updated to use `$tenant_currency` for price display

#### `/modules/saas/views/packages/modules/module_details.php`
- Updated to use `$tenant_locale` for localized module titles

#### `/modules/saas/config/my_routes.php`
- Added route: `$route['admin/module_details/(:any)/(:num)'] = 'saas/gb_admin/module_details/$1/$2';`

### URL Examples
- `https://invest-logistic.managio.africa/admin/get_modules/42` - Loads modules for tenant 42
- `https://invest-logistic.managio.africa/admin/module_details/best-database-backup/42` - Module details for tenant 42

---

## 2. Per-Currency Pricing Fix

### Issue
When setting per-currency prices for modules, the prices would be saved but reset to empty when reloading the page.

### Root Cause
The controller deleted ALL per-currency prices before checking if new prices were posted. Empty form fields were skipped during save, causing those prices to be permanently deleted.

### Solution
Modified the save logic to only delete prices for currencies that were actually posted in the form.

### Files Modified

#### `/modules/saas/controllers/Packages.php` (lines 465-533)
- Added tracking of posted currency codes
- Changed to only delete prices for currencies that were posted
- Preserves prices for currencies not included in form submission

#### `/modules/saas/models/Saas_package_module_prices_model.php` (lines 120-145)
- Added fourth parameter `$currencies_array` to `delete_by_module()` method
- Added support for `where_in()` clause to delete multiple specific currencies
- Maintains backward compatibility with existing code

### How It Works
- When user submits form, only currencies that were in the form are deleted
- Empty price fields in the form result in those currencies being deleted (user intent)
- Non-empty prices are saved correctly
- Previously saved prices for currencies not in the form remain untouched

---

## Testing Checklist

### Tenant Locale/Currency
- [ ] Visit `admin/get_modules/42` with valid tenant ID
- [ ] Verify tenant currency is used for price display
- [ ] Verify tenant locale is used for module titles/descriptions
- [ ] Test fallback when tenant has no locale/currency set

### Per-Currency Pricing
- [ ] Set prices for multiple currencies and save
- [ ] Reload page and verify prices persist
- [ ] Update only some prices and verify others remain
- [ ] Clear a price field and verify it's deleted
- [ ] Leave some price fields empty and verify they're deleted

---

## Documentation
- `IMPLEMENTATION_SUMMARY.md` - Detailed tenant locale/currency documentation
- `PRICING_FIX_SUMMARY.md` - Detailed per-currency pricing fix documentation
- `CHANGES_SUMMARY.txt` - Quick reference summary
