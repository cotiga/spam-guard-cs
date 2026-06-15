<?php

namespace Cotiga\SpamGuard\Filament\Resources\BannedEmails\Pages;

use Cotiga\SpamGuard\Filament\Resources\BannedEmails\SGBannedEmailResource;
use Filament\Resources\Pages\ListRecords;

class ListSGBannedEmails extends ListRecords
{
    protected static string $resource = SGBannedEmailResource::class;
}
