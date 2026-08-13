<?php

declare(strict_types=1);

use Liberu\Ecommerce\Promotions\Actions\QuoteBasket;
use Liberu\Ecommerce\Promotions\Exceptions\CodeRefused;

it('keeps a code the domain honoured and shows what it took off', function () {
    bindBasket(basket());
    offerWithCode();

    $component = component()->set('code', 'SAVE20')->call('apply');

    expect($component->get('appliedCodes'))->toBe(['SAVE20'])
        ->and($component->get('code'))->toBe('')
        ->and($component->errors()->get('code'))->toBe([])
        ->and($component->html())
        ->toContain('Twenty percent off')
        ->toContain('10.00');
});

it('normalises the code the way the domain does, so casing is not a second answer', function () {
    bindBasket(basket());
    offerWithCode();

    $component = component()->set('code', '  save20 ')->call('apply');

    expect($component->get('appliedCodes'))->toBe(['SAVE20']);
});

/*
 * The allocation is the domain's, not a re-derivation.
 *
 * A caller that spreads the total across lines differently produces a line total
 * that disagrees with the order total by a penny, forever. The domain publishes
 * a per-line allocation summing to the total exactly, by largest remainder; this
 * surface renders it and adds nothing.
 */
it('renders the domain\'s own per-line allocation, summing to the total', function () {
    $basket = bindBasket(basket([
        ['line-1', 'prd_987654321', 1, 3333],
        ['line-2', 'prd_111111111', 1, 3333],
        ['line-3', 'prd_222222222', 1, 3334],
    ]));
    offerWithCode(terms: percentageTerms(3333));

    $entitlement = app(QuoteBasket::class)(TENANT, $basket, ['SAVE20']);
    $allocation = $entitlement->allocationByLine();

    expect(array_sum($allocation))->toBe($entitlement->totalMinor());

    $html = component()->set('code', 'SAVE20')->call('apply')->html();

    foreach ($allocation as $lineRef => $amountMinor) {
        expect($html)->toContain($lineRef);
    }

    expect($html)->toContain($entitlement->total()->decimal());
});

it('publishes a shipping reduction separately from the lines', function () {
    bindBasket(basket(shippingMinor: 499));
    offerWithCode(terms: freeShippingTerms());

    $html = component()->set('code', 'SAVE20')->call('apply')->html();

    expect($html)->toContain('Shipping reduced by 4.99');
});

it('withdraws a code the shopper removes', function () {
    bindBasket(basket());
    offerWithCode();

    $component = component()->set('code', 'SAVE20')->call('apply')->call('remove', 'save20');

    expect($component->get('appliedCodes'))->toBe([])
        ->and($component->html())->not->toContain('Twenty percent off');
});

it('presents a code once however many times it is applied', function () {
    bindBasket(basket());
    offerWithCode();

    $component = component()
        ->set('code', 'SAVE20')->call('apply')
        ->set('code', 'SAVE20')->call('apply');

    expect($component->get('appliedCodes'))->toBe(['SAVE20']);
});

it('shows nothing at all when the basket seam is unbound', function () {
    offerWithCode();

    $html = component()->html();

    expect($html)->toContain('Promotion code')
        ->and($html)->not->toContain('Twenty percent off')
        ->and($html)->not->toContain('Total reduction')
        ->and($html)->not->toContain(CodeRefused::MESSAGE);
});
