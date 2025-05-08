<!DOCTYPE html>
<html>
<head>
    <title>{{ $title }}</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            border: 1px solid #333;
            padding: 5px;
        }
    </style>
</head>
<body>
    <h2>{{ $title }}</h2>
    @if(count($data) > 0)
        <table>
            <thead>
                <tr>
                    @foreach(array_keys((array)$data[0]) as $key)
                        <th>{{ ucwords(str_replace('_', ' ', $key)) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($data as $row)
                    <tr>
                        @foreach((array)$row as $value)
                            <td>{{ $value }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>No data available.</p>
    @endif
</body>
</html>
