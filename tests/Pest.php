<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\Promotions\Actions\ClaimRedemption;
use Liberu\Ecommerce\Promotions\Actions\CreateOffer;
use Liberu\Ecommerce\Promotions\Actions\DecideOfferStatus;
use Liberu\Ecommerce\Promotions\Actions\IssueCode;
use Liberu\Ecommerce\Promotions\Actions\QuoteBasket;
use Liberu\Ecommerce\Promotions\Data\Basket;
use Liberu\Ecommerce\Promotions\Data\BasketLine;
use Liberu\Ecommerce\Promotions\Data\OfferTerms;
use Liberu\Ecommerce\Promotions\Enums\OfferStatus;
use Liberu\Ecommerce\Promotions\Enums\OfferStatusReason;
use Liberu\Ecommerce\Promotions\Enums\OfferTarget;
use Liberu\Ecommerce\Promotions\Enums\OfferType;
use Liberu\Ecommerce\Promotions\Enums\StackingMode;
use Liberu\Ecommerce\Promotions\Livewire\Contracts\ResolvesShopperBasket;
use Liberu\Ecommerce\Promotions\Livewire\Tests\Support\StubBasketSource;
use Liberu\Ecommerce\Promotions\Livewire\Tests\TestCase;
use Liberu\Ecommerce\Promotions\Models\Offer;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(TestCase::class)->in(__DIR__);

const TENANT = 'merchant-1';
const COMPONENT = 'ecommerce-promotions::basket-promotions';
const BASKET_REF = 'basket-1';

/**
 * Terms for a straightforward percentage-off-the-order offer.
 *
 * @param  array<string, mixed>  $overrides
 */
function percentageTerms(int $basisPoints = 2000, array $overrides = []): OfferTerms
{
    return new OfferTerms(
        name: $overrides['name'] ?? 'Twenty percent off',
        type: $overrides['type'] ?? OfferType::Percentage,
        target: $overrides['target'] ?? OfferTarget::Order,
        stacking: $overrides['stacking'] ?? StackingMode::Stackable,
        valueBasisPoints: $basisPoints,
        minimumSubtotal: $overrides['minimumSubtotal'] ?? null,
        customerGroupRefs: $overrides['customerGroupRefs'] ?? [],
        priority: $overrides['priority'] ?? 0,
        startsAt: $overrides['startsAt'] ?? null,
        endsAt: $overrides['endsAt'] ?? null,
        maxRedemptions: $overrides['maxRedemptions'] ?? null,
        maxRedemptionsPerCustomer: $overrides['maxRedemptionsPerCustomer'] ?? null,
    );
}

/** Terms for a free-shipping offer, which carries no value at all. */
function freeShippingTerms(): OfferTerms
{
    return new OfferTerms(
        name: 'Free delivery',
        type: OfferType::FreeShipping,
        target: OfferTarget::Shipping,
        stacking: StackingMode::Stackable,
    );
}

/** An offer that is live: created, then activated by a named actor. */
function activeOffer(OfferTerms $terms, string $tenantId = TENANT): Offer
{
    $offer = App::make(CreateOffer::class)($tenantId, $terms, 'staff-1');

    App::make(DecideOfferStatus::class)($tenantId, $offer->id, OfferStatus::Active, OfferStatusReason::MerchantActivated, 'staff-1');

    return $offer->refresh();
}

/** An active offer reachable by one code. */
function offerWithCode(string $code = 'SAVE20', ?OfferTerms $terms = null, string $tenantId = TENANT): Offer
{
    $offer = activeOffer($terms ?? percentageTerms(), $tenantId);

    App::make(IssueCode::class)($tenantId, $offer->id, $code);

    return $offer;
}

/**
 * A basket of lines given as `[lineRef, productRef, quantity, unit minor]`.
 *
 * The product references are ones nothing in any database has heard of, on
 * purpose: this package resolves none of them either.
 *
 * @param  list<array{0: string, 1: string, 2: int, 3: int}>  $lines
 */
function basket(array $lines = [['line-1', 'prd_987654321', 1, 5000]], int $shippingMinor = 0, ?string $customerRef = null): Basket
{
    return new Basket(
        currency: 'GBP',
        lines: array_map(static fn (array $line): BasketLine => new BasketLine(...$line), $lines),
        shippingMinor: $shippingMinor,
        customerRef: $customerRef,
    );
}

/** Bind the host's basket seam. Absent this call, the seam is unbound. */
function bindBasket(?Basket $basket): Basket
{
    App::instance(ResolvesShopperBasket::class, new StubBasketSource($basket));

    return $basket ?? basket();
}

/** Spend a use of an offer, through the domain, exactly as a checkout would. */
function redeem(Offer $offer, Basket $basket, string $code = 'SAVE20', string $tenantId = TENANT): void
{
    $entitlement = App::make(QuoteBasket::class)($tenantId, $basket, [$code]);
    $applied = $entitlement->appliedOffer($offer->id);

    expect($applied)->not->toBeNull();

    App::make(ClaimRedemption::class)($tenantId, $applied, 'ord_not_a_real_order', 'GBP', 2, $basket->customerRef);
}

function component(string $tenantId = TENANT, string $basketRef = BASKET_REF): Testable
{
    return Livewire::test(COMPONENT, ['tenantId' => $tenantId, 'basketRef' => $basketRef]);
}

/**
 * The visible DOM, with the parts Livewire generates per instance removed.
 *
 * A component id and its state snapshot differ between two `Livewire::test()`
 * calls whatever the component did, so comparing raw output would be comparing
 * fixtures. What is left is what a shopper sees.
 */
function visibleDom(Testable $component): string
{
    return (string) preg_replace(
        '/\s*wire:(?:id|snapshot|effects)="[^"]*"/',
        '',
        (string) $component->html(),
    );
}
