# Lumy

> Content Intelligence for Lumixo

Lumy is a data-driven Content Intelligence System that turns Lumixo's historical content performance and audience behavior into better future content decisions.

## Purpose

Lumy combines Instagram analytics with structured content annotations such as Hooks, Topics, Pillars, CTAs, and Content Goals.

The system is designed to progress from:

`Data → Analytics → Patterns → Decisions → AI-assisted Strategy`

## Technology Stack

- Laravel
- Livewire
- Blade
- MaryUI
- Tailwind CSS
- DaisyUI
- Laravel Queue / Scheduler
- Zernio API

## Documentation

- [Product Vision & Roadmap](docs/product-vision.md)

## Development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer dev
```

## Current Milestone

### Phase 1 — Foundation & Observatory

- Clean Lumy foundation
- Define database schema
- Integrate Zernio
- Import historical content
- Store analytics snapshots
- Build the initial content catalog and dashboard

## Upstream

Lumy was bootstrapped from the Coreflare/xDeploy foundation. xDeploy remains the upstream foundation; Lumy is maintained as an independent product repository.
