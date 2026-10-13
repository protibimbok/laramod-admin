# Expose A New Slot

Use this when the admin's layout should get a spot that modules can fill, the way `HeaderActions` and `DashboardWidgets` are. This changes this package.

1. Create the slot in `resources/js/layout/` with `createSlot('name')` from `lib/slot.tsx`, in a file of its own, and export it from `resources/js/index.ts`. The name is what the error message shows when a fill is used outside the provider.

2. Wrap the layout in the slot's `Provider`, in `resources/js/layout/Layout.tsx` or the page that owns the spot, and render its `Outlet` where the content should appear. The provider must wrap both the outlet and every page or fill that may fill it.

3. Decide where modules fill it. A slot on a page of the module itself is filled from that page. A slot that is shown while other modules' pages are open, like the dashboard, is filled from the `fills` of the registration. Say which in the slot's JSDoc.

4. Add a vitest in `tests/js` that renders the provider, outlet and a fill and asserts the content lands in the outlet. Run `pnpm types` and `pnpm test`.

5. Document the export in the `@admin/js` table of `README.md` and in the public surface of `ai-workflow/README.md`. From then on it is public and may not change shape.
