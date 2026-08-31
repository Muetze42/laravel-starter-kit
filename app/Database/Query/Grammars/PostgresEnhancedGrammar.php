<?php

declare(strict_types=1);

namespace App\Database\Query\Grammars;

use App\Database\Query\Grammars\Concerns\UsesMicrosecondDateFormatTrait;
use Tpetry\PostgresqlEnhanced\Query\Grammar;

class PostgresEnhancedGrammar extends Grammar
{
    use UsesMicrosecondDateFormatTrait;
}
