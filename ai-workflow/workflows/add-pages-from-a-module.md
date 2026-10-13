# Add Pages From A Module

Use this when a module of the application, `Modules/Blog` for example, needs pages and a menu entry in the admin. The work is in that module, not in this package.

1. Make the module class implement `Laramod\Admin\Contracts\ProvidesDashboardScripts` next to `Laramod\Contracts\ProvidesViteEntries`. Return the same script from both, relative to the module:

   ```php
   public function viteEntries(): array
   {
       return ['resources/js/dashboard.tsx'];
   }

   public function dashboardScripts(): array
   {
       return ['resources/js/dashboard.tsx'];
   }
   ```

2. Create `resources/js/dashboard.tsx` in the module. Put the pages in files next to it and register at the top level:

   ```tsx
   import { FileTextIcon } from 'lucide-react'
   import { registerModule } from '@admin/js'
   import { Posts } from './pages/Posts'

   registerModule({
     id: 'blog',
     menuFilter: (menu) => [
       ...menu,
       { label: 'Blog', items: [{ label: 'Posts', icon: FileTextIcon, path: '/blog' }] },
     ],
     routes: [{ path: 'blog', element: <Posts /> }],
   })
   ```

   The menu item's `path` starts with `/`, the route's `path` does not. Nested pages use React Router `children`. A page puts buttons in the header with `<HeaderActions.Fill>` from `@admin/js`.

3. Hide what a viewer may not see in `menuFilter`, using its second argument `viewer`, and enforce it on the server in the module's own routes. The admin only decides what is listed.

4. Data for the pages comes from the module's own API routes, `routes/api.php` with `ProvidesApiRoutes`. The admin has no data layer of its own.

5. If `tsconfig.json` does not include `Modules/*/resources/js/**/*.tsx`, add it, otherwise the script is not type-checked.

6. Run `pnpm build` or the dev server in the application and open the admin. A script missing from `viteEntries()` fails the page with `Dashboard script [...] is not declared by [...]`, a second `registerModule()` with the same `id` throws in the browser console.

7. Record the pages and the menu entry in that module's own `ai-workflow/README.md`, not in this one.
