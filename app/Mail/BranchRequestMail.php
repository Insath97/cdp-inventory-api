<?php

namespace App\Mail;

use App\Models\BranchRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BranchRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $title;
    public $description;
    public $details;
    public $items = [];

    /**
     * Create a new message instance.
     */
    public function __construct(BranchRequest $request, string $actionType)
    {
        $request->load(['branch', 'requester', 'approver']);

        $this->title = 'Branch Request ' . ucfirst($actionType);
        $this->description = "A branch inventory request has been {$actionType}.";
        $this->details = [
            'Request Number' => $request->request_no,
            'Branch' => $request->branch->name ?? '—',
            'Requested By' => $request->requester->name ?? '—',
            'Request Date' => $request->request_date,
            'Status' => $request->status,
            'Total Items' => $request->total_items,
            'Approved By' => $request->approver->name ?? '—',
        ];

        // If requested_products JSON exists, populate it as line items
        if ($request->requested_products) {
            $prods = is_string($request->requested_products) 
                ? json_decode($request->requested_products, true) 
                : $request->requested_products;

            if (is_array($prods)) {
                foreach ($prods as $p) {
                    $this->items[] = [
                        'name' => $p['product_name'] ?? ($p['name'] ?? 'Product'),
                        'qty' => $p['quantity'] ?? ($p['qty'] ?? 0),
                    ];
                }
            }
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[" . config('app.name') . "] Branch Request Alert - " . ($this->details['Request Number'] ?? '')
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
