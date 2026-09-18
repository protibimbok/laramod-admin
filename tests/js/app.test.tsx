import { FileTextIcon } from 'lucide-react'
import { act } from 'react'
import { createRoot, type Root } from 'react-dom/client'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true })

let root: Root

beforeEach(() => {
  vi.resetModules()
  document.body.innerHTML = ''
})

afterEach(() => {
  act(() => root.unmount())
})

// The registry and the boot data are module state, so the admin is imported anew for every test.
async function admin(boot: object) {
  const element = document.createElement('div')
  element.id = 'admin'
  element.dataset.boot = JSON.stringify({ appName: 'Acme', basePath: '/admin', logoutUrl: null, viewer: null, ...boot })
  document.body.append(element)

  const api = await import('../../resources/js/index')
  const { default: App } = await import('../../resources/js/App')
  const { MemoryRouter } = await import('react-router')
  const { TooltipProvider } = await import('@/components/ui/tooltip')

  return {
    ...api,
    element,
    visit(path: string) {
      root = createRoot(element)

      act(() =>
        root.render(
          <MemoryRouter initialEntries={[path]}>
            <TooltipProvider>
              <App />
            </TooltipProvider>
          </MemoryRouter>,
        ),
      )
    },
  }
}

function links(element: HTMLElement): string[] {
  return [...element.querySelectorAll('[data-sidebar="menu-button"][href]')].map(
    (link) => `${link.textContent} ${link.getAttribute('href')}`,
  )
}

describe('the admin', () => {
  it('lands on the first page of the menu', async () => {
    const { element, visit } = await admin({})

    visit('/')

    expect(links(element)).toEqual(['AcmeAdmin /', 'Dashboard /dashboard'])
    expect(element.querySelector('h1')?.textContent).toBe('Dashboard')
    expect(element.querySelector('[data-slot="breadcrumb-page"]')?.textContent).toBe('Dashboard')
  })

  it('shows the pages, the menu and the header actions of a module', async () => {
    const { element, visit, registerModule, HeaderActions } = await admin({})

    registerModule({
      id: 'blog',
      menuFilter: (menu) => [...menu, { label: 'Blog', items: [{ label: 'Posts', icon: FileTextIcon, path: '/posts' }] }],
      routes: [
        {
          path: 'posts',
          children: [
            { index: true, element: <h1>All posts</h1> },
            {
              path: ':post',
              element: (
                <>
                  <HeaderActions.Fill>
                    <button>Publish</button>
                  </HeaderActions.Fill>
                  <h1>One post</h1>
                </>
              ),
            },
          ],
        },
      ],
    })

    visit('/posts/12')

    expect(links(element).slice(1)).toEqual(['Dashboard /dashboard', 'Posts /posts'])
    expect(element.querySelector('h1')?.textContent).toBe('One post')
    expect(element.querySelector('header')?.textContent).toContain('Publish')
    expect(element.querySelector('[data-sidebar="menu-button"][data-active]')?.textContent).toBe('Posts')
  })

  it('says so when no module has a page at the address', async () => {
    const { element, visit } = await admin({})

    visit('/nowhere')

    expect(element.querySelector('h1')?.textContent).toBe('Page not found')
  })

  it('shows who is looking, and nobody for a guest', async () => {
    const guest = await admin({})
    guest.visit('/dashboard')
    expect(guest.element.querySelector('[data-sidebar="footer"]')).toBeNull()
    act(() => root.unmount())

    vi.resetModules()
    document.body.innerHTML = ''

    const { element, visit } = await admin({ viewer: { id: 1, name: 'Ada Lovelace', email: 'ada@example.com' } })
    visit('/dashboard')

    expect(element.querySelector('[data-sidebar="footer"]')?.textContent).toBe('ALAda Lovelaceada@example.com')
  })

  it('signs out through the logout route of the application', async () => {
    const { element, visit } = await admin({
      logoutUrl: 'http://localhost/logout',
      viewer: { id: 1, name: 'Ada Lovelace', email: 'ada@example.com' },
    })
    const submit = vi.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(() => {})

    visit('/dashboard')
    await act(async () => element.querySelector<HTMLElement>('[data-sidebar="footer"] button')?.click())

    const item = document.querySelector<HTMLElement>('[data-slot="dropdown-menu-item"]')
    expect(item?.textContent).toBe('Sign out')

    await act(async () => item?.click())

    const form = document.querySelector('form')
    expect(submit).toHaveBeenCalledOnce()
    expect(form?.method).toBe('post')
    expect(form?.action).toBe('http://localhost/logout')
    expect(form?.querySelector('input')?.name).toBe('_token')

    submit.mockRestore()
  })
})
