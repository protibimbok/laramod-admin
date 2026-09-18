import { Outlet, useLocation } from 'react-router'

import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import { Separator } from '@/components/ui/separator'
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar'

import { appName, viewer } from '../lib/boot'
import { findMenuLocation } from '../lib/modular'
import { AppSidebar } from './AppSidebar'
import { HeaderActions } from './HeaderActions'

function PageBreadcrumb() {
  const { pathname } = useLocation()
  const location = findMenuLocation(pathname, viewer)

  return (
    <Breadcrumb>
      <BreadcrumbList>
        {location && (
          <>
            <BreadcrumbItem className="hidden md:block">{location.group.label}</BreadcrumbItem>
            <BreadcrumbSeparator className="hidden md:block" />
          </>
        )}
        <BreadcrumbItem>
          <BreadcrumbPage>{location?.item.label ?? appName}</BreadcrumbPage>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>
  )
}

export function Layout() {
  return (
    // Exactly one viewport tall: pages scroll inside the content area, never the window.
    <SidebarProvider className="h-svh overflow-hidden">
      <HeaderActions.Provider>
        <AppSidebar />

        <SidebarInset className="min-w-0 overflow-hidden">
          <header className="flex h-16 shrink-0 items-center gap-2 border-b px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
            <SidebarTrigger className="-ml-1" />
            <Separator
              orientation="vertical"
              className="mr-2 self-center! data-[orientation=vertical]:h-4"
            />
            <PageBreadcrumb />

            <HeaderActions.Outlet className="ml-auto flex min-w-0 items-center gap-2" />
          </header>

          <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-auto p-4 md:p-6">
            <Outlet />
          </div>
        </SidebarInset>
      </HeaderActions.Provider>
    </SidebarProvider>
  )
}
