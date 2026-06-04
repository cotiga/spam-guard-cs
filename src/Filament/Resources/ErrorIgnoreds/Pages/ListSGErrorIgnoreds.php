<?php

namespace Cotiga\SpamGuard\Filament\Resources\ErrorIgnoreds\Pages;

use Cotiga\SpamGuard\Filament\Resources\ErrorIgnoreds\SGErrorIgnoredResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSGErrorIgnoreds extends ListRecords
{
    protected static string $resource = SGErrorIgnoredResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
