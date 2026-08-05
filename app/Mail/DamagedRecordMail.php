<?php

namespace App\Mail;

use App\Models\DamagedRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DamagedRecordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $title;
    public $description;
    public $details;

    /**
     * Create a new message instance.
     */
    public function __construct(DamagedRecord $record)
    {
        $record->load(['product', 'productVariant', 'branch', 'reportedBy']);

        $name = $record->product->product_name ?? 'Item';
        if ($record->productVariant?->variant_name) {
            $name .= ' - ' . $record->productVariant->variant_name;
        }

        $this->title = 'Damaged Record Reported';
        $this->description = "A damaged product record has been reported or updated.";
        $this->details = [
            'Product' => $name,
            'Branch' => $record->branch->name ?? '—',
            'Reported By' => $record->reportedBy->name ?? '—',
            'Quantity' => $record->quantity,
            'Damage Date' => $record->damage_date,
            'Status' => $record->status ?? 'reported',
            'Reason' => $record->reason ?? '—',
        ];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[" . config('app.name') . "] Damaged Record Warning - " . ($this->details['Product'] ?? '')
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
