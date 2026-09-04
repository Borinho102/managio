# Module Per-Currency Pricing Fix

## Issue
When setting per-currency prices for modules at `https://managio.africa/saas/packages/set_module_price/7`, the prices would be saved but then reset to empty when reloading the page.

## Root Cause
In `/modules/saas/controllers/Packages.php`, the `update_modules()` function had a critical bug:

**Line 469 (OLD CODE):**
```php
// Delete ALL prices BEFORE checking if any new prices exist
$this->saas_package_module_prices_model->delete_by_module($module_id);
```

This caused the following problem:
1. User sets prices: USD = 5.00, EUR = 10.00, leaves XAF and XOF empty
2. Form submits all currencies (including empty ones)
3. Controller **deletes ALL existing prices** from the database
4. Controller processes posted prices but **skips empty amounts** (line 512)
5. Only USD and EUR are re-saved
6. **XAF and XOF prices are permanently deleted** (even if they existed before)

## The Fix

### 1. Updated `/modules/saas/controllers/Packages.php` (lines 465-533)

**Changed from:** Delete all prices upfront  
**Changed to:** Only delete prices for currencies that were actually posted in the form

```php
// Track which currencies were posted
$posted_currencies = [];

foreach ($posted_prices as $currency_code => $entry) {
    $currency_code = strtoupper(trim((string) ($entry['currency'] ?? $currency_code)));
    $posted_currencies[] = $currency_code;
    // ... process and save non-empty prices ...
}

// Only delete prices for currencies that were actually posted
if (!empty($posted_currencies)) {
    $this->saas_package_module_prices_model->delete_by_module($module_id, null, null, $posted_currencies);
}
```

### 2. Updated `/modules/saas/models/Saas_package_module_prices_model.php`

Added support for deleting multiple currencies at once:

```php
/**
 * Delete prices for a module (optionally per currency or array of currencies)
 * @param int $package_module_id
 * @param string|array|null $currency Single currency code or array of currency codes
 * @param string|null $billing_cycle
 * @return bool
 */
public function delete_by_module($package_module_id, $currency = null, $billing_cycle = null, $currencies_array = null)
{
    // ... validation ...
    
    // Handle array of currencies (for selective deletion)
    if (is_array($currencies_array) && !empty($currencies_array)) {
        $this->db->where_in('currency', array_map('strtoupper', $currencies_array));
    } elseif ($currency !== null) {
        $this->db->where('currency', strtoupper($currency));
    }
    
    // ... delete ...
}
```

## How It Works Now

### Before Fix:
```
User sets: USD=5, EUR=10, leaves XAF empty
↓
Form submits: {USD: {amount: 5}, EUR: {amount: 10}, XAF: {amount: ''}}
↓
DELETE ALL prices (including previously saved XAF)
↓
Save: USD=5, EUR=10 (XAF skipped because empty)
↓
Result: XAF price LOST ❌
```

### After Fix:
```
User sets: USD=5, EUR=10, leaves XAF empty
↓
Form submits: {USD: {amount: 5}, EUR: {amount: 10}, XAF: {amount: ''}}
↓
Collect posted currencies: [USD, EUR, XAF]
↓
DELETE only posted currencies: USD, EUR, XAF
↓
Save: USD=5, EUR=10 (XAF skipped because empty)
↓
Result: XAF price deleted (as intended by user leaving it empty) ✅
```

## Behavior

### Scenario 1: User sets new prices
- All prices in form are saved correctly
- Empty prices are deleted (user explicitly cleared them)

### Scenario 2: User only changes some prices
- Only changed currencies are deleted and re-saved
- Unchanged currencies remain untouched

### Scenario 3: User doesn't touch the per-currency section
- No prices are deleted or saved
- Existing prices remain intact

## Testing

Test the following scenarios:

1. **Set prices for multiple currencies:**
   - Set USD = 5.00, EUR = 10.00
   - Leave XAF empty
   - Save
   - Reload page
   - ✅ USD and EUR should show 5.00 and 10.00
   - ✅ XAF should be empty

2. **Update existing price:**
   - Change USD from 5.00 to 7.00
   - Save
   - Reload page
   - ✅ USD should show 7.00
   - ✅ Other currencies unchanged

3. **Clear a price:**
   - Set USD = 5.00
   - Save
   - Clear USD field (make it empty)
   - Save
   - Reload page
   - ✅ USD should be empty

## Files Modified

1. `/modules/saas/controllers/Packages.php` - Lines 465-533
2. `/modules/saas/models/Saas_package_module_prices_model.php` - Lines 120-145

## Backward Compatibility

✅ Fully backward compatible:
- Existing function signatures unchanged
- Optional fourth parameter added to `delete_by_module()`
- All existing calls to `delete_by_module()` still work
