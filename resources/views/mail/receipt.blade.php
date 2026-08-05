{{--
    The receipt email. Plain, self-contained HTML — email clients do not load the
    theme's stylesheet, so this deliberately does not try to look like the storefront.
    Locally it lands in Mailpit: http://localhost:8030
--}}
<div style="font-family:Arial,Helvetica,sans-serif;color:#222;max-width:600px">
    <h2 style="margin:0 0 4px">Brator Auto Parts</h2>
    <p style="margin:0 0 18px;color:#666">Receipt {{ $receipt->receipt_number }}</p>

    <p>Thank you, {{ $receipt->customer_name }}. Your order is confirmed. We will call you on
        {{ $receipt->customer_phone ?: 'the number you gave us' }} to arrange delivery and payment.</p>

    <table cellpadding="6" cellspacing="0" style="width:100%;border-collapse:collapse;margin:18px 0">
        <tr style="background:#f4f4f4;text-align:left">
            <th>Part</th><th>SKU</th><th align="right">Unit</th><th align="right">Qty</th><th align="right">Total</th>
        </tr>
        @foreach ($receipt->lines as $line)
            <tr style="border-bottom:1px solid #eee">
                <td>{{ $line->product_name_snapshot }}</td>
                <td style="color:#666">{{ $line->product_sku_snapshot }}</td>
                <td align="right">{{ $line->unit_price_minor->format() }}</td>
                <td align="right">{{ $line->quantity }}</td>
                <td align="right">{{ $line->line_total_minor->format() }}</td>
            </tr>
        @endforeach
        <tr><td colspan="4" align="right">Subtotal (excl. VAT)</td><td align="right">{{ $receipt->subtotal_minor->format() }}</td></tr>
        <tr><td colspan="4" align="right">VAT {{ (int) config('shop.vat_rate') }}%</td><td align="right">{{ $receipt->vat_minor->format() }}</td></tr>
        <tr><td colspan="4" align="right">Delivery</td><td align="right">{{ $receipt->shipping_minor->isZero() ? 'Free' : $receipt->shipping_minor->format() }}</td></tr>
        <tr style="font-weight:bold;background:#f4f4f4"><td colspan="4" align="right">Total</td><td align="right">{{ $receipt->total_minor->format() }}</td></tr>
    </table>

    <p style="margin:0 0 4px"><b>Delivery address</b></p>
    <p style="margin:0 0 18px;white-space:pre-line;color:#444">{{ $receipt->shipping_address }}</p>

    @if ($receipt->notes)
        <p style="margin:0 0 4px"><b>Your notes</b></p>
        <p style="margin:0 0 18px;white-space:pre-line;color:#444">{{ $receipt->notes }}</p>
    @endif

    <p style="color:#888;font-size:12px">Prices are shown excluding VAT; VAT is added above.
        Keep this email as your receipt.</p>
</div>
