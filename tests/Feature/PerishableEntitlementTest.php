<?php

declare(strict_types=1);

use Liberu\Ecommerce\Promotions\Data\Money;
use Liberu\Ecommerce\Promotions\Enums\OfferStatus;
use Liberu\Ecommerce\Promotions\Models\Offer;

/*
 * An entitlement is perishable and is never cached.
 *
 * The host learned exactly half of this: `CheckoutController` recomputes the
 * coupon against the live cart, correctly, and `CartController::applyCoupon()`
 * still writes `['code', 'discount', 'coupon_id']` into the session beside it.
 * Two surfaces have each solved that once; a third has nothing stopping it. This
 * is not the third.
 */

it('loses the reduction when the basket shrinks under it', function () {
    bindBasket(basket([['line-1', 'prd_987654321', 1, 5000]]));
    offerWithCode(terms: percentageTerms(overrides: ['minimumSubtotal' => Money::fromMinor(40_00, 'GBP')]));

    $component = component()->set('code', 'SAVE20')->call('apply');

    expect($component->html())->toContain('Twenty percent off');

    // The shopper removes an item somewhere else on the page, and the host's
    // cart component announces it.
    bindBasket(basket([['line-1', 'prd_987654321', 1, 1000]]));

    $component->dispatch('basket-updated');

    expect($component->html())->not->toContain('Twenty percent off')
        ->and($component->get('appliedCodes'))->toBe(['SAVE20']);
});

it('gains a reduction when the basket grows into it', function () {
    bindBasket(basket([['line-1', 'prd_987654321', 1, 1000]]));
    offerWithCode(terms: percentageTerms(overrides: ['minimumSubtotal' => Money::fromMinor(40_00, 'GBP')]));

    $component = component()->set('code', 'SAVE20')->call('apply');

    expect($component->get('appliedCodes'))->toBe([]);

    bindBasket(basket([['line-1', 'prd_987654321', 1, 5000]]));

    expect(component()->set('code', 'SAVE20')->call('apply')->html())->toContain('Twenty percent off');
});

it('stops showing a reduction the merchant paused, without being told', function () {
    bindBasket(basket());
    $offer = offerWithCode();

    $component = component()->set('code', 'SAVE20')->call('apply');

    expect($component->html())->toContain('Twenty percent off');

    Offer::query()->whereKey($offer->id)->update(['status' => OfferStatus::Paused]);

    expect($component->call('$refresh')->html())->not->toContain('Twenty percent off');
});
