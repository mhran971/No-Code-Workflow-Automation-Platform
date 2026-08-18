<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkflowInstanceResource\Pages;
use Filament\Actions;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Workflows\Enums\TriggerType;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Models\WorkflowInstance;

class WorkflowInstanceResource extends Resource
{
    protected static ?string $model = WorkflowInstance::class;

    protected static ?string $modelLabel = 'Workflow Run';

    protected static ?string $pluralModelLabel = 'Workflow Runs';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-play-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Workflow Operations';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Run #')
                    ->sortable(),
                Tables\Columns\TextColumn::make('workflow.name')
                    ->label('Workflow')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('tenant.business_name')
                    ->label('Tenant')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (WorkflowInstanceStatus|string|null $state): string => match ($state instanceof WorkflowInstanceStatus ? $state->value : (string) $state) {
                        WorkflowInstanceStatus::Completed->value => 'success',
                        WorkflowInstanceStatus::Running->value => 'primary',
                        WorkflowInstanceStatus::Pending->value => 'gray',
                        WorkflowInstanceStatus::Failed->value => 'danger',
                        WorkflowInstanceStatus::Paused->value => 'warning',
                        WorkflowInstanceStatus::Cancelled->value => 'slate',
                        default => 'secondary',
                    }),
                Tables\Columns\TextColumn::make('trigger_type')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => $state instanceof TriggerType ? ucfirst($state->value) : ucfirst((string) $state)),
                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime('M d, Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('finished_at')
                    ->dateTime('M d, Y H:i:s')
                    ->sortable()
                    ->placeholder('Running...'),
                Tables\Columns\TextColumn::make('duration')
                    ->state(function (WorkflowInstance $record): string {
                        if (! $record->started_at) {
                            return 'Not started';
                        }
                        $end = $record->finished_at ?? now();
                        $seconds = $record->started_at->diffInSeconds($end);
                        if ($seconds < 60) {
                            return "{$seconds}s";
                        }
                        $minutes = floor($seconds / 60);
                        $remainingSeconds = $seconds % 60;
                        return "{$minutes}m {$remainingSeconds}s";
                    })
                    ->badge()
                    ->color('gray'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        WorkflowInstanceStatus::Running->value => 'Running',
                        WorkflowInstanceStatus::Completed->value => 'Completed',
                        WorkflowInstanceStatus::Failed->value => 'Failed',
                        WorkflowInstanceStatus::Paused->value => 'Paused',
                        WorkflowInstanceStatus::Cancelled->value => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'business_name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('trigger_type')
                    ->options([
                        TriggerType::Manual->value => 'Manual',
                        TriggerType::Form->value => 'Form',
                        TriggerType::Webhook->value => 'Webhook',
                        TriggerType::SubWorkflow->value => 'Sub-Workflow',
                    ]),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-stop-circle')
                    ->color('danger')
                    ->visible(fn (WorkflowInstance $record): bool => in_array($record->status, [WorkflowInstanceStatus::Running, WorkflowInstanceStatus::Pending]))
                    ->requiresConfirmation()
                    ->action(function (WorkflowInstance $record): void {
                        $record->status = WorkflowInstanceStatus::Cancelled;
                        $record->finished_at = now();
                        $record->save();

                        Notification::make()
                            ->title('Execution cancelled')
                            ->body("Run #{$record->id} has been cancelled.")
                            ->warning()
                            ->send();
                    }),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Run Execution Information')
                    ->components([
                        Infolists\Components\TextEntry::make('id')
                            ->label('Execution ID')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('workflow.name')
                            ->label('Workflow Name')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('tenant.business_name')
                            ->label('Tenant')
                            ->badge()
                            ->color('info'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge(),
                        Infolists\Components\TextEntry::make('trigger_type')
                            ->label('Triggered Via')
                            ->badge(),
                        Infolists\Components\TextEntry::make('correlation_id')
                            ->label('Correlation ID')
                            ->placeholder('N/A'),
                        Infolists\Components\TextEntry::make('started_at')
                            ->dateTime('F d, Y H:i:s'),
                        Infolists\Components\TextEntry::make('finished_at')
                            ->dateTime('F d, Y H:i:s')
                            ->placeholder('In progress'),
                        Infolists\Components\TextEntry::make('error')
                            ->label('Failure Diagnostics')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : (string) $state)
                            ->visible(fn (WorkflowInstance $record): bool => ! empty($record->error))
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkflowInstances::route('/'),
            'view' => Pages\ViewWorkflowInstance::route('/{record}'),
        ];
    }
}
