<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #334155; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        h1 { color: #1e293b; margin: 0 0 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #cbd5e1; padding: 10px; text-align: left; }
        th { background-color: #f8fafc; color: #475569; font-weight: bold; }
        .footer { margin-top: 40px; font-size: 10px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Staff RMA Performance</h1>
        <p>Generated on {{ now()->format('M d, Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Full Name</th>
                <th>Staff Role</th>
                <th>Email Address</th>
                <th style="background-color: #e2e8f0; color: #1e293b; text-align: center;">Total Status Changes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($staff as $member)
            <tr>
                <td>{{ $member->full_name }}</td>
                <td>{{ $member->role_label }}</td>
                <td>{{ $member->email }}</td>
                <td style="text-align: center; font-weight: bold; font-size: 14px;">{{ $member->status_changes_count }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Strictly Internal Document - RMA Management System
    </div>
</body>
</html>
