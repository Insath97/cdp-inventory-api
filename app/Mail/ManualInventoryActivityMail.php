<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManualInventoryActivityMail extends Mailable
{
    use Queueable, SerializesModels;

    public $title;
    public $description;
    public $details;

    /**
     * Create a new message instance.
     */
    public function __construct($model, string $activityType)
    {
        $relations = ['product', 'branch'];
        if (method_exists($model, 'productVariant')) {
            $relations[] = 'productVariant';
        }
        $model->load($relations);

        $name = $model->product->product_name ?? 'Item';
        if (method_exists($model, 'productVariant') && isset($model->productVariant) && $model->productVariant?->name) {
            $name .= ' - ' . $model->productVariant->name;
        }

        $user = $activityType === 'Check In' 
            ? \App\Models\User::find($model->checked_in_by) 
            : \App\Models\User::find($model->checked_out_by);

        $this->title = 'Manual Stock ' . $activityType;
        $this->description = "A manual stock {$activityType} has been successfully recorded in the system.";
        $this->details = [
            'Activity Type' => $activityType,
            'Product' => $name,
            'Branch' => $model->branch->name ?? '—',
            'Performed By' => $user->name ?? 'System',
            'Quantity' => $model->quantity,
            'Date' => $activityType === 'Check In' ? $model->check_in_date : $model->check_out_date,
            'Status' => $model->status ?? 'completed',
            'Notes' => $model->notes ?? '—',
        ];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[" . config('app.name') . "] Manual Stock " . $this->details['Activity Type'] . " - " . ($this->details['Product'] ?? '')
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
