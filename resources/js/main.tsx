import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router'

import { TooltipProvider } from '@/components/ui/tooltip'

import './index'
import App from './App'
import { basePath } from './lib/boot'
import { seal } from './lib/modular'

// Every dashboard script ran before this entry, so whatever registers from here on would never be shown.
seal()

createRoot(document.getElementById('admin')!).render(
  <StrictMode>
    <BrowserRouter basename={basePath}>
      <TooltipProvider>
        <App />
      </TooltipProvider>
    </BrowserRouter>
  </StrictMode>,
)
