<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.5;">
    <h2 style="margin-bottom: 8px;">{{ $title }}</h2>
    <p style="margin-top: 0;">{{ $messageBody }}</p>

    @if(!empty($context))
        <h4 style="margin-bottom: 6px;">Details</h4>
        <ul style="padding-left: 18px; margin-top: 0;">
            @foreach($context as $key => $value)
                <li><strong>{{ str_replace('_', ' ', ucfirst((string) $key)) }}:</strong> {{ is_scalar($value) ? $value : json_encode($value) }}</li>
            @endforeach
        </ul>
    @endif

    <p style="margin-top: 20px; color: #475569; font-size: 12px;">
        This is an automated notification from the NTSA Traffic Demerit System.
    </p>
</body>
</html>
