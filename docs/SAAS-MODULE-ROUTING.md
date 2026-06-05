# Managio — Perfect SaaS module: routing reference

> **Audience:** technical partners integrating with or extending Managio (Perfex CRM 3.4).  
> **Scope:** where the `/saas/` URL prefix lives in the codebase, how requests are routed, and a practical map of sub-routes.  
> **Last updated:** 2026-06-04

---

## Overview

Managio is built on **Perfex CRM** (CodeIgniter 3 + HMVC). Multi-tenancy and SaaS features come from the third-party module **Perfect SaaS** (module id: `saas`, version **1.2.4**).

| Item | Value |
|------|--------|
| Module display name | Perfect SaaS - Powerful Multi-Tenancy Module for Perfex CRM |
| Module system name | `saas` (`SaaS_MODULE`) |
| Physical path | `modules/saas/` |
| URL prefix (super-admin panel) | `/saas/...` |
| Stack | PHP 8.1+, CodeIgniter 3.1.11, MX HMVC router |

**Important:** Many SaaS features are **not** under `/saas/` in the browser. Registration, checkout, webhooks, and tenant admin often use **root-level** routes (e.g. `/register`, `/admin/billings`) that still resolve to controllers inside `modules/saas/`.

---

## Where to find it in the repository

There is **no** `saas/` folder at the web root. All SaaS code lives in the Perfex module directory:

```
managio/
├── application/
│   └── config/
│       ├── routes.php          # Core Perfex routes
│       └── my_routes.php       # Includes SaaS routes (see below)
├── index.php                   # Front controller
├── .htaccess                   # Rewrites to index.php
└── modules/
    └── saas/                   # ← Perfect SaaS module (this is /saas/)
        ├── saas.php            # Module bootstrap, hooks, version
        ├── config/
        │   └── my_routes.php   # Custom route map (public + tenant + aliases)
        ├── controllers/        # HMVC controllers → URL segments
        │   ├── frontcms/       # Landing / marketing CMS
        │   └── affiliate/      # Affiliate portal (often /affiliate/...)
        ├── helpers/
        │   └── saas_helper.php # saas_url(), access checks, UI helpers
        ├── models/
        │   └── Saas_model.php  # Sidebar menu URLs (saas_url)
        └── views/
```

### Route file chain

1. Request hits `.htaccess` → `index.php`
2. `application/config/routes.php` loads core routes
3. `application/config/my_routes.php` includes the module file:

```php
require_once(FCPATH . 'modules/saas/config/my_routes.php');
```

4. HMVC resolves `saas/{controller}/{method}` to `modules/saas/controllers/{Controller}.php`

---

## How URL → file mapping works

### HMVC convention (super-admin panel)

Pattern:

```
https://{host}/saas/{controller}/{method}/{args...}
```

| URL example | Controller file | Method |
|-------------|-----------------|--------|
| `/saas/dashboard` | `controllers/Saas.php` | `index()` (via explicit route) |
| `/saas/companies/details/12` | `controllers/Companies.php` | `details(12)` |
| `/saas/frontcms/blogs` | `controllers/frontcms/Blogs.php` | `index()` (default) |
| `/saas/settings/payments` | `controllers/Settings.php` | `payments()` |

Controller class names are **PascalCase**; URL segments are **lowercase** (CodeIgniter convention).

### Official URL helper (super-admin)

Partners should use the same helper as the module UI when building links:

```php
saas_url('companies/details/5');  // → site_url('saas/companies/details/5')
```

Defined in `modules/saas/helpers/saas_helper.php`.

### Admin alias

Some installs also accept **`/admin/saas/...`**. The MX router can strip `admin` when the next segment is a module folder name. Prefer documented `saas_url()` paths for new integrations.

---

## Route categories

```mermaid
flowchart TD
    A[HTTP request] --> B[index.php + CI Router]
    B --> C[application/config/my_routes.php]
    C --> D[modules/saas/config/my_routes.php]
    D --> E{URL prefix?}
    E -->|/saas/...| F[Super-admin HMVC panel]
    E -->|/register, /pricing, ...| G[Public SaaS front - frontcms]
    E -->|/admin/billings, ...| H[Tenant admin - gb_admin]
    E -->|/clients/billings, ...| I[Tenant client area - gb_client]
    E -->|/{company-slug}/...| J[Tenant slug prefix - cloned routes]
```

---

## 1. Routes under `/saas/` (super-admin panel)

These URLs are intended for the **platform operator** (super admin), not end-customer tenants.

### Explicit rewrites (`modules/saas/config/my_routes.php`)

| Public URL | Internal target |
|------------|-----------------|
| `/saas/dashboard` | `saas/index` → `Saas::index` |
| `/saas/payments` | `saas/companies/invoices` → `Companies::invoices` |
| `/saas/module_details/{id}` | `saas/gb_admin/module_details/{id}` |

### Sidebar menu routes (`Saas_model::build_sidebar_menu()`)

Documented first-class paths (built with `saas_url()`):

| Path | Purpose |
|------|---------|
| `/saas/dashboard` | Dashboard |
| `/saas/companies` | Company list |
| `/saas/domain/requests` | Custom domain requests |
| `/saas/domain/settings` | Custom domain settings |
| `/saas/packages` | Subscription packages |
| `/saas/packages/customize` | Custom package builder |
| `/saas/packages/modules` | Per-module pricing |
| `/saas/packages/settings` | Package settings |
| `/saas/payments` | SaaS invoices / payments |
| `/saas/coupons` | Coupon management |
| `/saas/affiliates` | Affiliate program (admin) |
| `/saas/affiliates/users` | Affiliate users |
| `/saas/affiliates/payouts` | Affiliate payouts |
| `/saas/affiliates/settings` | Affiliate settings |
| `/saas/themebuilder` | Theme builder |
| `/saas/frontcms/page` | Front CMS — pages |
| `/saas/frontcms/menus` | Menus |
| `/saas/frontcms/media` | Media library |
| `/saas/frontcms/settings/slider` | Homepage slider |
| `/saas/frontcms/blogs` | Blog posts |
| `/saas/frontcms/creatives` | Creatives |
| `/saas/frontcms/discovers` | Discover section |
| `/saas/frontcms/features` | Features section |
| `/saas/frontcms/abouts` | About section |
| `/saas/frontcms/brands` | Brands |
| `/saas/frontcms/reviews` | Reviews |
| `/saas/frontcms/gallery` | Gallery |
| `/saas/frontcms/settings` | Front CMS settings |
| `/saas/settings` | SaaS platform settings |
| `/saas/faq` | FAQ management |
| `/saas/super_admin` | Super admin users |
| `/saas/api` | API management |

**Note:** Menu entry `custom_domain` points to `saas_url('custom_domain')`; operational sub-pages use `/saas/domain/...` as listed above.

Additional methods on each controller remain reachable via CI convention, e.g. `/saas/companies/companiesList`, `/saas/settings/index/{tab}`.

### Controllers (HMVC — `/saas/{controller}/...`)

**`modules/saas/controllers/` (root)**

| Controller file | Typical URL base |
|-----------------|------------------|
| `Saas.php` | `/saas` (dashboard) |
| `Companies.php` | `/saas/companies` |
| `Packages.php` | `/saas/packages` |
| `Settings.php` | `/saas/settings` |
| `Coupons.php` | `/saas/coupons` |
| `Affiliates.php` | `/saas/affiliates` |
| `Domain.php` | `/saas/domain` |
| `Faq.php` | `/saas/faq` |
| `Super_admin.php` | `/saas/super_admin` |
| `Api.php` | `/saas/api` |
| `Gb.php` | `/saas/gb` (also used from root aliases) |
| `Gb_admin.php` | `/saas/gb_admin` |
| `Gb_client.php` | `/saas/gb_client` |
| `Setup.php` | `/saas/setup` |
| `Builder.php` | `/saas/builder` |
| `Themebuilder.php` | `/saas/themebuilder` |
| `Webhooks.php` | `/saas/webhooks` |

**`modules/saas/controllers/frontcms/`**

| Controller | URL pattern |
|------------|-------------|
| `Home.php` | `/saas/frontcms/home/...` (and many root aliases) |
| `Page.php` | `/saas/frontcms/page` |
| `Menus.php` | `/saas/frontcms/menus` |
| `Media.php` | `/saas/frontcms/media` |
| `Settings.php` | `/saas/frontcms/settings` |
| `Blogs.php`, `Creatives.php`, `Discovers.php`, `Features.php`, `Abouts.php`, `Brands.php`, `Reviews.php`, `Gallery.php` | `/saas/frontcms/{name}` |

**`modules/saas/controllers/affiliate/`**

Used for the **public** affiliate portal; routes are usually prefixed with `/affiliate/` (see section 2), not `/saas/affiliate/`.

---

## 2. Related routes **without** `/saas/` prefix

Same module, different URL surface — critical for partners building signup, billing, or tenant flows.

### Public marketing & signup (root)

| URL | Controller target |
|-----|-------------------|
| `/`, `/home` | `saas/frontcms/home/index` (when not on tenant subdomain) |
| `/register`, `/register/{ref}` | `saas/frontcms/home/register` |
| `/pricing` | `saas/frontcms/home/page/pricing` |
| `/front`, `/front/{page}` | Front CMS pages |
| `/privacy`, `/tos` | Legal pages |
| `/find-my-company` | Company lookup |
| `/theme/...`, `/preview/...` | Theme preview |
| `/setup` | Initial SaaS setup wizard |
| `/checkout`, `/checkout/{id}` | Checkout flow |

### Payments & subscriptions (root)

| URL | Controller target |
|-----|-------------------|
| `/proceedPayment`, `/stripePayment`, `/paymentSuccess`, `/paymentCancel` | `saas/gb/...` |
| `/package_details/{id}` | `saas/gb/package_details` |
| `/check_coupon_code` | `saas/gb/check_coupon_code` |
| `/webhooks/{provider}` | `saas/webhooks/{provider}` |

### Tenant company admin (`/admin/...` → SaaS controllers)

| URL | Controller target |
|-----|-------------------|
| `/admin/billings` | `saas/gb_admin/billings` |
| `/admin/custom_domain` | `saas/gb_admin/custom_domain` |
| `/admin/updatePackage` | `saas/gb_admin/assignPackage` |
| `/admin/module_details/{id}` | `saas/gb_admin/module_details` |
| `/admin/themebuilder` | `saas/builder` |
| `/login_as_companies` | `saas/gb_admin/login_as_companies` |

On **tenant subdomains**, `admin/dashboard` may route to `saas/gb_admin/billings` instead of the default Perfex dashboard.

### Tenant client area (`/clients/...`)

| URL | Controller target |
|-----|-------------------|
| `/clients/billings`, `/clients/dashboard` | `saas/gb_client/billings` |
| `/clients/custom_domain` | `saas/gb_client/custom_domain` |
| `/clients/updatePackage` | `saas/gb_client/assignPackage` |

### Affiliate portal (`/affiliate/...`)

| URL | Controller target |
|-----|-------------------|
| `/affiliate`, `/affiliate-program` | Marketing / program pages |
| `/affiliate/dashboard` | `saas/affiliate/dashboard` |
| `/affiliate/commissions`, `/payouts`, `/referrals`, `/settings` | Affiliate dashboard actions |
| `/affiliate/verify/{token}` | `saas/affiliate/auth/verify` |

---

## 3. Multi-tenant URL prefix (company slug)

When `check_subdomain()` returns a company slug, `my_routes.php` **clones every static route** with a leading slug segment:

```
/{company-slug}/register
/{company-slug}/admin/billings
/{company-slug}/saas/dashboard
...
```

Catch-all rules (up to 7 URI segments) are also registered under `/{company-slug}/...` for deep links.

Partners integrating per-tenant links must:

1. Resolve the tenant slug (subdomain or path prefix, depending on deployment).
2. Prefix paths accordingly, or use absolute URLs generated by Managio helpers.

---

## 4. Default front controller behaviour

From `modules/saas/config/my_routes.php` (simplified):

| Condition | Default controller |
|-----------|-------------------|
| Main domain, URI not `/clients` or `/login` | `saas/frontcms/home/index` |
| Tenant subdomain, URI not `/clients` or `/login` | `saas/frontcms/home/client` |
| URI starts with `/clients` or `/login` | Standard Perfex `clients` area |

This is why the marketing site and tenant landing pages may not show `/saas/` in the address bar even though the code lives under `modules/saas/`.

---

## 5. Integration guidelines for partners

1. **Do not patch core Perfex** for SaaS routing — extend via `modules/saas/` or a separate bridge module (e.g. `wekonex_bridge`).
2. **Super-admin links:** use `saas_url('...')` or paths documented in section 1.
3. **Tenant / billing flows:** use section 2 root routes (`/admin/billings`, `/register`, webhooks).
4. **API / SSO work:** prefer PerfexGo REST + your bridge module; this document covers **web UI routing** only.
5. **New routes:** add rules to `modules/saas/config/my_routes.php` (or a small wrapper included from `application/config/my_routes.php`) to survive module updates — avoid editing vendor files when possible; document overrides in your fork.

---

## 6. Quick reference — files to open first

| Task | File |
|------|------|
| Add or change a URL | `modules/saas/config/my_routes.php` |
| Super-admin menu URLs | `modules/saas/models/Saas_model.php` → `build_sidebar_menu()` |
| Build admin links in PHP | `modules/saas/helpers/saas_helper.php` → `saas_url()` |
| Business logic / views | `modules/saas/controllers/*.php` |
| Module version & hooks | `modules/saas/saas.php` |
| Global route include | `application/config/my_routes.php` |

---

## Related internal docs

| Document | Location |
|----------|----------|
| Managio ↔ Wekonex integration phases | `docs/INTEGRATION-WEKONEX-PHASES.md` |
| Wekonex-side mirror | `wekonex/docs/INTEGRATION-MANAGIO-PHASES.md` |

---

## Document history

| Date | Change |
|------|--------|
| 2026-06-04 | Initial partner routing reference (Perfect SaaS 1.2.4 on Managio / Perfex 3.4) |
