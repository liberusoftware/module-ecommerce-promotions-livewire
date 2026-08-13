<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\Promotions\Actions\QuoteBasket;
use Liberu\Ecommerce\Promotions\Data\Basket;
use Liberu\Ecommerce\Promotions\Data\Money;
use Liberu\Ecommerce\Promotions\Enums\RefusalReason;
use Liberu\Ecommerce\Promotions\Exceptions\CodeRefused;

/*
 * The refusal is one message.
 *
 * Six different things go wrong below and a shopper cannot tell which. That is
 * addendum §5.8, and it is a security decision rather than a UX one: a
 * distinguishable refusal is an oracle for which codes exist. The host broke it
 * in the one place it had identified the threat — `/cart/apply-coupon` was
 * throttled against enumeration by a route comment saying so, while returning
 * three distinguishable refusals, the third of which printed the coupon's
 * configured minimum spend to a caller who did not hold it.
 *
 * Every case is set up through the domain's own actions, so each is a real
 * refusal rather than a mocked one. Each gets its own merchant, because the
 * domain scopes offers and codes by tenant and six scenarios in one test would
 * otherwise see each other's offers.
 *
 * @return array<string, callable(string): array{0: string, 1: Basket}>
 */
function refusalScenarios(): array
{
    return [
        'an unknown code' => function (string $tenant): array {
            return ['NEVER-ISSUED', bindBasket(basket())];
        },
        'an offer that has ended' => function (string $tenant): array {
            offerWithCode(terms: percentageTerms(overrides: ['endsAt' => CarbonImmutable::now()->subDay()]), tenantId: $tenant);

            return ['SAVE20', bindBasket(basket())];
        },
        'an offer whose uses are spent' => function (string $tenant): array {
            $basket = bindBasket(basket());
            $offer = offerWithCode(terms: percentageTerms(overrides: ['maxRedemptions' => 1]), tenantId: $tenant);
            redeem($offer, $basket, tenantId: $tenant);

            return ['SAVE20', $basket];
        },
        'an offer this shopper has already used' => function (string $tenant): array {
            $basket = bindBasket(basket(customerRef: 'cus_1'));
            $offer = offerWithCode(terms: percentageTerms(overrides: ['maxRedemptionsPerCustomer' => 1]), tenantId: $tenant);
            redeem($offer, $basket, tenantId: $tenant);

            return ['SAVE20', $basket];
        },
        'a minimum the basket does not meet' => function (string $tenant): array {
            offerWithCode(terms: percentageTerms(overrides: ['minimumSubtotal' => Money::fromMinor(100_00, 'GBP')]), tenantId: $tenant);

            return ['SAVE20', bindBasket(basket())];
        },
        'an offer whose eligibility seam is unbound' => function (string $tenant): array {
            offerWithCode(terms: percentageTerms(overrides: ['customerGroupRefs' => ['vip']]), tenantId: $tenant);

            return ['SAVE20', bindBasket(basket(customerRef: 'cus_1'))];
        },
    ];
}

/** @return list<array{0: string}> */
function refusalNames(): array
{
    return array_map(static fn (string $name): array => [$name], array_keys(refusalScenarios()));
}

it('answers every refusal with the domain\'s one message', function (string $scenario) {
    [$code] = refusalScenarios()[$scenario](TENANT);

    $component = component()->set('code', $code)->call('apply');

    expect($component->errors()->get('code'))->toBe([CodeRefused::MESSAGE])
        ->and($component->get('appliedCodes'))->toBe([])
        ->and($component->get('code'))->toBe('');
})->with(refusalNames());

it('renders every refusal identically, byte for byte', function () {
    $rendered = [];
    $merchant = 0;

    foreach (refusalScenarios() as $scenario => $arrange) {
        $tenant = 'merchant-'.++$merchant;
        [$code] = $arrange($tenant);

        $rendered[$scenario] = visibleDom(component($tenant)->set('code', $code)->call('apply'));
    }

    expect(array_unique(array_values($rendered)))->toHaveCount(
        1,
        'A shopper can tell these refusals apart: '.implode(' | ', array_keys($rendered)),
    );

    expect(reset($rendered))->toContain(CodeRefused::MESSAGE);
});

/*
 * The counterpart, and the test that stops the one above being vacuous: the
 * domain really did distinguish all six. Without this, "they all render the
 * same" would also pass on a suite where all six were the same failure.
 */
it('collapses six genuinely different domain reasons', function () {
    $reasons = [];
    $merchant = 0;

    foreach (refusalScenarios() as $scenario => $arrange) {
        $tenant = 'merchant-'.++$merchant;
        [$code, $basket] = $arrange($tenant);

        $reasons[$scenario] = app(QuoteBasket::class)($tenant, $basket, [$code])->refusedCodes[$code] ?? null;
    }

    expect(array_values($reasons))->toBe([
        RefusalReason::UnknownCode,
        RefusalReason::Ended,
        RefusalReason::Exhausted,
        RefusalReason::CustomerLimitReached,
        RefusalReason::MinimumNotMet,
        RefusalReason::EligibilityUnresolvable,
    ]);
});

it('never renders a machine-readable refusal reason', function (string $scenario) {
    [$code] = refusalScenarios()[$scenario](TENANT);

    $html = strtolower(component()->set('code', $code)->call('apply')->html());

    foreach (RefusalReason::cases() as $reason) {
        expect($html)->not->toContain($reason->value);
    }
})->with(refusalNames());

/*
 * Presentation-brief §5, generalised past idempotency: classify every failure as
 * resubmittable or spent, and never invite a retry on something permanent. A
 * refusal here is spent — nothing in this domain is transient and there is no
 * 423 — so "try again shortly" would be a lie the UI tells on the domain's
 * behalf.
 */
it('never invites a retry on a permanent refusal', function () {
    expect(strtolower(CodeRefused::MESSAGE))
        ->not->toContain('try again')
        ->not->toContain('shortly')
        ->not->toContain('later');
});

it('refuses a code when the basket seam is unbound', function () {
    offerWithCode();

    $component = component()->set('code', 'SAVE20')->call('apply');

    expect($component->errors()->get('code'))->toBe([CodeRefused::MESSAGE])
        ->and($component->get('appliedCodes'))->toBe([]);
});

it('refuses a code when the basket reference resolves to nothing', function () {
    bindBasket(null);
    offerWithCode();

    $component = component()->set('code', 'SAVE20')->call('apply');

    expect($component->errors()->get('code'))->toBe([CodeRefused::MESSAGE]);
});

it('reports an empty field as a validation failure rather than a refusal', function () {
    bindBasket(basket());

    $component = component()->set('code', '')->call('apply');

    expect($component->errors()->get('code'))->not->toBe([CodeRefused::MESSAGE])
        ->and($component->errors()->get('code'))->not->toBeEmpty();
});
