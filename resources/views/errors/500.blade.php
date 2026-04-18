<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Something went wrong') }}</title>
    <style>
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            max-width: 560px;
            width: 100%;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }
        h1 {
            margin: 0 0 10px;
            font-size: 24px;
        }
        p {
            margin: 0 0 12px;
            color: #4b5563;
            line-height: 1.5;
        }
        .ref {
            display: inline-block;
            margin-top: 4px;
            padding: 8px 10px;
            background: #f3f4f6;
            border-radius: 8px;
            font-family: Consolas, "Courier New", monospace;
            color: #111827;
            font-size: 12px;
        }
        a {
            color: #0f766e;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>{{ __('Something went wrong') }}</h1>
    <p>{{ __('An unexpected error occurred. Please try again in a moment.') }}</p>
    @if(!empty($errorId))
        <p>{{ __('Reference ID') }}:</p>
        <span class="ref">{{ $errorId }}</span>
    @endif
    <p style="margin-top:16px;">
        <a href="{{ url('/') }}">{{ __('Go to homepage') }}</a>
    </p>
</div>
</body>
</html>
