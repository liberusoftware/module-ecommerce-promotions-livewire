<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Promotions\Livewire\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View as ViewFactory;
use Liberu\Ecommerce\Promotions\Actions\QuoteBasket;
use Liberu\Ecommerce\Promotions\Data\AppliedOffer;
use Liberu\Ecommerce\Promotions\Data\Basket;
use Liberu\Ecommerce\Promotions\Data\Entitlement;
use Liberu\Ecommerce\Promotions\Data\Money;
use Liberu\Ecommerce\Promotions\Exceptions\CodeRefused;
use Liberu\Ecommerce\Promotions\Livewire\Contracts\ResolvesShopperBasket;
use Liberu\Ecommerce\Promotions\Models\Code;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * The shopper's promotion code field, and what the applied offers took off.
 *
 * Three rules shape every line of this class.
 *
 * **It holds no computed discount.** The entitlement is derived on each request
 * and thrown away; the only thing carried between requests is the list of codes
 * the shopper presented. That is the host's §2.9 fault answered at the surface:
 * `CartController::applyCoupon()` writes `['code', 'discount', 'coupon_id']` into
 * the session, two checkout paths each had to learn independently to ignore that
 * `discount`, and the stale copy is still sitting there for a third surface to
 * read. This is not that third surface.
 *
 * **It holds no money value as a client input.** A Livewire component round-trips
 * its public properties through the browser, so a basket held as a property is a
 * basket the shopper can re-price. It holds an opaque reference and the server
 * prices it, through {@see ResolvesShopperBasket}, on every request.
 *
 * **Every refusal is the same answer.** Unknown code, expired offer, exhausted
 * offer, one this shopper has already used, a minimum not met, an offer whose
 * eligibility seam is unbound — all of them render
 * {@see CodeRefused::MESSAGE} and nothing else. The domain publishes a
 * machine-readable reason for each; it is merchant-facing, and reaching for it
 * here would turn this field into an oracle for which codes exist.
 *
 * That last rule is also why nothing public on this class returns an
 * `Entitlement`. Livewire sends an action's return value back to the browser, so
 * a public method handing back the entitlement would let a crafted request read
 * `skipped` and `refusedCodes` — the merchant-only half — without ever rendering
 * the view.
 */
final class BasketPromotions extends Component
{
    /** The merchant whose offers are evaluated. Never a client input. */
    #[Locked]
    public string $tenantId = '';

    /**
     * An opaque handle the host resolves to a basket. It is not a basket, it
     * carries no amounts, and this package never interprets it.
     */
    #[Locked]
    public string $basketRef = '';

    /**
     * The codes the shopper has presented, normalised by the domain.
     *
     * A list of codes is not an entitlement: it is what the shopper typed, and
     * every one of them is re-evaluated from scratch on every quote. A code that
     * stops applying because the basket shrank stays in the list and stops
     * appearing in the display, which is what perishable means.
     *
     * @var list<string>
     */
    #[Locked]
    public array $appliedCodes = [];

    /** The one writable property on this component. */
    #[Validate('required|string|max:64')]
    public string $code = '';

    /**
     * Per-request memo, deliberately not a public property: private state is not
     * serialised to the browser and does not survive the response, which is the
     * only guarantee that stops it becoming the cached discount this component
     * exists to avoid.
     */
    private ?Entitlement $quote = null;

    private bool $quoted = false;

    public function mount(string $tenantId, string $basketRef): void
    {
        $this->tenantId = $tenantId;
        $this->basketRef = $basketRef;
    }

    /**
     * Present a code.
     *
     * The candidate is quoted *with* the codes already presented, because whether
     * it lands can depend on them — an exclusive offer already applying is a
     * refusal for everything else. It is kept only if the domain honoured it.
     */
    public function apply(): void
    {
        $this->validate();

        $candidate = $this->normalise($this->code);

        // Cleared on every outcome, refusal included: leaving the rejected code
        // in the field is the one visible difference between one refusal and
        // another, and they must be indistinguishable.
        $this->code = '';

        $entitlement = $this->requote([...$this->appliedCodes, $candidate]);

        if ($entitlement === null || ! in_array($candidate, $entitlement->honouredCodes, true)) {
            $this->quoted = false;
            $this->addError('code', CodeRefused::MESSAGE);

            return;
        }

        $this->appliedCodes = array_values(array_unique([...$this->appliedCodes, $candidate]));
    }

    /** Withdraw a presented code. */
    public function remove(string $code): void
    {
        $normalised = $this->normalise($code);

        $this->appliedCodes = array_values(array_filter(
            $this->appliedCodes,
            static fn (string $presented): bool => $presented !== $normalised,
        ));

        $this->quoted = false;
    }

    /**
     * The basket changed, so the entitlement did.
     *
     * An entitlement is perishable and is never cached, so there is nothing here
     * to invalidate — re-rendering re-quotes. The listener exists because a
     * change made by the host's cart component would otherwise leave this one
     * showing a reduction the basket no longer earns.
     *
     * Not named `refresh()`: that is a public method on Livewire's own component,
     * and narrowing it is a fatal at class load rather than a test failure.
     */
    #[On('basket-updated')]
    public function refreshEntitlement(): void
    {
        $this->quoted = false;
    }

    public function render(): View
    {
        $entitlement = $this->entitlement();

        return ViewFactory::make('ecommerce-promotions-livewire::basket-promotions', [
            'offers' => $this->offers($entitlement),
            'lines' => $this->lines($entitlement),
            'shipping' => $this->shipping($entitlement),
            'total' => $this->total($entitlement),
        ]);
    }

    /**
     * What each applied offer took off.
     *
     * The offer's own name is the shopper-facing description a merchant wrote.
     * Nothing else about the offer is published here.
     *
     * @return list<array{name: string, amount: Money}>
     */
    private function offers(?Entitlement $entitlement): array
    {
        if ($entitlement === null) {
            return [];
        }

        return array_map(
            fn (AppliedOffer $offer): array => [
                'name' => $offer->offerName,
                'amount' => Money::fromMinor($offer->totalMinor(), $entitlement->currency, $entitlement->currencyExponent),
            ],
            $entitlement->applied,
        );
    }

    /**
     * How much reduction landed on each line.
     *
     * Taken from the domain's published allocation, which sums to the
     * entitlement's total exactly by largest remainder. Re-deriving it here — a
     * pro-rata split of the total, say — is how a line total comes to disagree
     * with an order total by a penny forever.
     *
     * @return list<array{lineRef: string, amount: Money}>
     */
    private function lines(?Entitlement $entitlement): array
    {
        if ($entitlement === null) {
            return [];
        }

        $lines = [];

        foreach ($entitlement->allocationByLine() as $lineRef => $amountMinor) {
            $lines[] = [
                'lineRef' => $lineRef,
                'amount' => Money::fromMinor($amountMinor, $entitlement->currency, $entitlement->currencyExponent),
            ];
        }

        return $lines;
    }

    /**
     * Published separately from the lines, never folded into them: shipping is
     * taxed differently and refunded differently from goods.
     */
    private function shipping(?Entitlement $entitlement): ?Money
    {
        if ($entitlement === null || $entitlement->shippingReductionMinor() < 1) {
            return null;
        }

        return Money::fromMinor($entitlement->shippingReductionMinor(), $entitlement->currency, $entitlement->currencyExponent);
    }

    /** Whatever the applied offers came to, or null when nothing was reduced. */
    private function total(?Entitlement $entitlement): ?Money
    {
        if ($entitlement === null || $entitlement->totalMinor() < 1) {
            return null;
        }

        return $entitlement->total();
    }

    private function entitlement(): ?Entitlement
    {
        if (! $this->quoted) {
            $this->requote($this->appliedCodes);
        }

        return $this->quote;
    }

    /**
     * Evaluate the offers against the basket as it is now. Writes nothing.
     *
     * @param  list<string>  $codes
     */
    private function requote(array $codes): ?Entitlement
    {
        $basket = $this->basket();

        $this->quote = $basket === null
            ? null
            : App::make(QuoteBasket::class)($this->tenantId, $basket, $codes);
        $this->quoted = true;

        return $this->quote;
    }

    /**
     * The seam is optional. Unbound, or unable to resolve the reference, there is
     * no basket to quote and the component renders nothing — the blast radius of
     * an absent seam is the scope of what it controls, and this one controls only
     * this component.
     */
    private function basket(): ?Basket
    {
        if (! App::bound(ResolvesShopperBasket::class)) {
            return null;
        }

        return App::make(ResolvesShopperBasket::class)->basketFor($this->tenantId, $this->basketRef);
    }

    /**
     * Normalisation belongs to the domain, which publishes it and returns
     * normalised codes in `honouredCodes`. Restating it here as
     * `strtoupper(trim(...))` would be a rule of this package's own, and one that
     * silently stops matching the day the domain's changes.
     */
    private function normalise(string $code): string
    {
        return Code::normalise($code);
    }
}
