<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Foundation\Events\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReorderLevel;

class LowStockDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Product $product,
        public ?ProductVariant $variant,
        public ?Branch $branch,
        public float $currentStock,
        public float $reorderLevel,
        public ?ReorderLevel $rule = null,
    ) {
    }

    public function shouldNotify(): bool
    {
        return $this->currentStock <= $this->reorderLevel;
    }
}
