# Adopting this package

## 1. Composer

The domain package this depends on is **not on Packagist**. Composer honours
`repositories` only from the root manifest, so the entry this package carries for
its own CI does nothing for you. Add the same one to the host's `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-promotions" },
    { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-promotions-livewire" }
]
```

```bash
composer require liberusoftware/ecommerce-promotions-livewire
```

## 2. Enablement

Installing boots nothing. This package ships no `extra.laravel.providers`; the
host's `ModuleManagerServiceProvider` globs `config('modules.paths')` for
`*/module.json` and registers only what `MODULES_ENABLED` names.

```dotenv
MODULES_ENABLED=ecommerce-promotions,ecommerce-promotions-livewire
```

The domain module must be enabled too — it owns the tables and the evaluation.

## 3. There are no migrations here

This package ships none and owns no table. Run the domain package's migrations;
see its own `docs/adoption.md`.

## 4. Bind the basket seam

Nothing renders until the host says what a basket reference means. This is the
one thing you must write.

```php
use Liberu\Ecommerce\Promotions\Data\Basket;
use Liberu\Ecommerce\Promotions\Data\BasketLine;
use Liberu\Ecommerce\Promotions\Livewire\Contracts\ResolvesShopperBasket;

final class CartBasketSource implements ResolvesShopperBasket
{
    public function basketFor(string $tenantId, string $basketRef): ?Basket
    {
        $cart = Cart::query()
            ->where('tenant_id', $tenantId)   // scope it, always
            ->where('reference', $basketRef)
            ->first();

        if ($cart === null) {
            return null;
        }

        return new Basket(
            currency: $cart->currency,
            lines: $cart->items->map(fn ($item): BasketLine => new BasketLine(
                lineRef: (string) $item->id,
                productRef: (string) $item->product_id,
                quantity: $item->quantity,
                unitAmountMinor: $item->unit_amount_minor,
            ))->all(),
            shippingMinor: $cart->shipping_minor,
            customerRef: $cart->customer_id === null ? null : (string) $cart->customer_id,
        );
    }
}
```

```php
$this->app->bind(ResolvesShopperBasket::class, CartBasketSource::class);
```

**Scope it to the tenant, and return null rather than guessing.** The reference
arrives from a `#[Locked]` property, so Livewire will reject a client that
changes it mid-session — but the reference the component was mounted with came
from your own page, and it is your implementation that decides whether the person
holding it may price that basket.

Leave it unbound and the component still renders its field, refuses every code
with the one message, and displays no reduction. That is deliberate: an absent
seam removes this component and does not fail the page.

The domain's own two seams — `ResolvesCustomerEligibility` and
`ResolvesProductGrouping` — are separate and also optional. Unbound, the offers
that *name* a group, segment or collection do not apply; every other offer
evaluates normally, and the shopper sees the same one refusal message. See the
domain package's adoption notes.

## 5. Place the component

```blade
<livewire:ecommerce-promotions::basket-promotions
    :tenant-id="$tenant->id"
    :basket-ref="$cart->reference"
/>
```

Both arguments are required. Neither is a money value and neither is writable by
the client.

## 6. Tell it when the basket changes

An entitlement is perishable. This component re-quotes on every request it
handles, but a basket changed by *your* cart component is a request it never
sees. Emit the event from wherever the basket is mutated:

```php
$this->dispatch('basket-updated');
```

Nothing breaks without it — the next interaction with the field re-quotes anyway
— but until then the shopper sees a reduction the basket may no longer earn.

## 7. Do not read the session for a discount

If the host still writes `['code', 'discount', 'coupon_id']` into the session
from `CartController::applyCoupon()`, this component neither writes it nor reads
it. Do not wire it to. The number in that session key is the fault two checkout
paths were separately fixed to ignore, and the correct total is whatever
`QuoteBasket` returns for the basket as it is now.

## 8. Styling

The view ships unstyled and unclassed on purpose: a package cannot know your
design system, and a class list it invented would be one more thing to override.

There is nothing to publish. The view namespace is
`ecommerce-promotions-livewire`, and `loadViewsFrom()` registers the host's
vendor override path with it, so dropping your own
`resources/views/vendor/ecommerce-promotions-livewire/basket-promotions.blade.php`
replaces it. A publish command would only make a copy that stops tracking the
package.

**If you replace the view, keep these two properties**: render
`CodeRefused::MESSAGE` and never a `RefusalReason`, and print money through
`Money::decimal()` rather than formatting a minor-unit integer yourself. The
first is a security decision; the second is how a penny goes missing.
