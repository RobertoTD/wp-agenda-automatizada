# Admin UI Architecture

## Overview

This directory contains the admin UI layer that renders inside an iframe. This is **UI only** - no business logic.

## Directory Structure

```
ui/
├── index.php              # Entry point & router
├── shared/                # Reusable components
│   ├── layout.php         # Main HTML structure
│   ├── header.php         # Navigation header
│   └── footer.php         # Footer
├── modules/               # UI modules
│   ├── settings/          # Settings module
│   ├── services/          # Services module
│   └── calendar/          # Calendar module
└── assets/                # Static assets
    ├── css/
    │   └── admin.css      # Tailwind CSS entry point
    └── js/
        └── main.js        # Shared JavaScript
```

## How It Works

1. **Entry Point** (`index.php`): Routes to modules based on `?module=` query parameter
2. **Layout** (`shared/layout.php`): Provides HTML structure, loads CSS/JS
3. **Modules**: Each module has its own `index.php` (view) and `module.js` (module-specific JS)
4. **Assets**: Shared CSS (Tailwind) and JS utilities

## Adding a New Module

1. Create folder: `modules/your-module/`
2. Add `index.php` with your module's HTML
3. Add `module.js` for module-specific JavaScript
4. Add navigation link in `shared/header.php`

## Important Notes

- **No business logic** in this layer
- All data operations happen via AJAX to WordPress endpoints
- Use Tailwind CSS classes for styling
- Keep modules isolated and independent

