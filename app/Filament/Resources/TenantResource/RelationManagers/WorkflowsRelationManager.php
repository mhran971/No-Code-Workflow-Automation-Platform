<?php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Workflows\Enums\WorkflowStatus;

class WorkflowsRelationManager extends RelationManager
{
    protected static string $relationship = 'workflows';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->options([
                        WorkflowStatus::Active->value => 'Active',
                        WorkflowStatus::Disabled->value => 'Disabled',
                        WorkflowStatus::Deleted->value => 'Deleted',
                    ])
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('team.name')
                    ->label('Team')
                    ->placeholder('Tenant-wide')
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
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        WorkflowStatus::Active->value => 'Active',
                        WorkflowStatus::Disabled->value => 'Disabled',
                        WorkflowStatus::Deleted->value => 'Deleted',
                    ]),
            ])
            ->headerActions([
                //
            ])
            ->actions([
                Actions\ViewAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }
}
