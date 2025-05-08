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
        table, th, td {
            border: 1px solid #000;
            padding: 5px;
        }
    </style>
</head>
<body>
    <h2>{{ $title }}</h2>
    <table>
        <thead>
            <tr>
                @foreach(array_keys((array) $data[0] ?? []) as $column)
                    <th>{{ ucwords(str_replace('_', ' ', $column)) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
                <tr>
                    @foreach($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
