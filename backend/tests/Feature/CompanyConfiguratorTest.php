<?php

use App\Modules\Organization\Services\CompanyConfigurator;

function configure(App\Modules\Organization\Models\Company $c, array $types, ?string $primary = null, ?string $tpl = null)
{
    return app(CompanyConfigurator::class)->apply($c, $types, $primary, $tpl);
}

it('enables manufacturing-specific modules for a manufacturer', function () {
    $c = makeCompany('MFG');
    configure($c, ['manufacturing']);

    $enabled = $c->enabledModuleKeys();
    expect($enabled)->toContain('inventory', 'production', 'job_work', 'accounting', 'vat');
});

it('disables inventory/production for a pure service company', function () {
    $c = makeCompany('SVC');
    configure($c, ['service']);

    $enabled = $c->enabledModuleKeys();
    expect($enabled)->not->toContain('inventory')
        ->and($enabled)->not->toContain('production')
        ->and($enabled)->not->toContain('job_work');
    // Core modules still present.
    expect($enabled)->toContain('accounting', 'sales', 'vat', 'dashboard');
});

it('enables inventory for a trading company but not production', function () {
    $c = makeCompany('TRD');
    configure($c, ['trading']);

    $enabled = $c->enabledModuleKeys();
    expect($enabled)->toContain('inventory')
        ->and($enabled)->not->toContain('production');
});

it('merges modules for a hybrid manufacturing + trading company', function () {
    $c = makeCompany('HYB');
    configure($c, ['manufacturing', 'trading'], 'manufacturing');

    $enabled = $c->enabledModuleKeys();
    expect($enabled)->toContain('inventory', 'production', 'job_work');
    expect($c->primaryBusinessType())->toBe('manufacturing');
});

it('rejects an unknown business type', function () {
    $c = makeCompany('BAD');
    expect(fn () => configure($c, ['mining']))->toThrow(InvalidArgumentException::class);
});

it('rejects a primary type not in the enabled set', function () {
    $c = makeCompany('BAD2');
    expect(fn () => configure($c, ['service'], 'manufacturing'))->toThrow(InvalidArgumentException::class);
});
