<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Database\Query\Grammars\PostgresEnhancedGrammar;
use App\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tpetry\PostgresqlEnhanced\Query\Grammar as EnhancedGrammar;

final class DatabaseServiceProviderTest extends TestCase
{
    /**
     * The provider installs a compatible PostgreSQL grammar.
     */
    public function test_pgsql_connection_uses_compatible_microsecond_grammar(): void
    {
        $grammar = DB::connection('pgsql')->getQueryGrammar();

        if (class_exists(EnhancedGrammar::class)) {
            $this->assertInstanceOf(PostgresEnhancedGrammar::class, $grammar);
        }

        if (! class_exists(EnhancedGrammar::class)) {
            $this->assertInstanceOf(PostgresGrammar::class, $grammar);
        }

        $this->assertSame('Y-m-d H:i:s.u', $grammar->getDateFormat());
    }
}
