import { Home } from 'lucide-react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { MenuGroup } from '../../resources/js/lib/modular'

// The registry is module state, so every test gets its own copy of it.
let modular: typeof import('../../resources/js/lib/modular')

beforeEach(async () => {
  vi.resetModules()
  modular = await import('../../resources/js/lib/modular')
})

function group(label: string, ...paths: string[]): MenuGroup {
  return { label, items: paths.map((path) => ({ label: path, icon: Home, path })) }
}

describe('registerModule', () => {
  it('orders modules from the start, then from the end, and keeps registration order for ties', () => {
    modular.registerModule({ id: 'last', order: -1 })
    modular.registerModule({ id: 'second', order: 5 })
    modular.registerModule({ id: 'before-last', order: -2 })
    modular.registerModule({ id: 'first' })
    modular.registerModule({ id: 'first-too', order: 0 })

    expect(modular.getModules().map((module) => module.id)).toEqual([
      'first',
      'first-too',
      'second',
      'before-last',
      'last',
    ])
  })

  it('refuses an id that is already registered', () => {
    modular.registerModule({ id: 'blog' })

    expect(() => modular.registerModule({ id: 'blog' })).toThrow('"blog" is already registered')
  })

  it('refuses a registration once the registry is sealed', () => {
    modular.registerModule({ id: 'early' })
    modular.seal()

    expect(() => modular.registerModule({ id: 'late' })).toThrow(
      'call registerModule() at the top level of a dashboard script',
    )
    expect(modular.getModules().map((module) => module.id)).toEqual(['early'])
  })
})

describe('menu', () => {
  it('folds the menu filters in module order and hands them the viewer', () => {
    const viewer = { id: 1, name: 'Ada', email: 'ada@example.com' }

    modular.registerModule({
      id: 'guard',
      order: -1,
      menuFilter: (menu, current) => (current ? menu : []),
    })
    modular.registerModule({
      id: 'blog',
      order: 1,
      menuFilter: (menu) => [...menu, group('Blog', '/posts')],
    })
    modular.registerModule({
      id: 'dashboard',
      menuFilter: (menu) => [...menu, group('Overview', '/')],
    })

    expect(modular.getMenuGroups(viewer).map((entry) => entry.label)).toEqual(['Overview', 'Blog'])
    expect(modular.menuItems(viewer).map((item) => item.path)).toEqual(['/', '/posts'])
    expect(modular.homePath(viewer)).toBe('/')
    expect(modular.getMenuGroups(null)).toEqual([])
    expect(modular.homePath(null)).toBeNull()
  })

  it('collects the routes in module order', () => {
    modular.registerModule({ id: 'blog', order: 1, routes: [{ path: 'posts' }, { path: 'tags' }] })
    modular.registerModule({ id: 'dashboard', routes: [{ index: true }] })
    modular.registerModule({ id: 'silent' })

    expect(modular.getRoutes()).toEqual([{ index: true }, { path: 'posts' }, { path: 'tags' }])
  })

  it('takes the deepest item that covers a page as the active one', () => {
    const menu = [group('Overview', '/'), group('Blog', '/posts', '/posts/drafts')]

    expect(modular.activeMenuPath('/', menu)).toBe('/')
    expect(modular.activeMenuPath('/posts/12', menu)).toBe('/posts')
    expect(modular.activeMenuPath('/posts/drafts/12', menu)).toBe('/posts/drafts')
    expect(modular.activeMenuPath('/postscript', menu)).toBeNull()
  })

  it('finds the group and the item a page belongs to', () => {
    modular.registerModule({
      id: 'blog',
      menuFilter: (menu) => [...menu, group('Blog', '/posts', '/posts/drafts')],
    })

    const location = modular.findMenuLocation('/posts/drafts/12', null)

    expect(location?.group.label).toBe('Blog')
    expect(location?.item.path).toBe('/posts/drafts')
    expect(modular.findMenuLocation('/elsewhere', null)).toBeNull()
  })
})
