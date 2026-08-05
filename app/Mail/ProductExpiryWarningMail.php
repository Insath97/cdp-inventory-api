<?php

namespace App\Mail;

use App\Models\ExpiryRecord;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProductExpiryWarningMail extends Mailable
{
    use Queueable, SerializesModels;

    public $title;
    public $description;
    public $details;

    /**
     * Create a new message instance.
     */
    public function __construct(ExpiryRecord $record)
    {
        $record->load(['product', 'productVariant', 'branch']);

        $name = $record->product->product_name ?? 'Item';
        if ($record->productVariant?->name) {
            $name .= ' - ' . $record->productVariant->name;
        }

        $expiryDate = Carbon::parse($record->expiry_date);
        $daysRemaining = max(0, now()->diffInDays($expiryDate, false));

        $this->title = 'Product Expiry Warning Alert';
        $this->description = "A product variant is nearing its expiration date (less than 30 days remaining).";
        $this->details = [
            'Product' => $name,
            'Branch' => $record->branch->name ?? '—',
            'Batch Number' => $record->batch_number ?? '—',
            'Expiry Date' => $record->expiry_date,
            'Days Remaining' => $daysRemaining . ' day(s)',
            'Quantity' => $record->quantity,
            'Status' => 'active_warning',
        ];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[" . config('app.name') . "] Urgent: Product Expiration Warning - " . ($this->details['Product'] ?? '')
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
                'items' => [],
            ]
        );
    }
}
