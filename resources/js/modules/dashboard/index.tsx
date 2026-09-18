import { GaugeIcon } from 'lucide-react'

import { registerModule } from '../../lib/modular'

function DashboardPage() {
  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed p-6 text-center">
      <GaugeIcon className="size-8 text-muted-foreground" />
      <h1 className="text-lg font-semibold">Dashboard</h1>
      <p className="max-w-md text-sm text-muted-foreground">
        The pages of the admin come from your modules: a dashboard script that calls{' '}
        <code className="font-mono text-foreground">registerModule()</code> adds its own beside this one.
      </p>
    </div>
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
