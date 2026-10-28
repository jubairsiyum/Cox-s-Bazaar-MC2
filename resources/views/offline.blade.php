<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline — Cox's Bazaar ERP</title>
    <style>
        body { font-family: 'Inter', sans-serif; display: flex; flex-direction: column; align-items: center;
               justify-content: center; min-height: 100vh; background: #f4f6f8; color: #1a202c; margin: 0; }
        h1 { font-size: 1.4rem; margin-bottom: 8px; }
        p  { color: #718096; font-size: .88rem; text-align: center; max-width: 320px; }
        .icon { font-size: 3rem; margin-bottom: 16px; }
        button { margin-top: 16px; padding: 9px 20px; background: #c0392b; color: #fff; border: none;
                 border-radius: 6px; font-size: .85rem; cursor: pointer; }
    </style>
</head>
<body>
    <div class="icon">📶</div>
    <h1>You're Offline</h1>
    <p>Please check your internet connection. Sales, payments, and stock transactions require an active connection.</p>
    <button onclick="window.location.reload()">Try Again</button>
</body>
</html>
