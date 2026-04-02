<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 14px; color: #334155; }
        .header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; }
        h1 { color: #1e293b; margin: 0 0 10px 0; font-size: 24px; }
        .summary-container { width: 100%; border-collapse: separate; border-spacing: 20px; margin-top: 30px; }
        .summary-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; text-align: center; width: 30%; }
        .box-title { color: #64748b; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        .box-value { color: #0f172a; font-size: 28px; font-weight: bold; margin: 0; }
        .footer { margin-top: 50px; font-size: 11px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Financial & Claims Summary</h1>
        <p>Global System Overview - Generated on {{ now()->format('M d, Y H:i') }}</p>
    </div>

    <table class="summary-container">
        <tr>
            <td class="summary-box">
                <div class="box-title">Total RMAs</div>
                <p class="box-value">{{ number_format($total_rmas) }}</p>
            </td>
            
            <td class="summary-box">
                <div class="box-title">Total Returns Value</div>
                <p class="box-value" style="color: #ea580c;">${{ number_format($total_returns_value, 2) }}</p>
            </td>

            <td class="summary-box">
                <div class="box-title">Total Refunds Issued</div>
                <p class="box-value" style="color: #059669;">${{ number_format($total_refunds, 2) }}</p>
            </td>
        </tr>
    </table>

    <div class="footer">
        Strictly Internal Document - RMA Management System
        <br>
        *Returns Value includes all RMAs associated with a sale. Refunds Issued specifically filters for completed Simple Returns.
    </div>
</body>
</html>
