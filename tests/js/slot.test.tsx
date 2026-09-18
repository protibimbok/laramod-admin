import { act, type ReactNode } from 'react'
import { createRoot, type Root } from 'react-dom/client'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createSlot } from '../../resources/js/lib/slot'

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true })

let container: HTMLElement
let root: Root

beforeEach(() => {
  container = document.createElement('div')
  document.body.append(container)
  root = createRoot(container)
})

afterEach(() => {
  act(() => root.unmount())
  container.remove()
})

function render(node: ReactNode): void {
  act(() => root.render(node))
}

describe('createSlot', () => {
  it('portals the fills into the outlet in mount order', () => {
    const Actions = createSlot('actions')

    render(
      <Actions.Provider>
        <header>
          <Actions.Outlet data-testid="outlet" className="actions" />
        </header>
        <main>
          <Actions.Fill>
            <button>Save</button>
          </Actions.Fill>
          <Actions.Fill>
            <button>Delete</button>
          </Actions.Fill>
        </main>
      </Actions.Provider>,
    )

    const outlet = container.querySelector('[data-testid="outlet"]')

    expect(outlet?.className).toBe('actions')
    expect([...(outlet?.children ?? [])].map((child) => child.textContent)).toEqual(['Save', 'Delete'])
    expect(container.querySelector('main')?.children).toHaveLength(0)
  })

  it('takes a fill away when the page that rendered it is gone', () => {
    const Actions = createSlot('actions')

    const page = (filled: boolean) => (
      <Actions.Provider>
        <Actions.Outlet data-testid="outlet" />
        {filled && <Actions.Fill>Save</Actions.Fill>}
      </Actions.Provider>
    )

    render(page(true))
    expect(container.querySelector('[data-testid="outlet"]')?.textContent).toBe('Save')

    render(page(false))
    expect(container.querySelector('[data-testid="outlet"]')?.textContent).toBe('')
  })

  it('keeps two slots apart', () => {
    const Left = createSlot('left')
    const Right = createSlot('right')

    render(
      <Left.Provider>
        <Right.Provider>
          <Left.Outlet id="left" />
          <Right.Outlet id="right" />
          <Right.Fill>to the right</Right.Fill>
        </Right.Provider>
      </Left.Provider>,
    )

    expect(container.querySelector('#left')?.textContent).toBe('')
    expect(container.querySelector('#right')?.textContent).toBe('to the right')
  })

  it('names the slot that is used outside its provider', () => {
    const Actions = createSlot('actions')
    const error = vi.spyOn(console, 'error').mockImplementation(() => {})

    expect(() => render(<Actions.Fill>Save</Actions.Fill>)).toThrow('[slot] "actions" used outside its provider')

    error.mockRestore()
  })
})
