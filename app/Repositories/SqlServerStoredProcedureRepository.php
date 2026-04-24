<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class SqlServerStoredProcedureRepository
{
    public function select(string $procedure, array $parameters = [], ?string $connection = null): array
    {
        return DB::connection($connection ?? config('database.default'))
            ->select($this->buildStatement($procedure, $parameters), array_values($parameters));
    }

    public function statement(string $procedure, array $parameters = [], ?string $connection = null): bool
    {
        return DB::connection($connection ?? config('database.default'))
            ->statement($this->buildStatement($procedure, $parameters), array_values($parameters));
    }

    private function buildStatement(string $procedure, array $parameters): string
    {
        if ($procedure === '') {
            throw new RuntimeException('El nombre del procedimiento almacenado es obligatorio.');
        }

        $placeholders = collect(array_keys($parameters))
            ->map(fn (string|int $key) => is_string($key) ? "@{$key} = ?" : '?')
            ->implode(', ');

        return $placeholders !== ''
            ? sprintf('EXEC %s %s', $procedure, $placeholders)
            : sprintf('EXEC %s', $procedure);
    }
}
