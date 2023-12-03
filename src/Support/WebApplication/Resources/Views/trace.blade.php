<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warning - {{ $title }}</title>
    <link href="/trace.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #121212;
            color: #fff;
            margin: 0;
            padding: 0;
        }

        .error-container {
            background-color: #1e1e1e;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            margin: 20px;
            overflow: hidden; /* Prevents overflow */
            animation: fadeIn 0.5s ease;
        }

        .error-header {
            text-align: left;
            border-bottom: 1px solid #e91e63;
            padding-bottom: 10px;
        }

        h1, h3, p {
            word-wrap: break-word;
        }

        .trace {
            overflow-x: auto;
            white-space: nowrap;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #e91e63;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        @media (max-width: 768px) {
            .error-container {
                margin: 10px;
                padding: 10px;
            }

            .error-header, h1, h3, p {
                font-size: smaller;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
    </style>
</head>
<body>
<div class="error-container">
    <div class="error-header">
        <h1>{{ $title }}</h1>
        <h3>File: {{ $file }}</h3>
        <p>Line: {{ $line }}</p>
    </div>
    <div class="trace">
        @foreach ($traces as $trace)
            @if( $trace['file'] && $trace['line'])
                <p>{{ $trace['file'] }} : {{$trace['line']}}</p>
            @endif
        @endforeach
    </div>
    <a href="javascript:history.back();" class="back-link">Go Back</a>
</div>
</body>
</html>
