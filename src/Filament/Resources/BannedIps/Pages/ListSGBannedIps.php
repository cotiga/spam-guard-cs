<?php

namespace Cotiga\SpamGuard\Filament\Resources\BannedIps\Pages;

use Cotiga\SpamGuard\Filament\Resources\BannedIps\SGBannedIpResource;
use Filament\Resources\Pages\ListRecords;

class ListSGBannedIps extends ListRecords
{
    protected static string $resource = SGBannedIpResource::class;
}
