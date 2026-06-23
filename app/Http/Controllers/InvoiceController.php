<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Order;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function download(Order $order): Response
    {
        $order->load('customer');
        $settings = BusinessSetting::allAsArray();

        $html = $this->buildHtml($order, $settings);

        // Using mPDF (composer require mpdf/mpdf)
        // Fallback: return HTML if mPDF not installed
        if (! class_exists(\Mpdf\Mpdf::class)) {
            return response($html, 200, ['Content-Type' => 'text/html']);
        }

        $mpdf = new \Mpdf\Mpdf([
            'margin_left'   => 12,
            'margin_right'  => 12,
            'margin_top'    => 12,
            'margin_bottom' => 12,
            'format'        => 'A4',
        ]);

        $mpdf->WriteHTML($this->pdfStyles() . $html);

        $filename = "Invoice-{$order->order_number}.pdf";
        $output   = $mpdf->Output($filename, \Mpdf\Output\Destination::STRING_RETURN);

        return response($output, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function buildHtml(Order $order, array $settings): string
    {
        $items    = $order->items ?? [];
        $itemRows = '';

        foreach ($items as $item) {
            $subtotal  = ($item['qty'] ?? 1) * ($item['unit_price'] ?? 0);
            $itemRows .= "<tr>
                <td>{$item['name']}</td>
                <td class='center'>{$item['qty']} {$item['unit']}</td>
                <td class='right'>৳" . number_format($item['unit_price'] ?? 0, 0) . "</td>
                <td class='right'>৳" . number_format($subtotal, 0) . "</td>
            </tr>";
        }

        $businessName = htmlspecialchars($settings['business_name'] ?? 'My Business');
        $phone        = htmlspecialchars($settings['phone_number'] ?? '');
        $orderDate    = $order->created_at->format('d M Y');
        $customerName = htmlspecialchars($order->customer->name ?? 'Customer');
        $address      = htmlspecialchars($order->delivery_address ?? '—');
        $orderNo      = $order->order_number;
        $subtotal     = number_format((float) $order->subtotal, 0);
        $delivery     = number_format((float) $order->delivery_charge, 0);
        $total        = number_format((float) $order->total, 0);
        $status       = ucfirst(str_replace('_', ' ', $order->status));

        return <<<HTML
<div class="invoice">
  <div class="header">
    <div class="business">
      <h1>{$businessName}</h1>
      <p>{$phone}</p>
    </div>
    <div class="invoice-meta">
      <h2>Invoice</h2>
      <p><strong>#{$orderNo}</strong></p>
      <p>{$orderDate}</p>
      <span class="badge">{$status}</span>
    </div>
  </div>

  <div class="bill-to">
    <h3>Bill to</h3>
    <p><strong>{$customerName}</strong></p>
    <p>{$address}</p>
    <p>{$order->customer->phone}</p>
  </div>

  <table>
    <thead>
      <tr>
        <th>Item</th>
        <th class="center">Qty</th>
        <th class="right">Price</th>
        <th class="right">Amount</th>
      </tr>
    </thead>
    <tbody>
      {$itemRows}
    </tbody>
    <tfoot>
      <tr class="subtotal-row">
        <td colspan="3" class="right">Subtotal</td>
        <td class="right">৳{$subtotal}</td>
      </tr>
      <tr class="subtotal-row">
        <td colspan="3" class="right">Delivery</td>
        <td class="right">৳{$delivery}</td>
      </tr>
      <tr class="total-row">
        <td colspan="3" class="right">Total</td>
        <td class="right">৳{$total}</td>
      </tr>
    </tfoot>
  </table>

  <div class="footer">
    <p>Thank you for your order!</p>
    <p class="muted">Payment: Cash on delivery / bKash / Nagad</p>
  </div>
</div>
HTML;
    }

    private function pdfStyles(): string
    {
        return <<<CSS
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: sans-serif; font-size: 13px; color: #1a1a1a; }
  .invoice { padding: 24px; }
  .header { display: flex; justify-content: space-between; margin-bottom: 28px; padding-bottom: 16px; border-bottom: 2px solid #185FA5; }
  .business h1 { font-size: 20px; color: #185FA5; }
  .business p { color: #555; font-size: 12px; margin-top: 4px; }
  .invoice-meta { text-align: right; }
  .invoice-meta h2 { font-size: 18px; color: #185FA5; }
  .invoice-meta p { font-size: 12px; color: #555; }
  .badge { display: inline-block; background: #E6F1FB; color: #185FA5; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-top: 4px; }
  .bill-to { margin-bottom: 24px; }
  .bill-to h3 { font-size: 11px; text-transform: uppercase; color: #888; letter-spacing: 0.05em; margin-bottom: 6px; }
  .bill-to p { font-size: 13px; line-height: 1.6; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  thead tr { background: #185FA5; color: #fff; }
  th { padding: 9px 12px; font-size: 12px; font-weight: 600; text-align: left; }
  td { padding: 8px 12px; border-bottom: 1px solid #eee; }
  tbody tr:nth-child(even) { background: #f8f9fb; }
  .center { text-align: center; }
  .right { text-align: right; }
  .subtotal-row td { border-bottom: none; color: #555; font-size: 12px; }
  .total-row td { font-weight: 700; font-size: 15px; border-top: 2px solid #185FA5; padding-top: 10px; }
  .footer { text-align: center; padding-top: 16px; border-top: 1px solid #eee; }
  .footer p { font-size: 12px; color: #555; }
  .muted { color: #aaa; margin-top: 4px; }
</style>
CSS;
    }
}
