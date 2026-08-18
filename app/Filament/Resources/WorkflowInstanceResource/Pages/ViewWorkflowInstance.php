<?php

namespace App\Filament\Resources\WorkflowInstanceResource\Pages;

use App\Filament\Resources\WorkflowInstanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkflowInstance extends ViewRecord
{
    protected static string $resource = WorkflowInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
