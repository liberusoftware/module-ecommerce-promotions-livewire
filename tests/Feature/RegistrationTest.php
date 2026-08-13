<?php

declare(strict_types=1);

use Liberu\Ecommerce\Promotions\Livewire\Components\BasketPromotions;
use Liberu\Ecommerce\Promotions\Livewire\PromotionsLivewireServiceProvider;
use Livewire\Livewire;

it('resolves every registered component by its namespaced name', function (string $name, string $class) {
    expect(Livewire::new($name))->toBeInstanceOf($class);
})->with(array_map(
    static fn (string $name, string $class): array => [$name, $class],
    array_keys(PromotionsLivewireServiceProvider::COMPONENTS),
    array_values(PromotionsLivewireServiceProvider::COMPONENTS),
));

/*
 * The registration mechanism, asserted rather than assumed.
 *
 * Livewire 4's `Finder::resolveClassComponentClassName()` splits a namespaced
 * name on `::`, consults `classNamespaces`, and returns null — it never reaches
 * the explicit registry `Livewire::component()` writes to. So the obvious
 * registration silently does not work for a name of this shape, and only
 * `resolveMissingComponent()` does. If a Livewire release ever makes the direct
 * lookup work, this test fails and the provider can be simplified.
 */
it('cannot find a namespaced component through the class-component lookup alone', function () {
    expect(app('livewire.finder')->resolveClassComponentClassName('ecommerce-promotions::basket-promotions'))->toBeNull();
});

it('renders the component from a blade view this package ships', function () {
    bindBasket(basket());

    expect(component()->html())->toContain('Promotion code');
});

it('is the only component this package registers', function () {
    expect(PromotionsLivewireServiceProvider::COMPONENTS)->toBe([
        'ecommerce-promotions::basket-promotions' => BasketPromotions::class,
    ]);
});
