import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'

export default defineConfig({
  resolve: {
    // What the application resolves to its own copies is, here, the versions the admin was written against.
    alias: {
      '@/components/ui': fileURLToPath(new URL('./stubs/components/ui', import.meta.url)),
      '@/hooks': fileURLToPath(new URL('./stubs/hooks', import.meta.url)),
    },
  },
  test: {
    environment: 'happy-dom',
    include: ['tests/js/**/*.test.{ts,tsx}'],
  },
})
