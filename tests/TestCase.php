<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Promotions\Livewire\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Liberu\Ecommerce\Promotions\PromotionsServiceProvider;
use Liberu\PackageTestbench\PackageTestCase;

/**
 * The package's own case.
 *
 * The domain package is a runtime `require` and **not** a `require-dev` as well.
 * `PackageTestCase::getPackageProviders()` boots the manifest provider of a
 * sibling only when it is a dev requirement, so the domain provider — which is
 * what loads its migrations — is named here instead. Declaring the package twice
 * would achieve the same thing and make `composer validate` warn, and `Install`
 * runs `composer validate`.
 *
 * Livewire's own provider needs no naming: it ships `extra.laravel.providers`, so
 * the parent finds it through the direct requirement.
 *
 * **No seam is bound here by default** — not this package's basket seam, nor
 * either of the domain's. A test that wants one binds it on purpose, so "an
 * unbound seam removes this component and nothing else" is proved by building
 * against absence rather than asserted in a document.
 */
abstract class TestCase extends PackageTestCase
{
    use RefreshDatabase;

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return array_values(array_unique([
            PromotionsServiceProvider::class,
            ...parent::getPackageProviders($app),
        ]));
    }
}
