<?php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('first_name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('last_name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->label('Display Name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('position')
                    ->maxLength(255),
                Forms\Components\Select::make('role')
                    ->options([
                        Role::BusinessOwner->value => 'Business Owner',
                        Role::Manager->value => 'Manager',
                        Role::Admin->value => 'Admin',
                        Role::Employee->value => 'Employee',
                    ])
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active Status')
                    ->default(true),
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
                Tables\Columns\TextColumn::make('position')
                    ->placeholder('N/A'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
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
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
