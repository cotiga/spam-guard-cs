<?php

namespace Cotiga\SpamGuard;

use Filament\Contracts\Plugin;
use Filament\Panel;

class SpamGuardPlugin implements Plugin
{
    public function getId(): string
    {
        return 'spam-guard-cs';
    }

    public function register(Panel $panel): void
    {
        $panel->discoverResources(
            in: __DIR__.'/Filament/Resources',
            for: 'Cotiga\\SpamGuard\\Filament\\Resources'
        );
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }
}
