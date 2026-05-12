<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>{{ $appName }} | Connection successful</title>
        <style>
            :root {
                color-scheme: light;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                font-family: Arial, Helvetica, sans-serif;
                background: #ffffff;
                color: #111111;
            }

            .card {
                width: min(640px, calc(100vw - 32px));
                border: 1px solid #d1d5db;
                border-radius: 12px;
                padding: 32px;
                text-align: center;
            }

            .app-name {
                margin: 0 0 12px;
                font-size: 14px;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            h1 {
                margin: 0;
                font-size: 30px;
                line-height: 1.2;
                font-weight: 700;
            }

            p {
                margin: 16px 0 0;
                font-size: 16px;
                line-height: 1.6;
            }

            .note {
                margin-top: 24px;
                font-size: 14px;
                line-height: 1.5;
            }
        </style>
        <script>
            window.addEventListener('load', function () {
                try {
                    if (window.opener && !window.opener.closed) {
                        window.opener.location.reload();
                    }
                } catch (error) {
                    // Ignore cross-origin opener access errors.
                }

                window.setTimeout(function () {
                    window.close();
                }, 1000);
            });
        </script>
    </head>
    <body>
        <main class="card" role="main" aria-labelledby="success-title">
            <p class="app-name">{{ $appName }}</p>
            <h1 id="success-title">Connection successful</h1>
            <p>Your integration was connected successfully. You can close this tab.</p>
            <p class="note">The connections page will refresh automatically.</p>
        </main>
    </body>
</html>
