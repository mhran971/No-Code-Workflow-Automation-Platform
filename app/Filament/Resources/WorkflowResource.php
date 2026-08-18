<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkflowResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;

class WorkflowResource extends Resource
{
    protected static ?string $model = Workflow::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Workflow Operations';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Workflow Metadata')
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('tenant_id')
                            ->relationship('tenant', 'business_name')
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('team_id')
                            ->relationship('team', 'name')
                            ->searchable()
                            ->nullable(),
                        Forms\Components\Select::make('status')
                            ->options([
                                WorkflowStatus::Active->value => 'Active',
                                WorkflowStatus::Disabled->value => 'Disabled',
                                WorkflowStatus::Deleted->value => 'Deleted',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('tenant.business_name')
                    ->label('Tenant')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('team.name')
                    ->label('Team')
                    ->placeholder('Tenant-wide')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (WorkflowStatus|string|null $state): string => match ($state instanceof WorkflowStatus ? $state->value : (string) $state) {
                        WorkflowStatus::Active->value => 'success',
                        WorkflowStatus::Disabled->value => 'warning',
                        WorkflowStatus::Deleted->value => 'danger',
                        default => 'secondary',
                    }),
                Tables\Columns\TextColumn::make('current_version_number')
                    ->label('Version')
                    ->formatStateUsing(fn ($state) => $state ? "v{$state}" : 'v1')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('total_runs')
                    ->label('Total Runs')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('active_instances')
                    ->label('Active')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->placeholder('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'business_name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        WorkflowStatus::Active->value => 'Active',
                        WorkflowStatus::Disabled->value => 'Disabled',
                        WorkflowStatus::Deleted->value => 'Deleted',
                    ]),
                Tables\Filters\Filter::make('has_active_instances')
                    ->label('Has Active Runs')
                    ->query(fn ($query) => $query->where('active_instances', '>', 0)),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\Action::make('toggleStatus')
                    ->label(fn (Workflow $record): string => $record->status === WorkflowStatus::Active ? 'Disable' : 'Activate')
                    ->icon(fn (Workflow $record): string => $record->status === WorkflowStatus::Active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (Workflow $record): string => $record->status === WorkflowStatus::Active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Workflow $record): void {
                        if ($record->status === WorkflowStatus::Active) {
                            $record->status = WorkflowStatus::Disabled;
                        } else {
                            $record->status = WorkflowStatus::Active;
                        }
                        $record->save();

                        \App\Models\AdminAuditLog::record(
                            'workflow.status_changed',
                            "Workflow '{$record->name}' status changed to " . ($record->status instanceof WorkflowStatus ? $record->status->value : $record->status),
                            $record,
                            ['status' => $record->status instanceof WorkflowStatus ? $record->status->value : $record->status]
                        );

                        Notification::make()
                            ->title('Workflow status updated')
                            ->body("{$record->name} is now " . ($record->status instanceof WorkflowStatus ? $record->status->value : $record->status))
                            ->success()
                            ->send();
                    }),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Workflow Metadata Overview')
                    ->components([
                        Infolists\Components\TextEntry::make('name')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('tenant.business_name')
                            ->label('Tenant')
                            ->badge()
                            ->color('info'),
                        Infolists\Components\TextEntry::make('team.name')
                            ->label('Team')
                            ->placeholder('Tenant-wide'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge(),
                        Infolists\Components\TextEntry::make('current_version_number')
                            ->label('Current Version')
                            ->formatStateUsing(fn ($state) => $state ? "v{$state}" : 'v1')
                            ->badge(),
                        Infolists\Components\TextEntry::make('total_runs')
                            ->label('Total Executions')
                            ->badge()
                            ->color('primary'),
                        Infolists\Components\TextEntry::make('active_instances')
                            ->label('Active Runs')
                            ->badge()
                            ->color('success'),
                        Infolists\Components\TextEntry::make('createdBy.name')
                            ->label('Creator')
                            ->placeholder('System / Unassigned'),
                        Infolists\Components\TextEntry::make('description')
                            ->placeholder('No description provided')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime('F d, Y H:i:s'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkflows::route('/'),
            'view' => Pages\ViewWorkflow::route('/{record}'),
        ];
    }
}
