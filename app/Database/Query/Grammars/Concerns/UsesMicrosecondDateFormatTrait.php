<?php

declare(strict_types=1);

namespace App\Database\Query\Grammars\Concerns;

use Override;

trait UsesMicrosecondDateFormatTrait
{
    /**
     * Get the format for database stored dates.
     */
    #[Override]
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.u';
    }
}
