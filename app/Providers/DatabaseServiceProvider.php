<?php

declare(strict_types=1);

namespace App\Providers;

use App\Database\Query\Grammars\PostgresEnhancedGrammar;
use App\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Tpetry\PostgresqlEnhanced\Query\Grammar as EnhancedGrammar;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $connection = DB::connection('pgsql');

        $connection->setQueryGrammar($this->makePostgresGrammar($connection));
    }

    /**
     * Make the compatible PostgreSQL query grammar.
     */
    protected function makePostgresGrammar(Connection $connection): Grammar
    {
        if (class_exists(EnhancedGrammar::class)) {
            return new PostgresEnhancedGrammar($connection);
        }

        return new PostgresGrammar($connection);
    }
}
