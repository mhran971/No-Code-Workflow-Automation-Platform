<p>Hello {{ $userName }},</p>

<p>Your account has been created. Use the following credentials to sign in:</p>

<p>
    <strong>Email:</strong> {{ $email }}<br>
    <strong>Temporary Password:</strong> {{ $temporaryPassword }}
</p>

<p>
    Login URL: <a href="{{ url('/login') }}">{{ url('/login') }}</a>
</p>

<p>Please change your password immediately after your first login.</p>
