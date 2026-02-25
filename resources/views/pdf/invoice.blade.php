<!DOCTYPE html>
<html lang="{{ $language }}">

<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1f2937;
        }

        .page {
            padding: 24px 40px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 24px;
        }

        .brand-color {
            color: #10AF13;
        }

        .header-bar {
            background: #10AF13;
            color: #fff;
            padding: 6px 12px;
            font-weight: bold;
            font-size: 10px;
            display: inline-block;
        }

        .divider {
            height: 3px;
            background: #10AF13;
            margin-top: 4px;
        }

        .section {
            margin-bottom: 24px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
        }

        table.items th {
            text-align: left;
            padding: 6px 8px;
            font-size: 10px;
            border-bottom: 2px solid #9ca3af;
        }

        table.items td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .summary-box {
            border: 1px solid #d1d5db;
            overflow: hidden;
        }

        .summary-label {
            background: #FFF9C4;
            padding: 8px 12px;
            font-size: 10px;
            color: #6b7280;
            width: 120px;
            display: inline-block;
        }

        .summary-value {
            padding: 8px 16px;
            font-size: 20px;
            font-weight: bold;
            display: inline-block;
        }

        .grand-total-box {
            background: #FFF9C4;
            border: 1px solid #d1d5db;
            padding: 8px 16px;
            font-weight: bold;
            font-size: 16px;
            text-align: right;
        }
    </style>
</head>

<body>
    <div class="page">
        {{-- Company Header --}}
        @if(collect($components)->firstWhere('key', 'company_header')['enabled'] ?? false)
            <div class="header">
                <div>
                    <div style="font-size:28px;font-weight:800;color:#1B2A3D;">timedoor</div>
                    <div class="brand-color" style="font-size:12px;font-weight:bold;letter-spacing:0.3em;">
                        {{ $labels['invoice'] }}</div>
                </div>
                @if(collect($components)->firstWhere('key', 'invoice_meta')['enabled'] ?? false)
                    <div>
                        <span class="header-bar">{{ $invoice->invoice_date->format('Y-m-d') }}</span>
                        <span class="header-bar"
                            style="border-left:1px solid rgba(255,255,255,0.3);">{{ $labels['invoice_number'] }}:
                            {{ $invoice->invoice_number }}</span>
                    </div>
                @endif
            </div>
            <div class="divider"></div>
        @endif

        {{-- Customer + Sender Details --}}
        <div class="section" style="margin-top:24px;">
            <table style="width:100%;">
                <tr>
                    @if(collect($components)->firstWhere('key', 'customer_details')['enabled'] ?? false)
                        <td style="vertical-align:top;width:50%;">
                            <div style="font-size:10px;color:#6b7280;">{{ $labels['to'] }}</div>
                            <div style="font-weight:bold;font-size:14px;">
                                {{ $invoice->customer->company_name ?? $invoice->customer->name }}</div>
                        </td>
                    @endif
                    @if(collect($components)->firstWhere('key', 'sender_details')['enabled'] ?? false)
                        <td style="vertical-align:top;width:50%;text-align:right;font-size:10px;color:#6b7280;">
                            {{-- Sender details rendered here if available --}}
                        </td>
                    @endif
                </tr>
            </table>
        </div>

        {{-- Total Summary Box --}}
        @if(collect($components)->firstWhere('key', 'total_summary_box')['enabled'] ?? false)
            <div class="section summary-box">
                <span class="summary-label">{{ $labels['amount_of_payment'] }}</span>
                <span class="summary-value">@formatCurrency($invoice->total, $invoice->currency)</span>
            </div>
        @endif

        {{-- Line Items Table --}}
        @if(collect($components)->firstWhere('key', 'line_items')['enabled'] ?? false)
            <div class="section">
                <table class="items">
                    <thead>
                        <tr>
                            <th>{{ $labels['description'] }}</th>
                            <th class="text-center" style="width:60px;">{{ $labels['qty'] }}</th>
                            <th class="text-right" style="width:120px;">{{ $labels['unit_price'] }}</th>
                            <th class="text-right" style="width:120px;">{{ $labels['price'] }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td class="text-center">{{ $item->quantity }}</td>
                                <td class="text-right">@formatCurrency($item->unit_price, $invoice->currency)</td>
                                <td class="text-right">@formatCurrency($item->amount, $invoice->currency)</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Bank Transfer + Grand Total --}}
        @if(collect($components)->firstWhere('key', 'grand_total')['enabled'] ?? false)
            <div style="text-align:right;margin-top:16px;">
                <div style="font-size:10px;color:#6b7280;">{{ $labels['total_sum'] }}</div>
                <div class="grand-total-box">@formatCurrency($invoice->total, $invoice->currency)</div>
            </div>
        @endif
    </div>
</body>

</html>