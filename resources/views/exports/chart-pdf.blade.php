<!DOCTYPE html>
<html>
<head>
    <title>{{ $title ?? 'Report' }}</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #aaa;
            padding: 5px;
            text-align: left;
        }
    </style>
</head>
<body>
    <h2>{{ $title ?? 'Stock Report' }}</h2>

    @if($data->isEmpty())
        <p>No data available for this report.</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach(array_keys((array) $data->first()) as $key)
                        <th>{{ ucwords(str_replace('_', ' ', $key)) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($data as $row)
                    <tr>
                        @foreach((array) $row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
