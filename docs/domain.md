# What this package is

The shopper-facing surface of Promotions, and nothing else: a code entry field
and a display of what the applied offers took off.

It owns **no business rules**. Every decision — whether a code applies, how much
comes off, which line carries it, whether an offer is exhausted — belongs to
`liberusoftware/ecommerce-promotions`. This package renders the answer.

Anything that moves somebody else's money, reads hostile input on a merchant's
behalf, or serves a reconciliation queue is out of scope and belongs in
`-filament`. Offer authoring, the status decision log, the redemption ledger and
the skipped-offer report are all there, not here.

## The one component

| registered name | class | writable properties |
|---|---|---|
| `ecommerce-promotions::basket-promotions` | `Components\BasketPromotions` | `code` — and only `code` |

One component rather than two. A separate field and display would each have to
quote independently and stay in step with each other, and the only thing they
would share is the list of codes the shopper presented. Splitting them is a
`0.2.0` question, and the answer will be a parent component passing state down,
not two components each holding their own.

### Its state

| property | marking | what it is |
|---|---|---|
| `tenantId` | `#[Locked]` | the merchant whose offers are evaluated |
| `basketRef` | `#[Locked]` | an opaque handle the host resolves to a basket |
| `appliedCodes` | `#[Locked]` | the codes the shopper has presented, normalised by the domain |
| `code` | `#[Validate]` | what the shopper is typing |

Every public property is either `#[Locked]` or `#[Validate]` — never both, never
neither. A reflection test walks every registered component and enforces the
partition; a runtime test proves a client write to a locked property throws.

**No money value is anywhere in that table.** A Livewire component round-trips
its public properties through the browser, so a basket held as a property is a
basket the shopper can re-price. The component holds a reference and the server
prices it.

## Three rules it exists to keep

### An entitlement is perishable and is never cached

The component holds no computed discount. It re-quotes on every render and on
every `basket-updated` event, and throws the result away with the response.

This is the host's §2.9 fault answered at the surface. `CartController::applyCoupon()`
writes `['code', 'discount', 'coupon_id']` into the session;
`CheckoutController` and `HeadlessCheckoutService` were each taught,
independently, to ignore that `discount` and recompute against the live cart.
Both fixes are right and the stale copy is still sitting there. Two surfaces
have solved this once each; a third has nothing stopping it. This is not the
third.

A basket that shrinks under an applied code loses the reduction, without the
shopper doing anything and without the code being withdrawn. A basket that grows
back into it gains it again.

### The refusal is one message

Every refusal renders `CodeRefused::MESSAGE`:

> That code cannot be applied to this basket.

An unknown code, an ended offer, an exhausted one, one this shopper has already
used, a minimum the basket does not meet, and an offer whose eligibility seam is
unbound all produce that string and an otherwise byte-identical page. A test
renders all six and asserts the visible DOM is identical; a second test asserts
the domain really did distinguish them, so the first cannot pass vacuously.

The domain publishes a machine-readable `RefusalReason` for each. It is
**merchant-facing** and this package never touches it — not in a view, not in a
returned value, not in a log line a shopper could reach. A source-level test
greps `src/` and `resources/views/` for it.

That is addendum §5.8 and wave 7's gift-card rule: enumeration is closed by
making every wrong answer the same answer. The host broke it in the one place it
had identified the threat — `/cart/apply-coupon` was throttled against
enumeration by a route comment saying so, while returning three distinguishable
refusals, the third of which printed the coupon's configured minimum spend to a
caller who did not hold it.

A refusal is **spent**, never resubmittable: nothing in this domain is transient
and there is no 423, so the message invites no retry. A test asserts it says
neither "try again" nor "shortly".

### Money comes from the domain

Every amount rendered is a `Data\Money` — integer minor units, a currency and an
exponent — printed through `decimal()`. Nothing is reconstructed from a float.

The per-line figures are the domain's published allocation, which sums to the
entitlement total exactly by largest remainder. This package does not re-derive
it. A caller that spreads the total pro-rata instead produces a line total that
disagrees with the order total by a penny, forever.

## The seam

```php
interface ResolvesShopperBasket
{
    public function basketFor(string $tenantId, string $basketRef): ?Basket;
}
```

Optional and **unbound by default**. It exists because the component may not
hold a basket and may not read a cart: Promotions is told the basket and never
fetches one, and a surface that could read a cart would eventually decide what
is in it.

Its blast radius is the whole component, and only the component. Unbound, the
field renders, no reduction is displayed, and any presented code is refused with
the one message. The page the storefront put the component on still serves. That
is the wave-12 rule: the blast radius of an unbound seam is the scope of the
thing it controls.

A reference the seam does not answer for resolves to nothing rather than to
somebody else's basket. Guessing another shopper's reference prices nothing.

## Why `resolveMissingComponent()`

Livewire 4's `Finder::resolveClassComponentClassName()` splits a name on `::`,
looks the namespace up in `classNamespaces`, and returns null — it never reaches
the explicit registry `Livewire::component()` writes to. So the obvious
registration silently does not work for a namespaced name. `addNamespace()` does
reach it, but maps one component namespace onto exactly one PHP namespace, which
would decide class naming for every component this package ever ships.

The registration is deferred until Livewire's factory is first resolved, so this
provider does not depend on booting after Livewire's — the host registers
modules from `MODULES_ENABLED` and makes no promise about order. A test asserts
the direct lookup really does return null, so if a Livewire release ever makes it
work, the test fails and the provider can be simplified.
