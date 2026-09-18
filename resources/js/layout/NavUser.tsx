import { ChevronsUpDownIcon, LogOutIcon } from 'lucide-react'

import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from '@/components/ui/sidebar'

import { logoutUrl, type Viewer } from '../lib/boot'

function initials(name: string) {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
}

/** A form post rather than a request: where the viewer lands afterwards is the application's call. */
function signOut(url: string) {
  const token = document.createElement('input')
  token.type = 'hidden'
  token.name = '_token'
  token.value = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

  const form = document.createElement('form')
  form.method = 'post'
  form.action = url
  form.append(token)

  document.body.append(form)
  form.submit()
}

function SignOutItem({ url }: { url: string }) {
  return (
    <DropdownMenuItem onClick={() => signOut(url)}>
      <LogOutIcon />
      Sign out
    </DropdownMenuItem>
  )
}

function Identity({ viewer }: { viewer: Viewer }) {
  return (
    <div className="grid flex-1 text-left leading-tight">
      <span className="truncate font-medium">{viewer.name}</span>
      <span className="truncate text-xs text-muted-foreground">{viewer.email}</span>
    </div>
  )
}

export function NavUser({ viewer }: { viewer: Viewer }) {
  const { isMobile } = useSidebar()

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger
            render={
              <SidebarMenuButton
                size="lg"
                className="data-popup-open:bg-sidebar-accent data-popup-open:text-sidebar-accent-foreground"
              />
            }
          >
            <Avatar className="size-8 rounded-lg">
              <AvatarFallback className="rounded-lg">{initials(viewer.name)}</AvatarFallback>
            </Avatar>
            <Identity viewer={viewer} />
            <ChevronsUpDownIcon className="ml-auto size-4" />
          </DropdownMenuTrigger>

          <DropdownMenuContent
            className="min-w-56 rounded-lg"
            side={isMobile ? 'bottom' : 'right'}
            align="end"
            sideOffset={4}
          >
            <DropdownMenuGroup>
              <DropdownMenuLabel className="font-normal text-foreground">
                <Identity viewer={viewer} />
              </DropdownMenuLabel>
            </DropdownMenuGroup>

            {logoutUrl && (
              <>
                <DropdownMenuSeparator />
                <SignOutItem url={logoutUrl} />
              </>
            )}
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}
