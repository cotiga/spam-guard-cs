<?php

namespace Cotiga\SpamGuard\Filament\Resources\ErrorIgnoreds\Pages;

use Cotiga\SpamGuard\Filament\Resources\ErrorIgnoreds\SGErrorIgnoredResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSGErrorIgnored extends CreateRecord
{
    protected static string $resource = SGErrorIgnoredResource::class;
}
