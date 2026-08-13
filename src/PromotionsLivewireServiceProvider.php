<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Promotions\Livewire;

use Illuminate\Support\ServiceProvider;
use Liberu\Ecommerce\Promotions\Livewire\Components\BasketPromotions;
use Liberu\Ecommerce\Promotions\Livewire\Contracts\ResolvesShopperBasket;
use Livewire\Component;
use Livewire\Livewire;

/**
 * Registers this package's views and its Livewire components, and binds nothing
 * else.
 *
 * It is declared in `module.json` and **not** in `extra.laravel.providers`:
 * Composer installing this package must boot nothing. Enablement is the host's
 * explicit decision, made by naming the module in `MODULES_ENABLED`.
 *
 * {@see ResolvesShopperBasket} is deliberately unbound. A host that does not bind
 * it gets a component that renders no reduction and refuses every code, rather
 * than a page that fails.
 */
class PromotionsLivewireServiceProvider extends ServiceProvider
{
    /**
     * The registered component names. Also what the reflection test in
     * `tests/Feature/PropertyPartitionTest.php` walks — the partition rule is
     * enforced over every component this package registers, not over a list
     * somebody remembered to update.
     *
     * @var array<string, class-string<Component>>
     */
    public const COMPONENTS = [
        'ecommerce-promotions::basket-promotions' => BasketPromotions::class,
    ];

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ecommerce-promotions-livewire');

        /*
         * `resolveMissingComponent()`, not `addNamespace()`.
         *
         * Livewire 4's `Finder::resolveClassComponentClassName()` splits a name
         * on `::` and, for a namespaced one, consults `classNamespaces` and
         * returns null — it never reaches the explicit registry that
         * `Livewire::component()` writes to. So a namespaced name registered
         * that way is unreachable. `addNamespace()` does reach it, but maps one
         * component namespace onto exactly one PHP namespace, which decides
         * class naming for every component this package will ever ship.
         *
         * Deferred until the factory is first resolved, so this provider does
         * not depend on booting after Livewire's. The host registers modules
         * from `MODULES_ENABLED` and makes no promise about the order.
         */
        $this->callAfterResolving('livewire.factory', static function (): void {
            Livewire::resolveMissingComponent(
                static fn (string $name): ?string => self::COMPONENTS[$name] ?? null,
            );
        });
    }
}
