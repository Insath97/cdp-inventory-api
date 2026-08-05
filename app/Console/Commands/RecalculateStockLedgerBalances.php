<?php

namespace App\Console\Commands;

use App\Models\StockLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the running `balance` column on stock_ledger.
 *
 * Historical rows were written while the balance lookup was scoped by variant,
 * so entries from variant-aware modules (GRN, transfers, purchase returns) and
 * product-level ones (check-in, check-out) each continued a different running
 * total and corrupted each other. Balances are now product+branch scoped; this
 * replays every row in order and writes the correct balance.
 */
class RecalculateStockLedgerBalances extends Command
{
    protected $signature = 'stock:recalculate-balances
                            {--product= : Limit to a single product id}
                            {--branch= : Limit to a single branch id}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Replay stock_ledger rows and rewrite the running balance per product+branch';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Each product+branch pair is an independent running total.
        $groups = StockLedger::query()
            ->select('product_id', 'branch_id')
            ->when($this->option('product'), fn ($q, $v) => $q->where('product_id', $v))
            ->when($this->option('branch'), fn ($q, $v) => $q->where('branch_id', $v))
            ->groupBy('product_id', 'branch_id')
            ->get();

        if ($groups->isEmpty()) {
            $this->info('No stock ledger rows matched.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Replaying {$groups->count()} product/branch group(s)...");

        $changed = 0;
        $scanned = 0;

        foreach ($groups as $group) {
            DB::transaction(function () use ($group, $dryRun, &$changed, &$scanned) {
                // Same ordering StockLedgerService uses to find the previous balance.
                $rows = StockLedger::query()
                    ->where('product_id', $group->product_id)
                    ->where(function ($q) use ($group) {
                        $group->branch_id === null
                            ? $q->whereNull('branch_id')
                            : $q->where('branch_id', $group->branch_id);
                    })
                    ->orderBy('transaction_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $running = 0.0;

                foreach ($rows as $row) {
                    $scanned++;
                    $running += (float) $row->quantity_in - (float) $row->quantity_out;

                    if (abs((float) $row->balance - $running) < 0.00005) {
                        continue;
                    }

                    $this->line(sprintf(
                        '  ledger #%d (product %s / branch %s): %s -> %s',
                        $row->id,
                        $row->product_id,
                        $row->branch_id ?? 'null',
                        $row->balance,
                        number_format($running, 4, '.', '')
                    ));

                    $changed++;

                    if (! $dryRun) {
                        $row->balance = $running;
                        $row->save();
                    }
                }

                if ($running < 0) {
                    $this->warn(sprintf(
                        '  product %s / branch %s ends at %s — negative stock, check the source records.',
                        $group->product_id,
                        $group->branch_id ?? 'null',
                        number_format($running, 4, '.', '')
                    ));
                }
            });
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d of %d row(s).',
            $dryRun ? 'Would update' : 'Updated',
            $changed,
            $scanned
        ));

        return self::SUCCESS;
    }
}
