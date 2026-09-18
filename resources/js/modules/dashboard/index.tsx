import { GaugeIcon } from 'lucide-react'

import { DashboardWidgets } from '../../layout/DashboardWidgets'
import { registerModule } from '../../lib/modular'

function DashboardPage() {
  return (
    <>
      <h1 className="text-2xl font-semibold tracking-tight">Dashboard</h1>

      <DashboardWidgets.Outlet className="peer grid gap-4 empty:hidden sm:grid-cols-2 lg:grid-cols-3" />

      {/* Shown for as long as no module has filled the outlet above. */}
      <div className="hidden flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed p-6 text-center peer-empty:flex">
        <GaugeIcon className="size-8 text-muted-foreground" />
        <p className="max-w-md text-sm text-muted-foreground">
          Nothing here yet. A module puts its widgets on this page with{' '}
          <code className="font-mono text-foreground">{'<DashboardWidgets.Fill>'}</code>, from the{' '}
          <code className="font-mono text-foreground">fills</code> of its registration.
        </p>
      </div>
    </>
  )
}

registerModule({
  id: 'dashboard',
  menuFilter: (menu) => [
    ...menu,
    {
      label: 'Overview',
      items: [{ label: 'Dashboard', icon: GaugeIcon, path: '/dashboard' }],
    },
  ],
  routes: [{ path: 'dashboard', element: <DashboardPage /> }],
})
