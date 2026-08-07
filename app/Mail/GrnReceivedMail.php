<?php

namespace App\Mail;

use App\Models\Grn;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GrnReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $title;
    public $description;
    public $details;
    public $items = [];

    /**
     * Create a new message instance.
     */
    public function __construct(Grn $grn)
    {
        $grn->load(['supplier', 'receiver', 'items.product', 'items.productVariant']);

        $this->title = 'Goods Received Note Received';
        $this->description = "A Goods Received Note (GRN) has been marked as received and recorded into the stock ledger.";
        $this->details = [
            'GRN Number' => $grn->grn_number,
            'Purchase Order Number' => $grn->purchaseOrder->po_number ?? '—',
            'Supplier' => $grn->supplier->supplier_name ?? '—',
            // 'Branch' removed — GRN no longer tied to a branch
            'Received Date' => $grn->received_date,
            'Received By' => $grn->receiver->name ?? 'System',
            'Status' => $grn->status,
        ];

        foreach ($grn->items as $item) {
            $name = $item->product->product_name ?? 'Item';
            if ($item->productVariant?->name) {
                $name .= ' - ' . $item->productVariant->name;
            }
            $this->items[] = [
                'name' => $name,
                'qty' => $item->quantity_received,
            ];
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[" . config('app.name') . "] GRN Received Notification - " . ($this->details['GRN Number'] ?? '')
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.admin-alert',
            with: [
                'title' => $this->title,
                'description' => $this->description,
                'details' => $this->details,
                'items' => $this->items,
            ]
        );
    }
}
