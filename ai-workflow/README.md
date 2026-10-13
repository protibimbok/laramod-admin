# Admin Module

Entry point for AI agents. Read this before changing the module or using its classes from elsewhere.

## Purpose

A React and shadcn/ui admin that is empty by itself: one page at the configured path, for everyone the configured middleware lets in. Every page, menu item and dashboard widget comes from the application's other modules, registered from a dashboard script they ship.

The module is not an authentication system, an authorization system or a component library. Sign in, sign out and who counts as an admin are the application's. The shadcn/ui components are the application's own copies in `resources/js/components/ui`, the admin only imports them.

## Public Surface

What the application and other modules may rely on. Everything else is internal and may change.

- `Laramod\Admin\Contracts\ProvidesDashboardScripts::dashboardScripts()`: the Vite entries of a module the admin loads before it mounts, relative to the module. Each one must also be in the module's `viteEntries()`, otherwise the page fails and names the script.
- `Laramod\Admin\Contracts\ProvidesDashboardScriptUrls::dashboardScriptUrls()`: scripts loaded from a URL, outside the build. They cannot import `@admin/js`, so they register nothing.
- `@admin/js`, the one import of a dashboard script: `registerModule()` with the types `ModuleRegistration`, `MenuGroup`, `MenuItem` and `MenuFilter`; the slots `DashboardWidgets` and `HeaderActions`; `createSlot()` with the type `Slot`; `viewer` with the type `Viewer`; `basePath`.
- Config keys `modules.admin.path` and `modules.admin.middleware`, published with `vendor:publish --tag=admin-config`.
- The view `admin::app`, published with `vendor:publish --tag=admin-views`. The markup the admin needs from it is `<div id="admin" data-boot="...">` and the `@vite` call with the entries.
- The route named `admin`, a fallback that covers every URL beneath the path. A route of the application beneath the same path wins.
- `php artisan admin:init`, safe to run again, `--force` overwrites changed components.

Nothing else is public: the layout components, `lib/modular.ts` beyond `registerModule()`, `lib/boot.ts` beyond `viewer` and `basePath`, and `Laramod\Admin\DashboardScripts` are internal.

## Conventions

- `registerModule()` is called at the top level of the dashboard script, once per module, with a unique `id`. A registration after the admin has started throws. Pages come from `routes`, the menu from `menuFilter`, and anything that must stay rendered on every page from `fills`.
- Menu and route paths are relative to the admin's path. A menu item's `path` starts with `/`, a route's `path` does not.
- The menu decides what is shown, never what is allowed. Authorization is on the server, in the routes of the module that owns the data.
- A page's own fills, `<HeaderActions.Fill>` for example, are rendered inside the page. Fills for slots no page of the module owns, `<DashboardWidgets.Fill>`, go into the `fills` of the registration.
- Import components as `@/components/ui/*`, never from this package. There is one copy of every component and one React in the application.
- Inside this package, the TypeScript is checked and tested against `stubs/`, which holds the versions `admin:init` copies. A component the admin starts to use is added to `stubs/` and to the list the command copies.
- No sign in, sign out, user model or permission check is added to this module. Read what the application hands over in `data-boot` and nothing more.
- PHP changes come with a feature test in `tests/Feature`, TypeScript changes with a vitest in `tests/js`. Run `composer test`, `pnpm types` and `pnpm test`.

## Workflows

Step-by-step guides for recurring tasks live in `workflows/*.md`:

- `add-pages-from-a-module.md`: give a module of the application pages and a menu entry in the admin.
- `add-a-dashboard-widget.md`: show something from a module on the dashboard.
- `expose-a-new-slot.md`: add a spot in the admin's layout that modules can fill.
