<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Database\Query\Grammars\PostgresEnhancedGrammar;
use App\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Connection;
use PHPUnit\Framework\TestCase;
use Tpetry\PostgresqlEnhanced\Query\Grammar as EnhancedGrammar;

final class PostgresGrammarTest extends TestCase
{
    /**
     * The plain grammar preserves microseconds.
     */
    public function test_plain_grammar_returns_microsecond_date_format(): void
    {
        $grammar = new PostgresGrammar($this->createStub(Connection::class));

        $this->assertSame('Y-m-d H:i:s.u', $grammar->getDateFormat());
    }

    /**
     * The enhanced grammar keeps package compatibility and microseconds.
     */
    public function test_enhanced_grammar_returns_microsecond_date_format_when_package_is_installed(): void
    {
        if (! class_exists(EnhancedGrammar::class)) {
            $this->markTestSkipped('The PostgreSQL enhanced package is not installed.');
        }

        $grammar = new PostgresEnhancedGrammar($this->createStub(Connection::class));

        $this->assertInstanceOf(EnhancedGrammar::class, $grammar);
        $this->assertSame('Y-m-d H:i:s.u', $grammar->getDateFormat());
    }
}
