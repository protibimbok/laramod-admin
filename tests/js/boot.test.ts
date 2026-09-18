import { beforeEach, describe, expect, it, vi } from 'vitest'

beforeEach(() => {
  vi.resetModules()
  document.body.innerHTML = ''
})

describe('boot', () => {
  it('reads what the server handed over from the element the admin mounts in', async () => {
    const admin = document.createElement('div')
    admin.id = 'admin'
    admin.dataset.boot = JSON.stringify({
      basePath: '/admin',
      viewer: { id: 1, name: 'Ada', email: 'ada@example.com' },
    })
    document.body.append(admin)

    const boot = await import('../../resources/js/lib/boot')

    expect(boot.basePath).toBe('/admin')
    expect(boot.viewer).toEqual({ id: 1, name: 'Ada', email: 'ada@example.com' })
  })

  it('has no viewer for a guest', async () => {
    const admin = document.createElement('div')
    admin.id = 'admin'
    admin.dataset.boot = JSON.stringify({ basePath: '/admin', viewer: null })
    document.body.append(admin)

    const boot = await import('../../resources/js/lib/boot')

    expect(boot.viewer).toBeNull()
  })

  it('fails on a page that is not the admin', async () => {
    await expect(import('../../resources/js/lib/boot')).rejects.toThrow('#admin[data-boot] not found')
  })
})
