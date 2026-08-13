<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Promotions\Livewire\Contracts;

use Liberu\Ecommerce\Promotions\Data\Basket;

/**
 * Turns an opaque basket reference into the basket the domain evaluates.
 *
 * This seam exists because **no money value is ever a client input**. A Livewire
 * component persists its public properties to the browser and back, so a basket
 * held as a property would hand the shopper the unit amounts and let them edit
 * them. The component holds a reference; the host prices it, on the server, on
 * every request.
 *
 * It is also the reason this package imports no cart: Promotions is *told* the
 * basket and never fetches one, and a surface that could read a cart would
 * eventually decide what is in it.
 *
 * **Optional and unbound by default.** Its blast radius is the whole component —
 * with no basket there is nothing to quote — so its absence removes the display
 * and refuses presented codes with the one message. It does not fail the page a
 * storefront put the component on.
 */
interface ResolvesShopperBasket
{
    /**
     * The basket that reference names, or null when it names nothing this
     * shopper may price — an expired basket, another tenant's, a forgery.
     */
    public function basketFor(string $tenantId, string $basketRef): ?Basket;
}
