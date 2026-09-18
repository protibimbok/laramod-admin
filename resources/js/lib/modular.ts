import type { LucideIcon } from 'lucide-react'
import type { RouteObject } from 'react-router'
import type { Viewer } from './boot'

export interface MenuItem {
  label: string
  icon: LucideIcon
  path: string
}

export interface MenuGroup {
  label: string
  items: MenuItem[]
}

/** Receives the menu the modules before it built and returns the one it wants; the viewer is null for a guest. */
export type MenuFilter = (menu: MenuGroup[], viewer: Viewer | null) => MenuGroup[]

export interface ModuleRegistration {
  id: string
  /** Non-negative: from the start (0 = first). Negative: from the end (-1 = last). */
  order?: number
  menuFilter?: MenuFilter
  routes?: RouteObject[]
}

const modules: ModuleRegistration[] = []

let orderedCache: ModuleRegistration[] | null = null

let sealed = false

export function registerModule(registration: ModuleRegistration): void {
  if (sealed) {
    throw new Error(
      `[modular] module "${registration.id}" was registered after the admin started: call registerModule() at the top level of a dashboard script`,
    )
  }

  if (modules.some((module) => module.id === registration.id)) {
    throw new Error(`[modular] module "${registration.id}" is already registered`)
  }

  modules.push(registration)
  orderedCache = null
}

/**
 * Closes the registry. The admin calls it before it renders: dashboard scripts run before
 * the admin's own entry, so a registration that comes later would silently never show up.
 */
export function seal(): void {
  sealed = true
}

export function getModules(): readonly ModuleRegistration[] {
  return getOrderedModules()
}

function getOrderedModules(): ModuleRegistration[] {
  if (orderedCache) return orderedCache

  const indexed = modules.map((module, registrationIndex) => ({
    module,
    order: module.order ?? 0,
    registrationIndex,
  }))

  const fromStart = indexed
    .filter((entry) => entry.order >= 0)
    .sort((a, b) => a.order - b.order || a.registrationIndex - b.registrationIndex)

  const fromEnd = indexed
    .filter((entry) => entry.order < 0)
    .sort((a, b) => a.order - b.order || a.registrationIndex - b.registrationIndex)

  orderedCache = [...fromStart, ...fromEnd].map((entry) => entry.module)
  return orderedCache
}

export function getMenuGroups(viewer: Viewer | null): MenuGroup[] {
  let menu: MenuGroup[] = []

  for (const mod of getOrderedModules()) {
    if (mod.menuFilter) {
      menu = mod.menuFilter(menu, viewer)
    }
  }

  return menu
}

/** Everything the viewer may reach, flattened across groups. */
export function menuItems(viewer: Viewer | null): MenuItem[] {
  return getMenuGroups(viewer).flatMap((group) => group.items)
}

/** Where "/" lands: the first page the viewer is offered. */
export function homePath(viewer: Viewer | null): string | null {
  return menuItems(viewer)[0]?.path ?? null
}

/**
 * The one item a page belongs to: the deepest that covers it, so a child page
 * does not light up its parent as well.
 */
export function activeMenuPath(pathname: string, menu: MenuGroup[]): string | null {
  let match: string | null = null

  for (const group of menu) {
    for (const item of group.items) {
      if (!isUnder(pathname, item.path)) continue
      if (!match || item.path.length > match.length) {
        match = item.path
      }
    }
  }

  return match
}

export function findMenuLocation(
  pathname: string,
  viewer: Viewer | null,
): { group: MenuGroup; item: MenuItem } | null {
  const menu = getMenuGroups(viewer)
  const active = activeMenuPath(pathname, menu)
  if (!active) return null

  for (const group of menu) {
    const item = group.items.find((candidate) => candidate.path === active)
    if (item) return { group, item }
  }

  return null
}

export function isUnder(pathname: string, path: string): boolean {
  if (path === '/') return pathname === '/'
  return pathname === path || pathname.startsWith(`${path}/`)
}

export function getRoutes(): RouteObject[] {
  return getOrderedModules().flatMap((mod) => mod.routes ?? [])
}
