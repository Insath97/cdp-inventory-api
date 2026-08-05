<?php

namespace App\Mail;

use App\Models\StockTake;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StockTakeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $title;
    public $description;
    public $details;
    public $items = [];

    /**
     * Create a new message instance.
     */
    public function __construct(StockTake $stockTake)
    {
        $stockTake->load(['branch', 'creator', 'approver', 'items.product', 'items.productVariant']);

        $statusTitle = ucfirst($stockTake->status);
        $this->title = "Stock Take {$statusTitle}";
        $this->description = "Stock Take {$stockTake->take_number} for {$stockTake->branch?->name} branch has been {$stockTake->status}.";
        $this->details = [
            'Take Number' => $stockTake->take_number,
            'Branch' => $stockTake->branch?->name ?? '—',
            'Date' => $stockTake->take_date,
            'Status' => $statusTitle,
            'Created By' => $stockTake->creator?->name ?? '—',
            'Approved By' => $stockTake->approver?->name ?? '—',
            'Notes' => $stockTake->notes ?? '—',
        ];

        foreach ($stockTake->items as $item) {
            $name = $item->product->product_name ?? 'Item';
            if ($item->productVariant?->variant_name || $item->productVariant?->name) {
                $name .= ' - ' . ($item->productVariant->variant_name ?? $item->productVariant->name);
            }
            $this->items[] = [
                'name' => $name,
                'qty' => $item->physical_quantity ?? $item->system_quantity ?? 0,
            ];
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[" . config('app.name') . "] Stock Take Alert - " . ($this->details['Take Number'] ?? '')
        );
    }

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
