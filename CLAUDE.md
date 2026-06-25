# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this module does

`Tweakwise_TweakwiseHyva` is a Hyvä Themes compatibility layer for the `Tweakwise_Magento2Tweakwise` Magento 2 module. It registers itself as a compat module via `hyva-themes/magento2-compat-module-fallback`, meaning Hyvä's compatibility system will route Tweakwise template/layout rendering through this module instead of the base Tweakwise module.

It replaces or extends Hyvä's own ViewModels and blocks with Tweakwise-aware counterparts, adds Tweakwise layout handles (`hyva_*`), and provides Hyva-compatible `.phtml` templates for Tweakwise features (layered navigation, swatch rendering, search autocomplete, product recommendations, analytics).

## Module structure

- `src/` — Magento 2 module root (`registration.php` + `etc/`)
- `src/etc/di.xml` — global DI: replaces `Magento\Catalog\Block\Product\View` with the module's own `Block\Product\View`
- `src/etc/frontend/di.xml` — frontend DI: registers this module as a compat module, overrides `Hyva\Theme\ViewModel\SwatchRenderer`, and adds plugins on `Hyva\Theme\ViewModel\ProductList` and `Hyva\Theme\ViewModel\ProductListItem`
- `src/Block/Product/View.php` — extends Magento's product view block to handle ESI/personal merchandising cache TTL
- `src/Plugin/ViewModel/ProductListItem.php` — wraps product card rendering with ESI includes and custom caching keyed on store/customer group
- `src/ViewModel/ProductList/Plugin.php` — intercepts Hyvä's crosssell/upsell/linked-items calls to return Tweakwise recommendation results instead
- `src/ViewModel/SwatchRenderer.php` — replaces Hyvä's swatch renderer; appends active filter state to the block cache key so swatch images reflect applied Tweakwise filters
- `src/Plugin/ViewModel/SearchForm.php` — ViewModel that injects `Magento\Search\Helper\Data` into the search form block
- `src/view/frontend/layout/` — Hyvä-specific layout handles (`hyva_default.xml`, `hyva_catalog_category_view*.xml`, `hyva_catalogsearch_result_index.xml`, `hyva_tweakwise_ajax_*.xml`)
- `src/view/frontend/templates/` — Hyvä-compatible `.phtml` templates for all Tweakwise UI components

## Key architectural patterns

**Compat module registration** (`src/etc/frontend/di.xml`): The module tells Hyvä's `CompatModuleRegistry` that it covers both `Tweakwise_Magento2Tweakwise` and `Emico_AttributeLanding`. This causes Hyvä to skip those modules' non-Hyva templates and use this module's instead.

**ESI / personal merchandising cache** (`Block\Product\View`, `Plugin\ViewModel\ProductListItem`): When `Tweakwise\Magento2Tweakwise\Helper\Cache::personalMerchandisingCanBeApplied()` returns true, product cards are rendered as `<esi:include>` tags instead of inline HTML, keyed on item ID + store + customer group. The `Block\Product\View` adjusts cache TTL for related/upsell/crosssell blocks in the same scenario.

**Swatch cache key enrichment** (`ViewModel\SwatchRenderer`): Hyvä renders product list items with a block cache. This ViewModel appends active swatch filter values from the request to the cache key so that the correct variant image is shown per applied filter.

## Running code quality tools

```bash
# Run all GrumPHP checks (phpcs, phpstan, phpmd, parallel-lint)
vendor/bin/grumphp run

# Run phpcs alone
vendor/bin/phpcs --standard=phpcs.xml src/

# Auto-fix phpcs violations
vendor/bin/phpcbf --standard=phpcs.xml src/

# Run PHPStan alone
vendor/bin/phpstan analyse --configuration=phpstan.neon

# Run unit tests (Codeception)
vendor/bin/codecept run Unit

# Run a single unit test file
vendor/bin/codecept run Unit tests/Unit/SomeTest.php
```

GrumPHP is configured via `grumphp.yml` (extends `vendor/emico/code-quality/grumphp.base.yml`). It runs automatically on `git commit` via the pre-commit hook. Only `*.php` files are included (see `.grumphpinclude`).

Functional tests require a running Magento 2 instance at `https://tweakwise.test` with RabbitMQ; they are not expected to run locally without that environment.

## Releases

Releases are automated via semantic-release on the `main` branch (GitLab CI). Commit messages must follow the [Conventional Commits](https://github.com/semantic-release/semantic-release?tab=readme-ov-file#commit-message-format) format (`feat:`, `fix:`, etc.) so the pipeline can determine the next version and generate release notes automatically.
