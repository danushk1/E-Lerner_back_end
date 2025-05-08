<!DOCTYPE html>
<html>
<head>
    <title>Chart Report</title>
    <style>
        body { font-family: sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 6px 10px; text-align: left; }
    </style>
</head>
<body>
    <h2>Chart Report</h2>
    <table>
        <thead>
            <tr>
                @foreach($data->first() ?? [] as $key => $val)
                    <th>{{ $key }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
                <tr>
                    @foreach($row as $val)
                        <td>{{ $val }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
