# laramod-admin

A React and shadcn/ui admin, as a [laramod](https://github.com/protibimbok/laramod) module.

## Requirements

- PHP 8.3+
- Laravel 13 with [laramod](https://github.com/protibimbok/laramod) 0.1.1+
- [laramod-vite-plugin](https://github.com/protibimbok/laramod-vite-plugin), it is what puts the admin and the dashboard scripts of your modules into the application's build
- [pnpm](https://pnpm.io) and Tailwind CSS 4, the way a new Laravel application sets it up
- TypeScript, see below

## Installation

```bash
composer require protibimbok/laramod-admin
```

Composer asks whether to trust the package's plugin, see [After it is installed](#after-it-is-installed). Answer yes, or allow it yourself:

```bash
composer config allow-plugins.protibimbok/laramod-admin true
```

That is the whole installation: the plugin lists the module in `bootstrap/modules.php` and runs `php artisan admin:init`.

```php
use Laramod\Admin\AdminModule;

return [
    // ...
    AdminModule::class,
];
```

The package is a module like any other, there is no service provider to discover, and the list stays yours: move the module, or register it from a service provider instead, and it is never listed a second time.

`admin:init` sets up the frontend the admin is built with. Every step is skipped when it is already done, so the command is safe to run again:

| Step | Skipped when |
| --- | --- |
| `pnpm install` | `node_modules` exists |
| `pnpm add react react-dom` and `pnpm add -D @vitejs/plugin-react @types/react @types/react-dom` | `package.json` lists them |
| `pnpm dlx shadcn@latest init --template laravel --defaults` | `components.json` exists |
| `pnpm add react-router@^7 @base-ui/react class-variance-authority cn lucide-react` | `package.json` lists them |
| copies the shadcn/ui components the admin is written against into `resources/js/components/ui` and `resources/js/hooks` | the component is there and is the same |
| adds `@source '../../vendor/protibimbok/laramod-admin/resources/js';` to the stylesheet `components.json` names | the line is there |

The application has one copy of every component: the admin, your modules' pages and your own code import the same `@/components/ui/button`, so a component that holds state, like the sidebar, is never there twice. A component you have changed is kept and reported, `php artisan admin:init --force` overwrites it with the admin's. What `shadcn init` has just written is replaced without asking.

### TypeScript comes first

TypeScript is the application's own to set up. Without `typescript` in `package.json`, a `tsconfig.json` and the Vite configuration in `vite.config.ts`, the command fails before it touches anything and lists what to set up first:

```bash
pnpm add -D typescript
mv vite.config.js vite.config.ts
```

```json
{
    "compilerOptions": {
        "target": "ESNext",
        "module": "ESNext",
        "moduleResolution": "bundler",
        "jsx": "react-jsx",
        "strict": true,
        "noEmit": true,
        "skipLibCheck": true,
        "isolatedModules": true,
        "types": ["vite/client"],
        "paths": {
            "@/*": ["./resources/js/*"]
        }
    },
    "include": ["resources/js/**/*.ts", "resources/js/**/*.tsx"]
}
```

shadcn/ui needs the `@/*` alias and refuses to set itself up without it.

### Vite

`vite.config.ts` is your own code and is never edited, the command tells you to add the React plugin:

```ts
import laramod from 'laramod-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [laramod({ /* ... */ }), react(), tailwindcss()],
});
```

### After it is installed

The package is a Composer plugin. When Composer has installed the package, at the end of that run, it:

1. asks `laramod:list` whether the application has registered `Laramod\Admin\AdminModule`, and lists it in `bootstrap/modules.php` when it has not, the way `make:module` lists a new module: as text, the import sorted in, the rest of the file as you wrote it,
2. runs `php artisan admin:init`.

A fresh clone is set up by `composer install` alone, and the application's `composer.json` has no script for it.

- When the module cannot be listed, `bootstrap/modules.php` is missing or does not end in a plain `];`, the plugin tells you to list it and to run the command yourself.
- What the command reports is for you to read. It never fails Composer, not even when TypeScript is missing.
- An update of the package, `--no-scripts`, `--no-plugins` and a plugin that was not allowed all leave the command to you: `php artisan admin:init`.

## The admin

The admin answers at `/admin`, for everyone who is signed in. Both are configuration, `php artisan vendor:publish --tag=admin-config` copies it to `config/modules/admin.php`:

```php
return [
    'path' => 'admin',
    'middleware' => ['auth'],
];
```

- `path`: the admin answers every URL beneath it and leaves what comes after it to its own router, so a refresh on `/admin/posts/12` works. A route of yours beneath the same path, `admin/export` for example, still takes precedence, also when the routes are cached.
- `middleware`: applied on top of the `web` group. `auth` lets every signed in user in, so add what decides who is an admin, such as `can:access-admin`.

The admin has no sign in and no sign out of its own, they are the application's:

- `auth` sends a guest to the route named `login`. An application without one, a new Laravel application without a starter kit for example, answers with `Route [login] not defined.` instead: add the route, or change `middleware`.
- "Sign out" in the viewer's menu is there when the application has a route named `logout`. It is a form post with the CSRF token, where the viewer lands afterwards is that route's call.

The page the admin mounts in loads `resources/css/app.css`, the stylesheet of a new Laravel application and the one `admin:init` has added the `@source` line to. For another stylesheet, publish the view with `--tag=admin-views` and change it there.

A fresh install shows one page, an empty dashboard. Everything else comes from your modules, what is on the dashboard included.

## Pages of a module

A module adds its pages to the admin with a dashboard script: one of its Vite entries, that the admin loads before it mounts.

```php
use Laramod\Admin\Contracts\ProvidesDashboardScripts;
use Laramod\Contracts\Module;
use Laramod\Contracts\ProvidesViteEntries;

class BlogModule implements Module, ProvidesDashboardScripts, ProvidesViteEntries
{
    // ...

    public function viteEntries(): array
    {
        return ['resources/js/dashboard.tsx'];
    }

    public function dashboardScripts(): array
    {
        return ['resources/js/dashboard.tsx'];
    }
}
```

The script is listed twice on purpose: `viteEntries()` is what gets it built, `dashboardScripts()` is what the admin loads. A script that is not a Vite entry fails the page and says what to add: `Dashboard script [resources/js/dashboard.tsx] is not declared by [Modules\Blog\BlogModule::viteEntries()].`

The script registers the module, at its top level:

```tsx
// Modules/Blog/resources/js/dashboard.tsx
import { FileTextIcon } from 'lucide-react'
import { HeaderActions, registerModule } from '@admin/js'
import { Button } from '@/components/ui/button'

function Posts() {
  return (
    <>
      <HeaderActions.Fill>
        <Button>New post</Button>
      </HeaderActions.Fill>

      <h1>All posts</h1>
    </>
  )
}

registerModule({
  id: 'blog',
  menuFilter: (menu) => [
    ...menu,
    { label: 'Blog', items: [{ label: 'Posts', icon: FileTextIcon, path: '/blog' }] },
  ],
  routes: [{ path: 'blog', element: <Posts /> }],
})
```

`@admin` is the alias laramod-vite-plugin gives every module, the admin included, and `@/components/ui/*` are the application's components, the same copies the admin uses. There is one build, so the admin and every dashboard script share one React and one registry, nothing is a global.

`registerModule()` takes:

| | |
| --- | --- |
| `id` | Unique, a second registration of the same id throws |
| `order` | Where the module comes among the others. `0` and up counts from the start, negative from the end: `-1` is last. Modules with the same order keep the order of `bootstrap/modules.php`. Default `0` |
| `menuFilter` | `(menu, viewer) => menu`. Receives the menu the modules before it have built and returns the one it wants: append a group, add an item to a group of another module, or take away what this viewer should not see. `viewer` is `null` for a guest |
| `routes` | [React Router](https://reactrouter.com) route objects, rendered inside the layout. Paths are relative to the admin's path, `children` work |
| `fills` | Rendered for as long as the admin is, whatever page is shown. It is where the module fills the slots that are not on a page of its own, see [Widgets on the dashboard](#widgets-on-the-dashboard) |

- A menu item's `icon` is a [lucide](https://lucide.dev) icon, its `path` starts with `/` and is relative to the admin's path too. The item that is lit up is the deepest one that covers the page, so `/blog/drafts/12` lights up "Drafts" and not "Posts".
- No module owns `/`: it lands on the first item of the menu the viewer is offered.
- Dashboard scripts run in the order of the modules, the admin's own entry runs last and closes the registry. A `registerModule()` that comes later, from a `setTimeout` or after an `await`, would never show up, so it throws instead.
- The menu decides what is shown, not what is allowed. Who may do what is decided by the routes of your module, on the server.

`@admin/js` exports:

| | |
| --- | --- |
| `registerModule` | See above, with the types `ModuleRegistration`, `MenuGroup`, `MenuItem` and `MenuFilter` |
| `DashboardWidgets` | The slot of the dashboard, see [Widgets on the dashboard](#widgets-on-the-dashboard) |
| `HeaderActions` | The slot at the top right of the header. A page renders `<HeaderActions.Fill>` and what is inside shows up there for as long as the page is mounted |
| `createSlot` | Makes a slot of your own: `Provider` wraps both ends, `Outlet` is the spot, `Fill` places content into it. Type `Slot` |
| `viewer` | Who is looking: `id`, `name` and `email`, or `null` for a guest. Type `Viewer` |
| `basePath` | Where the admin lives, `/admin`. Links inside the admin do not need it, a link from outside does |

### Widgets on the dashboard

The dashboard has nothing of its own, it is a slot the modules fill. A page can only fill a slot while it is shown, and no page of your module is shown on the dashboard, so the fill goes into the `fills` of the registration, which the layout keeps rendered:

```tsx
import { DashboardWidgets, registerModule } from '@admin/js'

registerModule({
  id: 'blog',
  fills: (
    <DashboardWidgets.Fill>
      <PostsCard />
      <DraftsCard />
    </DashboardWidgets.Fill>
  ),
  // ...
})
```

The widgets are laid out in a grid of up to three columns, in the order of the modules. `<DashboardWidgets.Fill>` renders nothing until the dashboard is shown, so a widget that loads data only does so there.

### TypeScript

The `tsconfig.json` above only covers `resources/js`. For the dashboard scripts to be type-checked, add your modules to it:

```json
"include": ["resources/js/**/*.ts", "resources/js/**/*.tsx", "Modules/*/resources/js/**/*.ts", "Modules/*/resources/js/**/*.tsx"]
```

laramod-vite-plugin writes the `@admin/*` and `@blog/*` paths into `tsconfig.json` itself. Tailwind needs nothing: it finds the classes in `Modules/` on its own, only what is in `vendor/` has to be pointed out to it, which is what the `@source` line of `admin:init` is for.

### Scripts from elsewhere

A script that is not part of the build, a chart library from a CDN for example, is loaded from where it is by a second contract:

```php
use Laramod\Admin\Contracts\ProvidesDashboardScriptUrls;

public function dashboardScriptUrls(): array
{
    return ['https://cdn.example.com/chart.js'];
}
```

Such a script cannot import `@admin/js`, so it cannot register anything: pages always come from a dashboard script.

### Known issue

A module that lives outside the application, a Composer path repository that is symlinked in for example, is not handled by laramod and laramod-vite-plugin yet: its entries get absolute names in the manifest, Vite's `server.fs.allow` does not cover it and its bare imports are resolved from the real path. Until that is fixed, develop this package, or a module of your own that ships a dashboard script, with `"options": {"symlink": false}` on the path repository.

## Testing

```bash
composer test
composer lint

pnpm install
pnpm types
pnpm test
```

The TypeScript is checked and tested against the components in `stubs/`, the versions `admin:init` copies.

## License

MIT
