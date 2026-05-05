<?php

namespace Modules\Integrations\Services\Actions;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Modules\Integrations\Contracts\IntegrationAction;
use Modules\Integrations\Exceptions\IntegrationException;
use Modules\Integrations\Models\IntegrationConnection;

class SendEmailAction implements IntegrationAction
{
    public function key(): string
    {
        return 'send-email';
    }

    public function handle(array $payload, ?IntegrationConnection $connection = null): mixed
    {
        $validated = Validator::make($payload, [
            'to' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ])->validate();

        if (! config('mail.from.address')) {
            throw new IntegrationException('A default mail sender address is not configured.', 422);
        }

        Mail::raw($validated['body'], function ($message) use ($validated): void {
            $message->to($validated['to'])
                ->subject($validated['subject']);
        });

        return [
            'sent' => true,
            'to' => $validated['to'],
        ];
    }
}
