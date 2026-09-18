// First, so the dashboard is registered before the module whose script brought this file in.
import './modules/dashboard'

export { DashboardWidgets } from './layout/DashboardWidgets'
export { HeaderActions } from './layout/HeaderActions'
export { basePath, viewer, type Viewer } from './lib/boot'
export {
  registerModule,
  type MenuFilter,
  type MenuGroup,
  type MenuItem,
  type ModuleRegistration,
} from './lib/modular'
export { createSlot, type Slot } from './lib/slot'
