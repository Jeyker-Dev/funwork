# AGENTS

Guía para agentes (humano o IA) que trabajen en este repositorio.

## Stack

- Laravel 13 (starter kit de Livewire), PHP ^8.4.1, Fortify.
- Livewire v4 (Single File Components nativos), Flux / Flux Pro, Alpine v3, TailwindCSS, Vite.
- Tests: Pest (`./vendor/bin/pest`). Lint: Pint (`composer lint`).

## Comandos

```bash
composer install
cp .env.example .env
php artisan key:generate
pnpm install
pnpm run dev
php artisan serve
composer lint
./vendor/bin/pest
```

## Reglas del proyecto

- No modificar el `.env` real; los cambios de variables van en `.env.example`.
- Los componentes Livewire se crean siempre con `php artisan make:livewire`, nunca a mano.
- No existe `livewire/volt`: no importar `Livewire\Volt\Volt`. En tests usar `Livewire::test()` y `Livewire::actingAs()`.
- Flux Pro es una dependencia privada; en CI necesita credenciales:

```bash
composer config http-basic.composer.fluxui.dev "${FLUX_USERNAME}" "${FLUX_LICENSE_KEY}"
```

## Documentación

`docs/` es la fuente de verdad (ver `docs/README.md`) y se mantiene según la skill `project-docs`. Las features grandes abren `odd/tasks/<feature>.md`. `docs/archive/` (OpenSpec/SDD) es solo lectura.

## Skills

Las convenciones generales vienen de las skills instaladas en `.agents/skills/` (`clean-code`, `testing-strategy`, `error-handling`, `project-docs`, `laravel-conventions`), que se actualizan con `npx skills update`. No repetir sus reglas en este archivo.
