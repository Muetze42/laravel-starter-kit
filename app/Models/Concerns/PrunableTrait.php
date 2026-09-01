<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
trait PrunableTrait
{
    use Prunable;
    use SoftDeletes;

    /**
     * Get the cutoff date for pruning models.
     */
    protected function prunableCutoff(): CarbonInterface
    {
        return now()->subMonths(12);
    }

    /**
     * Get the prunable model query.
     *
     * @return \Illuminate\Database\Eloquent\Builder<TModel>
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()
            ->where('deleted_at', '<=', $this->prunableCutoff());
    }
}
