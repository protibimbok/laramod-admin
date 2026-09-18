# laramod-admin

A React and shadcn/ui admin, as a [laramod](https://github.com/protibimbok/laramod) module.

## Requirements

- PHP 8.3+
- Laravel 13 with [laramod](https://github.com/protibimbok/laramod) 0.1.1+
- [pnpm](https://pnpm.io) and Tailwind CSS 4, the way a new Laravel application sets it up
- TypeScript, see below

## Installation

```bash
composer require protibimbok/laramod-admin
```

Composer asks whether to trust the package's plugin, see [After it is installed](#after-it-is-installed). Answer yes, or allow it yourself:

```bash
composer config allow-plugins.protibimbok/laramod-admin true
```

That is the whole installation: the plugin lists the module in `bootstrap/modules.php` and runs `php artisan admin:init`.

```php
use Laramod\Admin\AdminModule;

return [
    // ...
    AdminModule::class,
];
```

The package is a module like any other, there is no service provider to discover, and the list stays yours: move the module, or register it from a service provider instead, and it is never listed a second time.

`admin:init` sets up the frontend the admin is built with. Every step is skipped when it is already done, so the command is safe to run again:

| Step | Skipped when |
| --- | --- |
| `pnpm install` | `node_modules` exists |
| `pnpm add react react-dom` and `pnpm add -D @vitejs/plugin-react @types/react @types/react-dom` | `package.json` lists them |
| `pnpm dlx shadcn@latest init --template laravel --defaults` | `components.json` exists |

### TypeScript comes first

TypeScript is the application's own to set up. Without `typescript` in `package.json`, a `tsconfig.json` and the Vite configuration in `vite.config.ts`, the command fails before it touches anything and lists what to set up first:

```bash
pnpm add -D typescript
mv vite.config.js vite.config.ts
```

```json
{
    "compilerOptions": {
        "target": "ESNext",
        "module": "ESNext",
        "moduleResolution": "bundler",
        "jsx": "react-jsx",
        "strict": true,
        "noEmit": true,
        "skipLibCheck": true,
        "isolatedModules": true,
        "types": ["vite/client"],
        "paths": {
            "@/*": ["./resources/js/*"]
        }
    },
    "include": ["resources/js/**/*.ts", "resources/js/**/*.tsx"]
}
```

shadcn/ui needs the `@/*` alias and refuses to set itself up without it.

### Vite

`vite.config.ts` is your own code and is never edited, the command tells you to add the React plugin:

```ts
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [laravel({ /* ... */ }), react(), tailwindcss()],
});
```

### After it is installed

The package is a Composer plugin. When Composer has installed the package, at the end of that run, it:

1. asks `laramod:list` whether the application has registered `Laramod\Admin\AdminModule`, and lists it in `bootstrap/modules.php` when it has not, the way `make:module` lists a new module: as text, the import sorted in, the rest of the file as you wrote it,
2. runs `php artisan admin:init`.

A fresh clone is set up by `composer install` alone, and the application's `composer.json` has no script for it.

- When the module cannot be listed, `bootstrap/modules.php` is missing or does not end in a plain `];`, the plugin tells you to list it and to run the command yourself.
- What the command reports is for you to read. It never fails Composer, not even when TypeScript is missing.
- An update of the package, `--no-scripts`, `--no-plugins` and a plugin that was not allowed all leave the command to you: `php artisan admin:init`.

## Testing

```bash
composer test
composer lint
```

## License

MIT
