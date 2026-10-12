<?php

// SqlDumpStatementSplitter.php — Split phpMyAdmin/mysqldump SQL into executable statements.
//
// exports: SqlDumpStatementSplitter | SqlDumpStatementSplitter::statements(string $sql): list<string>
// used_by: app/Services/Hosting/DomainBackupManager.php
//         tests/Unit/SqlDumpStatementSplitterTest.php
// rules:   Strip line comments inside each segment; never drop a segment only because it starts with --.
// agent:   composer-2.5-fast | cursor | 2026-10-12 | s_db_import_parser | Fix DROP TABLE after phpMyAdmin comment blocks
// message:

namespace App\Support;

class SqlDumpStatementSplitter
{
    /**
     * @return list<string>
     */
    public static function statements(string $sql): array
    {
        $parts = preg_split('/;\s*\R/', $sql) ?: [];
        $out = [];

        foreach ($parts as $part) {
            $statement = self::stripLeadingComments(trim($part));

            if ($statement === '') {
                continue;
            }

            $out[] = $statement;
        }

        return $out;
    }

    private static function stripLeadingComments(string $segment): string
    {
        if ($segment === '') {
            return '';
        }

        $lines = preg_split('/\R/', $segment) ?: [];
        $kept = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }

            $kept[] = $line;
        }

        return trim(implode("\n", $kept));
    }
}
