import { Link, Navigate, useRoutes } from 'react-router'

import { Layout } from './layout/Layout'
import { viewer } from './lib/boot'
import { getRoutes, homePath } from './lib/modular'

// No module owns "/": it lands on the first page the viewer is offered.
function Home() {
  const home = homePath(viewer)

  return home && home !== '/' ? <Navigate to={home} replace /> : null
}

function NotFound() {
  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-2 text-center">
      <h1 className="text-lg font-semibold">Page not found</h1>
      <p className="text-sm text-muted-foreground">No module of the admin has a page at this address.</p>
      <Link to="/" className="text-sm underline underline-offset-4">
        Back to the start
      </Link>
    </div>
  )
}

export default function App() {
  return useRoutes([
    {
      element: <Layout />,
      children: [{ index: true, element: <Home /> }, ...getRoutes(), { path: '*', element: <NotFound /> }],
    },
  ])
}
