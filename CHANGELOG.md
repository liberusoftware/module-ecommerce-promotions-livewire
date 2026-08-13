# Changelog

All notable changes to `liberusoftware/ecommerce-promotions-livewire` are
documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-08-13

First release. The shopper-facing surface of
`liberusoftware/ecommerce-promotions`: a promotion code field and a display of
what the applied offers took off. It adds no business rules of its own.

### Added

- **`ecommerce-promotions::basket-promotions`**, one Livewire 4 component,
  registered through `Livewire::resolveMissingComponent()` — Livewire 4's finder
  returns null for a namespaced name before it consults the explicit registry, so
  `Livewire::component()` alone leaves it unreachable. Registration is deferred
  until the factory resolves, so it does not depend on provider order.
- **One writable property, `code`.** Every public property is either `#[Locked]`
  or `#[Validate]`, never both and never neither, enforced by a reflection test
  over every registered component and by a runtime test that a client write to a
  locked property throws.
- **No money value as client input.** The component holds an opaque basket
  reference and the server prices it through the optional
  `ResolvesShopperBasket` seam.
- **No computed discount as state.** The entitlement is derived per request and
  discarded; only the presented codes survive. A `basket-updated` listener
  re-quotes when the host's cart changes.
- **One refusal message.** Six genuinely different domain refusals — unknown
  code, ended offer, exhausted offer, per-customer limit reached, minimum not
  met, eligibility seam unbound — render byte-identically, with a companion test
  proving the domain distinguished them. `RefusalReason` never reaches the
  shopper, asserted at source level over `src/` and `resources/views/`.
- **Money rendered from `Data\Money`** and per-line figures taken from the
  domain's published largest-remainder allocation, never re-derived.
- An optional basket seam whose absence removes this component's output and
  nothing else, rather than failing the page it sits on.

[0.1.0]: https://github.com/liberusoftware/module-ecommerce-promotions-livewire/releases/tag/0.1.0
