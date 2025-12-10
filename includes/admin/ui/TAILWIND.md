# Tailwind CSS Setup for Admin UI

## Overview

Tailwind CSS is configured **only** for the iframe-based admin UI located in `includes/admin/ui/`.

The iframe is completely isolated from WordPress admin styles, so Tailwind can safely use its preflight (CSS reset) without affecting WordPress.

## Installation

1. Install dependencies:
```bash
npm install
```

## Build Commands

### Development (Watch Mode)
Watch for changes and automatically rebuild CSS:
```bash
npm run watch:css
```

### Production Build
Build minified CSS for production:
```bash
npm run build:css
```

## Configuration

- **Config file**: `tailwind.config.js` (root of plugin)
- **CSS entry**: `includes/admin/ui/assets/css/admin.css`
- **Content scanning**: Only scans files in `includes/admin/ui/**/*.php` and `includes/admin/ui/**/*.js`

## How It Works

1. Tailwind scans PHP and JS files in the `includes/admin/ui/` directory
2. It extracts all Tailwind utility classes used in those files
3. It generates CSS with only the classes that are actually used
4. The compiled CSS is written back to `admin.css`
5. The iframe loads this CSS file (already configured in `layout.php`)

## Important Notes

- **Scope**: Tailwind ONLY affects the iframe UI. It does NOT affect WordPress admin or frontend.
- **Isolation**: The iframe is a separate HTML document, so Tailwind's preflight is safe to use.
- **Mobile-first**: Tailwind uses mobile-first breakpoints by default.
- **No prefix needed**: Since it's isolated in an iframe, no prefix is required.

## Usage in PHP Files

Use Tailwind classes directly in your PHP templates:

```php
<div class="bg-white rounded-lg shadow-sm p-6">
    <h2 class="text-2xl font-semibold text-gray-800 mb-6">Title</h2>
    <button class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
        Click me
    </button>
</div>
```

## Usage in JavaScript

You can also use Tailwind classes in dynamically generated HTML:

```javascript
const element = document.createElement('div');
element.className = 'p-4 bg-gray-50 rounded-md';
```

## Custom Styles

Add custom CSS after the Tailwind directives in `admin.css`:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

/* Your custom styles here */
.custom-component {
    /* ... */
}
```

