<?php

declare(strict_types=1);

/*
 * A source-level guard, because the rendering tests can only prove that the
 * merchant-facing half is absent from the paths they exercise.
 *
 * The domain publishes three things a shopper must never see: `RefusalReason`,
 * the `skipped` list on an entitlement, and the `refusedCodes` map. Together they
 * are an oracle for which codes exist. `Entitlement::toArray()` serialises all
 * three, which is why it is named here too — one `json_encode` of a whole
 * entitlement would undo every other test in this suite.
 *
 * `honouredCodes` is deliberately not on the list: it names codes the shopper
 * themselves presented and that applied, which is the answer they already have.
 */

/** @return list<array{0: string}> */
function shopperFacingSources(): array
{
    $files = [];

    foreach (['/../../src', '/../../resources/views'] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.$directory));

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = [$file->getPathname()];
            }
        }
    }

    return $files;
}

/**
 * The code, with the comments taken out.
 *
 * The comments have to go: this file's own explanation of what must not be
 * rendered names all three, and so does the component's. A guard that failed on
 * being explained would be deleted rather than fixed.
 */
function executableSource(string $path): string
{
    if (str_ends_with($path, '.php')) {
        return php_strip_whitespace($path);
    }

    return (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($path));
}

it('never reaches for the merchant-facing half of an entitlement', function (string $path) {
    $source = executableSource($path);

    foreach (['RefusalReason', '->skipped', 'refusedCodes', 'skipReasonFor', 'toArray()'] as $forbidden) {
        expect($source)->not->toContain($forbidden, "[{$path}] reaches for [{$forbidden}].");
    }
})->with(shopperFacingSources());

it('reads the sources it claims to', function () {
    expect(shopperFacingSources())->toHaveCount(4);
});
