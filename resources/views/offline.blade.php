<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline — {{ config('app.name') }}</title>
    <style>
        body {
            font-family: 'Outfit', system-ui, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #f4f6f8;
            color: #0b0d12;
            margin: 0;
            padding: 24px;
            text-align: center;
        }
        h1 { font-size: 1.4rem; margin-bottom: 8px; }
        p  { color: #667085; font-size: .88rem; max-width: 320px; line-height: 1.6; }
        .icon { font-size: 3rem; margin-bottom: 16px; }
        button {
            margin-top: 16px;
            padding: 10px 22px;
            background: #004EFC;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: .85rem;
            font-weight: 500;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="icon">📶</div>
    <h1>You're Offline</h1>
    <p>Please check your internet connection. Sales, payments, and stock transactions require an active connection.</p>
    <button onclick="window.location.reload()">Try Again</button>
</body>
</html>
