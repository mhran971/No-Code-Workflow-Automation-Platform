<?php

namespace App\Filament\Resources\TeamResource\RelationManagers;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Auth\Enums\Role;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->email()
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
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (Role|string|null $state): string => match ($state instanceof Role ? $state->value : (string) $state) {
                        Role::BusinessOwner->value => 'warning',
                        Role::Manager->value => 'info',
                        Role::Admin->value => 'primary',
                        Role::Employee->value => 'gray',
                        default => 'secondary',
                    }),
                Tables\Columns\TextColumn::make('pivot.status')
                    ->label('Team Status')
                    ->badge()
                    ->color(fn (string|null $state): string => match ($state) {
                        'active' => 'success',
                        'disabled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Account Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('pivot.created_at')
                    ->label('Joined Team At')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        Role::BusinessOwner->value => 'Business Owner',
                        Role::Manager->value => 'Manager',
                        Role::Admin->value => 'Admin',
                        Role::Employee->value => 'Employee',
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
