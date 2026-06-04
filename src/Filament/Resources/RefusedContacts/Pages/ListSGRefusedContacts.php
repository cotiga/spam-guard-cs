<?php

namespace Cotiga\SpamGuard\Filament\Resources\RefusedContacts\Pages;

use Cotiga\SpamGuard\Filament\Resources\RefusedContacts\SGRefusedContactResource;
use Filament\Resources\Pages\ListRecords;

class ListSGRefusedContacts extends ListRecords
{
    protected static string $resource = SGRefusedContactResource::class;
}
