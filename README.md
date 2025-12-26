# vue

This template should help get you started developing with Vue 3 in Vite.

## Recommended IDE Setup

[VSCode](https://code.visualstudio.com/) + [Volar](https://marketplace.visualstudio.com/items?itemName=Vue.volar) (and disable Vetur).

## Type Support for `.vue` Imports in TS

TypeScript cannot handle type information for `.vue` imports by default, so we replace the `tsc` CLI with `vue-tsc` for type checking. In editors, we need [Volar](https://marketplace.visualstudio.com/items?itemName=Vue.volar) to make the TypeScript language service aware of `.vue` types.

## Customize configuration

See [Vite Configuration Reference](https://vite.dev/config/).

## Project Setup

```sh
pnpm install
```

### Compile and Hot-Reload for Development

```sh
pnpm dev
```

### Type-Check, Compile and Minify for Production

```sh
pnpm build
```

vuexy-vuejs-laravel-template/
├── app                      # Controllers and Models
├── bootstrap                # Contains cache and app.php
├── config                   # Application's configuration files
├── database                 # Migrations, model factories, & seeds
├── public                   # index.php ,static folder & Build
│   ├── images/              # Public images
│   ├── favicon.ico           # Favicon
│   └── index.php             # Main php file
├── resources                # Views, Layouts, store and vue.js components
│   ├── images/                # Include all images
│   ├── styles/                # Include all styles files
│   ├── {js/ts}/               # Include all vue files
│   └── views/                 # Contain Blade templates
├── routes/                  # Include Routes Web.php
├── storage/                 # Contains compile blade templates
├── tests/                   # For testing
├── .editorconfig            # Related with your editor
├── .env.example             # Include Database credentials and other environment variables
├── .gitattributes           # Give attributes to path names
├── .gitignore               # Files and Directories to ignore
├── .stylelintrc.json        # Style related file
├── .eslintrc.js             # ESLint Configuration
├── auto-imports.d.ts        # Unplugin auto import file
├── components.d.ts          # Unplugin vue components
├── artisan                  # Include artisans commands
├── shims.d.ts               # Typescript only
├── composer.json            # Dependencies used by composer
├── package.json             # Dependencies used by node
├── env.d.ts                 # Typescript only
├── themeConfig.ts           # Theme Customizer
├── tsconfig.json            # Typescript only file
├── jsconfig.json            # Javascript only file 
├── phpunit.xml              # Related With testing
├── server.php               # For php's internal web server
└── vite.config.ts           # Laravel's vite file
