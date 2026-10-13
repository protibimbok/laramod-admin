# Add A Dashboard Widget

Use this when a module should show a card on the admin's dashboard. The dashboard has nothing of its own, it is the `DashboardWidgets` slot the modules fill, laid out in a grid of up to three columns in the order of the modules.

1. The module already has a dashboard script, see `add-pages-from-a-module.md`. A module may have widgets without pages, the script and the contract are the same.

2. Write the widget as a component in the module, fetching its data from the module's API routes. `<DashboardWidgets.Fill>` renders nothing until the dashboard is shown, so a widget that loads data only does so there.

3. Put the fill into the `fills` of the registration, not into a page. No page of the module is shown on the dashboard, and only `fills` stays rendered whatever page is open:

   ```tsx
   import { DashboardWidgets, registerModule } from '@admin/js'
   import { PostsCard } from './widgets/PostsCard'

   registerModule({
     id: 'blog',
     fills: (
       <DashboardWidgets.Fill>
         <PostsCard />
       </DashboardWidgets.Fill>
     ),
   })
   ```

4. To move the module's widgets before or after the others, set `order` on the registration: `0` and up counts from the start, `-1` is last.

5. Open the admin's dashboard to see it. The widget takes one grid cell, use the application's `@/components/ui/*` for its card.
