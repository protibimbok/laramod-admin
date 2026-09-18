export interface Viewer {
  id: number | string
  name: string
  email: string
}

interface Boot {
  basePath: string
  viewer: Viewer | null
}

/** What the server hands the admin: the data-boot attribute of the element it mounts in. */
function read(): Boot {
  const boot = document.getElementById('admin')?.dataset.boot
  if (!boot) throw new Error('[boot] #admin[data-boot] not found: the admin only runs on its own page')

  return JSON.parse(boot) as Boot
}

export const { basePath, viewer } = read()
