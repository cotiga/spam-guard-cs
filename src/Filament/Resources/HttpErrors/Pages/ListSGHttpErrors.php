<?php

namespace Cotiga\SpamGuard\Filament\Resources\HttpErrors\Pages;

use Cotiga\SpamGuard\Filament\Resources\HttpErrors\SGHttpErrorResource;
use Filament\Resources\Pages\ListRecords;

class ListSGHttpErrors extends ListRecords
{
    protected static string $resource = SGHttpErrorResource::class;
}
