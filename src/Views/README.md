# Cartly Views Structure

## Overview

The `/src/Views` folder is organized into **core** (shared, non-theme) and **themes** (storefront variants) with intelligent theme fallback.

---

## Directory Structure

```
src/Views/
├── core/                          # Shared, non-theme views
│   ├── landing/                   # Public landing page (root domain)
│   │   ├── home.twig             # Landing page content
│   │   └── layout.twig           # Landing page layout
│   ├── admin/                     # Admin dashboard (Root + Store Admin)
│   │   ├── dashboard/             # Root dashboard page(s)
│   │   ├── packages/              # Packages CRUD
│   │   ├── shops/                 # Shops CRUD
│   │   ├── subscriptions/         # Subscription management
│   │   ├── layouts/               # Admin layouts
│   │   │   └── dashboard.twig     # Admin base layout
│   │   └── partials/              # Admin partials
│   │       ├── sidebar.twig
│   │       └── topbar.twig
│   ├── auth/                      # Authentication pages
│   │   └── login.twig            # Admin login
│   ├── layouts/                   # Shared layouts (non-admin)
│   └── errors/                    # Error pages (404, 500, etc.)
│
├── themes/                        # Storefront themes
│   ├── default/                   # Default/fallback theme
│   │   ├── layout.twig           # Main storefront layout
│   │   ├── home.twig             # Home page
│   │   ├── pages/                # Page views
│   │   │   └── home.twig
│   │   ├── partials/             # Theme-specific partials
│   │   │   ├── header.twig
│   │   │   └── footer.twig
│   │   ├── assets/               # Theme assets (images, icons)
│   │   └── theme.json            # Theme metadata (colors, features)
│   ├── classic/                   # Classic theme variant
│   │   ├── layout.twig
│   │   ├── home.twig
│   │   ├── pages/
│   │   ├── partials/
│   │   ├── assets/
│   │   └── theme.json
│   └── modern/                    # Modern theme variant
│       ├── layout.twig
│       ├── home.twig
│       ├── pages/
│       ├── partials/
│       ├── assets/
│       └── theme.json
│
└── (top-level files removed — all views organized above)

public/assets/landing/             # Public landing page assets (images, CSS, JS)
```

---

## Theme Resolution Strategy

### Storefront (Shop Tenant)
Looks for themes in this order:
1. `/themes/<active_theme>/{file}` (e.g., `/themes/classic/pages/home.twig`)
2. `/themes/default/{file}` (fallback)
3. `/core/` (rarely used, for shared code)

**Example:**
- Request for `pages/home.twig` with active theme = "classic"
  1. Try `/themes/classic/pages/home.twig` ✓ Found → Use it
  2. If missing, try `/themes/default/pages/home.twig`
  3. If missing, try `/core/pages/home.twig`

### Admin Dashboard (Root + Store Admin)
Looks only in:
- `/core/admin/` (dashboard pages)
- `/core/admin/layouts/` (base admin layout)
- `/core/admin/partials/` (admin partials)
- `/core/auth/` (login pages)

**Note:** Admin UI is **not themed**—same interface for all tenants.

### Landing Page (Root Domain, Public)
Looks only in:
- `/core/landing/` (no theme variants)

---

## Theme Configuration

Each theme has a `theme.json` file defining:
- **Colors:** Primary, secondary, success, danger, warning, info, light, dark
- **Features:** Enable/disable functionality (search, reviews, wishlists, etc.)
- **Fonts:** Primary and secondary font stacks

### Example: `themes/classic/theme.json`
```json
{
  "name": "Classic Theme",
  "version": "1.0.0",
  "description": "Traditional e-commerce theme",
  "colors": {
    "primary": "#8b5a3c",
    "secondary": "#a0826d",
    "success": "#6fa876",
    ...
  },
  "features": {
    "enable_search": true,
    "enable_reviews": true,
    "enable_wishlists": true,
    ...
  },
  "fonts": {
    "primary": "'Segoe UI', Tahoma, sans-serif",
    "secondary": "'Courier New', monospace"
  }
}
```

### Tenant Customization
Tenants **do not override themes**. Instead, tenant settings in the database override theme.json defaults:
- Database stores custom colors, enabled features, fonts
- Twig views read from `$tenant->theme_settings` (or similar)
- This allows all tenants to share the same themes while maintaining unique branding

---

## Usage in Controllers

### Rendering Storefront Pages
```php
// ThemeMiddleware resolves the active theme from the current shop.
// Twig will automatically resolve /themes/<active>/pages/home.twig
return $this->view->render($response, 'pages/home.twig', [
    'products' => $products,
]);
```

### Rendering Admin Pages
```php
// No theme context needed—always uses /core/admin/
return $this->view->render($response, 'admin/dashboard/root.twig', [
    'stats' => $stats,
]);
```

### Rendering Landing Page
```php
// Landing page: /core/landing/home.twig
return $this->view->render($response, 'home.twig', [
    'plans' => $plans,
]);
```

---

## ThemeResolver

The `App\Services\ThemeResolver` handles:
- **Theme resolution** with fallback chain
- **Loading theme.json** config files
- **Getting available themes** list
- **Providing theme asset paths**

### Methods
```php
$themeResolver = new ThemeResolver(__DIR__ . '/../Views');

// Get available themes
$themes = $themeResolver->getAvailableThemes();

// Load theme metadata (colors, features, fonts)
$config = $themeResolver->getThemeMetadata('classic');

// Asset base path for current theme
$assets = $themeResolver->getThemeAssetPath();
```

---

## Middleware Integration

Theme resolution is automatic:
- `ShopResolverMiddleware` attaches the current shop to the request.
- `ThemeMiddleware` reads the shop + context and configures Twig paths.

---

## Adding a New Theme

1. Create directory: `src/Views/themes/my-theme/`
2. Add structure:
   ```
   my-theme/
   ├── layout.twig
   ├── home.twig
   ├── pages/
   ├── partials/
   ├── assets/
   └── theme.json
   ```
3. Define `theme.json` with colors, features, fonts
4. Reference in shop: `$shop->theme = 'my-theme'`

---

## Adding a New Admin Page

1. Create in: `src/Views/core/admin/{area}/{page}.twig`
2. Use layout: `{% extends "layouts/dashboard.twig" %}`
3. Render:
   ```php
   return $this->view->render($response, '{page}.twig', $data);
   ```

---

## Tenant Customization Pattern

Since themes aren't overridable per-tenant, use this pattern:

```twig
{# Theme view #}
<div style="color: {{ tenant.theme_colors.primary ?? theme_config.colors.primary }}">
  {% if tenant.theme_features.enable_reviews ?? theme_config.features.enable_reviews %}
    <reviews-section />
  {% endif %}
</div>
```

Where `tenant.theme_colors` and `tenant.theme_features` come from the database, overriding `theme_config` defaults.

---

## Asset Paths

### Landing Assets
- Location: `/public/assets/landing/`
- Reference: `<img src="/assets/landing/logo.png">`

### Theme Assets
- Location: `/src/Views/themes/<theme>/assets/`
- Reference: May be served via manifest or symlink to `/public/assets/themes/<theme>/`

---

## Error Handling

If a theme is missing:
1. Default fallback activates: `/themes/default/{file}`
2. If still missing, Twig error is thrown
3. Admin/landing pages will error if core files are missing (by design—no fallback)

This ensures:
- ✅ Storefront always has a working page (default theme)
- ✅ Admin/landing pages must exist and be properly maintained
- ✅ Theme variants can override selectively

---

## Summary

| Context | Lookup Path | Fallback |
|---------|-------------|----------|
| **Storefront** | `themes/<active>` → `themes/default` | ✓ Default theme acts as fallback |
| **Admin** | `core/admin` + `core/layouts` + `core/partials` | ✗ No fallback—must exist |
| **Landing** | `core/landing` | ✗ No fallback—must exist |

This structure enables:
- 🎨 Multiple theme variants for storefronts
- 👥 Shared admin interface across all tenants
- 🔒 Consistent landing page experience
- 📊 Database-driven customization (colors, features) per tenant
- 🛡️ Safe fallback for incomplete theme implementations
