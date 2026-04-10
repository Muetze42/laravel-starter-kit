<?php

namespace App\Models\Concerns;

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
     * Get the prunable model query.
     *
     * @return Builder<TModel>
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()
            ->where('deleted_at', '<=', now()->subMonths(12));
    }
}
