<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Promotions\Livewire\Tests\Support;

use Liberu\Ecommerce\Promotions\Data\Basket;
use Liberu\Ecommerce\Promotions\Livewire\Contracts\ResolvesShopperBasket;

/**
 * A host's basket seam, standing in for whatever owns the cart.
 *
 * It answers for exactly one reference and returns null for every other, which
 * is the shape a real one has: a reference the shopper may not price resolves to
 * nothing rather than to somebody else's basket.
 */
final class StubBasketSource implements ResolvesShopperBasket
{
    public function __construct(
        private readonly ?Basket $basket,
        private readonly string $reference = 'basket-1',
    ) {}

    public function basketFor(string $tenantId, string $basketRef): ?Basket
    {
        return $basketRef === $this->reference ? $this->basket : null;
    }
}
