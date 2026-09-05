<?php

declare(strict_types=1);

namespace App\Console\Commands\DataMaintenance;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'data-maintenance:db:fix-sequences')]
class FixPostgresSequencesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data-maintenance:db:fix-sequences
                            {--table= : Fix only a specific table}
                            {--connection=* : Fix specific connection(s), or all PostgreSQL connections}
                            {--dry-run : Show what would be fixed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix PostgreSQL sequences to match the current max ID in each table';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $dryRun = (bool) $this->option('dry-run');
        $specificTable = $this->specificTableOption();

        if ($dryRun) {
            $this->info('DRY RUN - No changes will be made');
            $this->newLine();
        }

        if ($specificTable !== null && ! preg_match('/^[A-Za-z_]\w*$/', $specificTable)) {
            $this->error('The table option must be a valid PostgreSQL identifier.');

            return;
        }

        foreach ($this->connectionNames() as $connectionName) {
            $this->fixConnectionSequences($connectionName, $specificTable, $dryRun);
        }
    }

    /**
     * Get all sequences with their associated tables.
     *
     * @return \Illuminate\Support\Collection<int, object{tablename: string, sequence_name: string, column_name: string}>
     */
    protected function getSequences(string $connectionName, ?string $specificTable): Collection
    {
        $query = "
            SELECT
                t.relname as tablename,
                s.relname as sequence_name,
                a.attname as column_name
            FROM pg_class s
            JOIN pg_depend d ON d.objid = s.oid
            JOIN pg_class t ON d.refobjid = t.oid
            JOIN pg_namespace n ON n.oid = t.relnamespace
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = d.refobjsubid
            WHERE s.relkind = 'S'
            AND n.nspname = 'public'
            AND d.deptype = 'a'
        ";

        if ($specificTable !== null) {
            $query .= sprintf(" AND t.relname = '%s'", str_replace("'", "''", $specificTable));
        }

        $query .= ' ORDER BY t.relname';

        /** @var \Illuminate\Support\Collection<int, object{tablename: string, sequence_name: string, column_name: string}> */
        return collect(DB::connection($connectionName)->select($query));
    }

    /**
     * Get the current value of a sequence.
     */
    protected function getSequenceValue(string $connectionName, string $sequenceName): int
    {
        /** @var object{last_value: int}|null $result */
        $result = DB::connection($connectionName)
            ->selectOne(sprintf('SELECT last_value FROM %s', $this->quoteIdentifier($sequenceName)));

        return $result->last_value ?? 0;
    }

    /**
     * Get the maximum value from a table column.
     */
    protected function getMaxId(string $connectionName, string $tableName, string $columnName): int
    {
        /** @var object{max_id: int}|null $result */
        $result = DB::connection($connectionName)->selectOne(sprintf(
            'SELECT COALESCE(MAX(%s), 0) as max_id FROM %s',
            $this->quoteIdentifier($columnName),
            $this->quoteIdentifier($tableName)
        ));

        return $result->max_id ?? 0;
    }

    /**
     * Fix a sequence to match the max ID.
     */
    protected function fixSequence(string $connectionName, string $sequenceName, int $maxId): void
    {
        $newValue = max($maxId, 1);
        DB::connection($connectionName)
            ->statement(sprintf("SELECT setval('%s', %d)", $this->quoteIdentifier($sequenceName), $newValue));
    }

    /**
     * Fix all sequences for one connection.
     */
    protected function fixConnectionSequences(string $connectionName, ?string $specificTable, bool $dryRun): void
    {
        $connection = DB::connection($connectionName);

        if ($connection->getDriverName() !== 'pgsql') {
            $this->warn(sprintf('Skipping connection [%s] because it is not PostgreSQL.', $connectionName));

            return;
        }

        $this->info(sprintf('Connection: %s', $connectionName));

        $sequences = $this->getSequences($connectionName, $specificTable);

        if ($sequences->isEmpty()) {
            $this->info('No sequences found to fix.');
            $this->newLine();

            return;
        }

        $fixed = 0;
        $skipped = 0;

        $this->table(
            ['Table', 'Column', 'Sequence', 'Current Value', 'Max ID', 'Status'],
            $sequences->map(function (object $seq) use ($connectionName, $dryRun, &$fixed, &$skipped): array {
                $maxId = $this->getMaxId($connectionName, $seq->tablename, $seq->column_name);
                $currentValue = $this->getSequenceValue($connectionName, $seq->sequence_name);

                $needsFix = $currentValue < $maxId;
                $status = $needsFix ? '<fg=yellow>NEEDS FIX</>' : '<fg=green>OK</>';

                if ($needsFix) {
                    if (! $dryRun) {
                        $this->fixSequence($connectionName, $seq->sequence_name, $maxId);
                        $status = '<fg=green>FIXED</>';
                    }

                    $fixed++;
                }

                if (! $needsFix) {
                    $skipped++;
                }

                return [
                    $seq->tablename,
                    $seq->column_name,
                    $seq->sequence_name,
                    $currentValue,
                    $maxId ?: '(empty)',
                    $status,
                ];
            })->all()
        );

        $this->newLine();

        if ($dryRun) {
            $this->info(sprintf('Would fix %d sequence(s), %d already OK.', $fixed, $skipped));
            $this->newLine();

            return;
        }

        $this->info(sprintf('Fixed %d sequence(s), %d already OK.', $fixed, $skipped));
        $this->newLine();
    }

    /**
     * Get the requested table option.
     */
    protected function specificTableOption(): ?string
    {
        $table = $this->option('table');

        return is_string($table) && $table !== '' ? $table : null;
    }

    /**
     * Get the connection names that should be fixed.
     *
     * @return array<int, string>
     */
    protected function connectionNames(): array
    {
        $requestedConnections = $this->option('connection');

        if ($requestedConnections !== []) {
            $connections = array_values(array_filter($requestedConnections, is_string(...)));

            if (in_array('all', $connections, true)) {
                return $this->postgresConnectionNames();
            }

            return array_values(array_unique($connections));
        }

        return $this->defaultConnectionNames();
    }

    /**
     * Get default connections that should be fixed.
     *
     * @return array<int, string>
     */
    protected function defaultConnectionNames(): array
    {
        $defaultConnection = config('database.default');
        $activityLogConnection = $this->activityLogConnectionName();

        $connections = is_string($defaultConnection) ? [$defaultConnection] : [];

        if (
            $activityLogConnection !== null && $activityLogConnection !== '' &&
            ! in_array($activityLogConnection, $connections, true)
        ) {
            $connections[] = $activityLogConnection;
        }

        return $connections;
    }

    /**
     * Get the configured activity log connection name.
     */
    protected function activityLogConnectionName(): ?string
    {
        $activityModel = config('activitylog.activity_model');

        if (! is_string($activityModel) || ! is_a($activityModel, Model::class, true)) {
            return null;
        }

        return new $activityModel()->getConnectionName();
    }

    /**
     * Get all configured PostgreSQL connection names.
     *
     * @return array<int, string>
     */
    protected function postgresConnectionNames(): array
    {
        $connections = config('database.connections');

        if (! is_array($connections)) {
            return [];
        }

        return collect($connections)
            ->filter(function (mixed $connection): bool {
                return is_array($connection) && ($connection['driver'] ?? null) === 'pgsql';
            })
            ->keys()
            ->filter(fn (mixed $connection): bool => is_string($connection))
            ->values()
            ->all();
    }

    /**
     * Quote a PostgreSQL identifier.
     */
    protected function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
