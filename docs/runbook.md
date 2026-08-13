# Runbook

This package has no queue, no schedule, no table and no writes. Every incident
below is a rendering symptom with a cause somewhere else, so the runbook is
mostly about telling which somewhere else.

## "Unable to find component: [ecommerce-promotions::basket-promotions]"

The provider did not boot, or booted and its registration was lost.

1. Is `ecommerce-promotions-livewire` in `MODULES_ENABLED`? Installing boots
   nothing by design.
2. Is `PromotionsLivewireServiceProvider` in `app('config')` provider list —
   `php artisan about` will not show it, so check
   `app()->getProvider(PromotionsLivewireServiceProvider::class)` in tinker.
3. Registration is deferred to `callAfterResolving('livewire.factory')`. If
   Livewire itself is not installed, that callback never fires and this is the
   symptom. `composer show livewire/livewire`.

Registration is by `Livewire::resolveMissingComponent()`, not
`Livewire::component()`, and that is not interchangeable — see `docs/domain.md`.

## The field renders but no reduction ever appears

Almost always the basket seam.

```php
app()->bound(Liberu\Ecommerce\Promotions\Livewire\Contracts\ResolvesShopperBasket::class);
```

`false` means unbound, which is a valid deployment and a silent one: the field
works, every code is refused, nothing is displayed. Bind it
(`docs/adoption.md` §4).

`true` means it is bound and returning null for the reference the component was
mounted with. Call it by hand with the same two arguments and see. The usual
cause is tenant scoping in the implementation rejecting a reference minted under
a different tenant id.

## A shopper says their code is valid and we say it is not

**Do not add a second message.** The refusal is deliberately one answer for
thirteen reasons, and every proposal to "just tell them it expired" reopens the
enumeration oracle that addendum §5.8 closes.

The reason is available to staff, never to the shopper. Quote the basket through
the merchant-facing surface:

```php
$entitlement = app(Liberu\Ecommerce\Promotions\Actions\QuoteBasket::class)(
    $tenantId, $basket, ['THEIRCODE'],
);

$entitlement->refusedCodes;   // code => RefusalReason
$entitlement->skipped;        // offers that did not apply, and why
```

`eligibility_unresolvable` there means an offer named a customer group or a
collection and the relevant domain seam is unbound — the offer has been reaching
nobody, and that is a deployment fault rather than a shopper one. The
`-filament` surface shows this list; it is the intended place to look.

## The displayed reduction disagrees with the order total

The component displays what `QuoteBasket` returned for the basket **at render
time**. If checkout computed something else, one of the two saw a different
basket, which is the entitlement being perishable working correctly.

Check, in order:

1. Did the basket change between the two? Compare line quantities and unit
   amounts, not the total.
2. Is anything reading the host's legacy session key
   `['code', 'discount', 'coupon_id']`? That number is stale by construction.
   This package never writes it and never reads it; a surface that does is the
   bug.
3. Is any caller re-deriving the per-line split instead of using
   `Entitlement::allocationByLine()`? A pro-rata re-derivation disagrees with
   largest-remainder by up to a penny per line.

## A merchant paused an offer and shoppers still see it

They should not, on the next request. The component re-quotes every time; there
is no cache to clear here and no cache to blame. If it persists, the offer's
`status` is still `active` — look at the domain's status decision log
(`promotions_offer_status_decisions`), which records who changed it and when.

## Changing the refusal wording

It lives in the domain, as `CodeRefused::MESSAGE`, and there is exactly one of
it. Change it there and both this surface and the `-api` change together. A
domain test asserts all thirteen reasons produce one distinct message; if that
test fails after your edit, you have added a second message.
