<?php

declare(strict_types=1);

use Liberu\Ecommerce\Promotions\Livewire\PromotionsLivewireServiceProvider;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;

/*
 * Every public property of every registered component is either `#[Locked]` or
 * `#[Validate]` — never both, never neither.
 *
 * An unmarked property is a client-controlled input nobody decided about. The
 * rule is not about immobility: a shopper types a code and that has to arrive
 * somehow. It is about no property being writable by accident, which is why the
 * writable set is named here and asserted rather than described in a comment.
 */

/** @return list<array{0: string}> */
function registeredComponents(): array
{
    return array_map(
        static fn (string $class): array => [$class],
        array_values(PromotionsLivewireServiceProvider::COMPONENTS),
    );
}

it('marks every public property either locked or validated, never both', function (string $class) {
    $properties = (new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC);

    expect($properties)->not->toBeEmpty();

    foreach ($properties as $property) {
        $locked = $property->getAttributes(Locked::class) !== [];
        $validated = $property->getAttributes(Validate::class) !== [];

        expect($locked || $validated)->toBeTrue("[{$class}::\${$property->getName()}] is neither locked nor validated.");
        expect($locked && $validated)->toBeFalse("[{$class}::\${$property->getName()}] is both locked and validated.");
    }
})->with(registeredComponents());

it('exposes exactly one writable property, the code the shopper types', function () {
    $writable = array_values(array_map(
        static fn (ReflectionProperty $property): string => $property->getName(),
        array_filter(
            (new ReflectionClass(PromotionsLivewireServiceProvider::COMPONENTS[COMPONENT]))->getProperties(ReflectionProperty::IS_PUBLIC),
            static fn (ReflectionProperty $property): bool => $property->getAttributes(Validate::class) !== [],
        ),
    ));

    expect($writable)->toBe(['code']);
});

/*
 * `#[Locked]` at runtime, not only in reflection.
 *
 * The class-string is one that really autoloads — `toThrow()` given a class that
 * does not silently degrades to a message-substring check, which passes while
 * asserting nothing about the type.
 */
it('refuses a client write to a locked property', function (string $property, string $value) {
    bindBasket(basket());

    component()->set($property, $value);
})
    ->throws(CannotUpdateLockedPropertyException::class)
    ->with([
        ['tenantId', 'some-other-merchant'],
        ['basketRef', 'somebody-elses-basket'],
        ['appliedCodes', 'SAVE20'],
    ]);

/*
 * The state that survives a request, named. This is the §2.9 assertion: the host
 * writes `['code', 'discount', 'coupon_id']` into the session and two checkout
 * paths each had to learn to ignore that `discount`. Nothing here holds a
 * discount, a total, an allocation or a basket, so there is nothing for a third
 * surface to read stale.
 */
it('carries no money and no computed discount between requests', function () {
    bindBasket(basket());
    offerWithCode();

    $component = component()->set('code', 'SAVE20')->call('apply');

    expect(array_keys($component->snapshot['data']))->toBe(['tenantId', 'basketRef', 'appliedCodes', 'code'])
        ->and($component->get('appliedCodes'))->toBe(['SAVE20']);
});
