<?php

declare(strict_types=1);

use Liberu\Ecommerce\Promotions\Livewire\Components\BasketPromotions;
use Liberu\Ecommerce\Promotions\Livewire\Contracts\ResolvesShopperBasket;
use Livewire\Attributes\On;

/*
 * The seam, and the rule wave 12 states about seams.
 *
 * The blast radius of an unbound seam is the scope of the thing it controls.
 * This one controls the whole component, so its absence removes the component's
 * output — and nothing else. A storefront that has not bound it still serves the
 * page the component sits on.
 */

it('leaves the seam unbound by default', function () {
    expect(app()->bound(ResolvesShopperBasket::class))->toBeFalse();
});

it('renders the field, and nothing else, with no seam bound', function () {
    offerWithCode();

    $html = component()->html();

    expect($html)->toContain('Promotion code')
        ->and($html)->not->toContain('Total reduction');
});

it('never asks the seam for a basket the shopper did not name', function () {
    bindBasket(basket());
    offerWithCode();

    // A reference the seam does not answer for. Nothing is priced, and no other
    // shopper's basket is reachable by guessing.
    $html = component(basketRef: 'somebody-elses-basket')->set('code', 'SAVE20')->call('apply')->html();

    expect($html)->not->toContain('Twenty percent off');
});

/*
 * Perishability needs a nudge from outside: the entitlement is re-derived on
 * every request to this component, but a basket changed by the host's cart
 * component is a request this one never saw. The listener is what closes that.
 */
it('listens for the host announcing a basket change', function () {
    $attributes = (new ReflectionMethod(BasketPromotions::class, 'refreshEntitlement'))->getAttributes(On::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->event)->toBe('basket-updated');
});

/*
 * Trap 8, asserted rather than remembered. `refresh()` is a public method on
 * Livewire's own component and narrowing it is a fatal at class load, not a test
 * failure — so the listener is named something else, and this says so.
 */
it('does not shadow a livewire method with its own listener', function () {
    expect(method_exists(BasketPromotions::class, 'refresh'))->toBeFalse();
});
