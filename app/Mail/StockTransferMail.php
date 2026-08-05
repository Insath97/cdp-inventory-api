<?php

namespace App\Mail;

use App\Models\StockTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StockTransferMail extends Mailable
{
    use Queueable, SerializesModels;

    public $title;
    public $description;
    public $details;
    public $items = [];

    /**
     * Create a new message instance.
     */
    public function __construct(StockTransfer $transfer)
    {
        $transfer->load(['fromBranch', 'toBranch', 'fromEmployee', 'toEmployee', 'requester', 'approver', 'items.product', 'items.productVariant']);

        $this->title = 'Stock Transfer Status Updated';
        $this->description = "Stock transfer {$transfer->transfer_number} status has been updated to {$transfer->status}.";
        $this->details = [
            'Transfer Number' => $transfer->transfer_number,
            'Transfer Type' => str_replace('_', ' ', $transfer->transfer_type),
            'From Branch' => $transfer->fromBranch->name ?? '—',
            'To Branch' => $transfer->toBranch->name ?? '—',
            'From Employee' => $transfer->fromEmployee->name ?? '—',
            'To Employee' => $transfer->toEmployee->name ?? '—',
            'Status' => $transfer->status,
            'Date' => $transfer->transfer_date,
            'Requested By' => $transfer->requester->name ?? '—',
            'Approved By' => $transfer->approver->name ?? '—',
        ];

        foreach ($transfer->items as $item) {
            $name = $item->product->product_name ?? 'Item';
            if ($item->productVariant?->name) {
                $name .= ' - ' . $item->productVariant->name;
            }
            $this->items[] = [
                'name' => $name,
                'qty' => $item->quantity_received ?? $item->quantity_sent ?? $item->quantity_requested,
            ];
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[" . config('app.name') . "] Stock Transfer Status - " . ($this->details['Transfer Number'] ?? '')
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
