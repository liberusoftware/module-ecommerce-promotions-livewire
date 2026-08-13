# Ecommerce: Promotions Livewire

> This optional Livewire 4 presentation package provides interactive server-driven components for exactly one independent domain module. Components coordinate public queries/actions and presentation state; they do not own persistence, authorization decisions, tenancy, business rules, or theme identity. The package has no dependency on application Ap

[Software](https://liberusoftware.com) ·
[Hosting](https://liberuhosting.com) ·
[Services](https://liberuservices.com) ·
[Liberu Group](https://liberugroup.com)

![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white) ![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white) ![Livewire](https://img.shields.io/badge/Livewire-4-FB70A9)
[![Latest release](https://img.shields.io/github/v/release/liberusoftware/module-ecommerce-promotions-livewire?sort=semver)](https://github.com/liberusoftware/module-ecommerce-promotions-livewire/releases/latest) [![Tests](https://github.com/liberusoftware/module-ecommerce-promotions-livewire/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/liberusoftware/module-ecommerce-promotions-livewire/actions/workflows/tests.yml)

## Features

- One shopper-facing component: a promotion code field and a display of what the
  applied offers took off. Nothing merchant-facing — that is `-filament`.
- **Exactly one writable property**, the code string. Every public property is
  `#[Locked]` or `#[Validate]`, never both, never neither, enforced by a
  reflection test over every registered component.
- **No money value is ever a client input.** The component holds an opaque basket
  reference and the server prices it.
- **No computed discount is held as state.** An entitlement is perishable: it is
  re-quoted on every render and on every basket change, and never cached.
- **One refusal message for every refusal.** Unknown code, expired offer,
  exhausted offer, a limit already reached, a minimum not met, an unbound
  eligibility seam — all render byte-identically, because a distinguishable
  refusal is an oracle for which codes exist.
- Money rendered from the domain's `Money`, and per-line figures taken from the
  allocation it publishes. Nothing re-derived from a float.

## Requirements

- **PHP 8.5**
- **Laravel 13**, **Livewire 4.2+**
- `liberusoftware/ecommerce-promotions` — the domain package that owns every rule

## Quick start

The domain package is not on Packagist, so add both VCS repositories to the
host's `composer.json` first (`docs/adoption.md` has the entries), then:

```bash
composer require liberusoftware/ecommerce-promotions-livewire
```

Enable both modules, bind the basket seam, and place the component:

```blade
<livewire:ecommerce-promotions::basket-promotions
    :tenant-id="$tenant->id"
    :basket-ref="$cart->reference"
/>
```

Full steps, including the one interface you have to implement, are in
[docs/adoption.md](docs/adoption.md).

## Package documentation

- [docs/domain.md](docs/domain.md) — what the component is, what it holds, and the
  three rules it exists to keep
- [docs/adoption.md](docs/adoption.md) — installing, enabling, binding the basket
  seam, and overriding the view
- [docs/runbook.md](docs/runbook.md) — the four ways this renders nothing, and
  which of them is somebody else's fault

## Documentation

- [Liberu Main Documentation](https://github.com/liberusoftware/documentation)
- [Architecture & Standards Index](https://github.com/liberusoftware/documentation/tree/main/architecture)

## Related Liberu Projects

| Project | Repository | Purpose |
| --- | --- | --- |
| **Boilerplate** | [liberusoftware/boilerplate-laravel](https://github.com/liberusoftware/boilerplate-laravel) | Shared Laravel application foundation and reference composition |
| **CMS** | [liberu-cms/cms-laravel](https://github.com/liberu-cms/cms-laravel) | Structured content, publishing, media, multisite, and headless delivery |
| **CRM** | [liberu-crm/crm-laravel](https://github.com/liberu-crm/crm-laravel) | Customer data, sales, marketing, service, and customer success |
| **Billing** | [liberu-billing/billing-laravel](https://github.com/liberu-billing/billing-laravel) | Products, subscriptions, invoicing, payments, and provisioning |
| **Accounting** | [liberu-accounting/accounting-laravel](https://github.com/liberu-accounting/accounting-laravel) | Ledgers, banking, tax, expenses, close, and financial reporting |
| **Ecommerce** | [liberu-ecommerce/ecommerce-laravel](https://github.com/liberu-ecommerce/ecommerce-laravel) | Catalog, checkout, orders, fulfillment, returns, B2B, and omnichannel commerce |
| **Control Panel** | [liberu-control-panel/control-panel-laravel](https://github.com/liberu-control-panel/control-panel-laravel) | Hosting, infrastructure, DNS, mail, databases, backups, and security operations |
| **Automation** | [liberu-automation/automation-laravel](https://github.com/liberu-automation/automation-laravel) | Governed workflows, provider-neutral AI, approvals, and connectors |

## Security

Please do not report security vulnerabilities through public GitHub issues.
Follow our [Security Policy](https://github.com/liberusoftware/documentation/blob/main/architecture/SECURITY.md) for private reporting and supported versions.

## License

This project is open-source software. You may use, modify, and distribute it
under the terms described in [LICENSE.md](LICENSE.md).

The linked license text is authoritative; this summary is not legal advice.

## Feedback and contributing

Feedback and contributions are welcome. You can help by reporting reproducible
bugs, proposing focused enhancements, improving documentation or translations,
and submitting tested code changes.

Before contributing, please read [CONTRIBUTING.md](https://github.com/liberusoftware/documentation/blob/main/standards/CONTRIBUTING.md) and our
[Code of Conduct](https://github.com/liberusoftware/documentation/blob/main/architecture/CODE_OF_CONDUCT.md). Search existing issues first, then use
the appropriate issue template. Pull requests should explain the problem and
approach, remain focused, include or update tests, pass the required workflows,
and document user-visible or breaking changes.

## Contributors

Thank you to everyone who helps improve Liberu.

<a href="https://github.com/liberusoftware/module-ecommerce-promotions-livewire/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=liberusoftware/module-ecommerce-promotions-livewire" alt="Contributors to liberusoftware/module-ecommerce-promotions-livewire">
</a>

[View the full contributors graph](https://github.com/liberusoftware/module-ecommerce-promotions-livewire/graphs/contributors).
