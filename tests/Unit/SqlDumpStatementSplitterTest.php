<?php

// SqlDumpStatementSplitterTest.php — phpMyAdmin-style dumps must not drop DROP TABLE after comments.
//
// exports: SqlDumpStatementSplitterTest
// used_by: none (PHPUnit entry)
// rules:   Never put real customer SQL in tests.
// agent:   composer-2.5-fast | cursor | 2026-10-12 | s_db_import_parser | Splitter regression tests
// message:

namespace Tests\Unit;

use App\Support\SqlDumpStatementSplitter;
use Tests\TestCase;

class SqlDumpStatementSplitterTest extends TestCase
{
    public function test_keeps_drop_table_after_phpmyadmin_comment_block(): void
    {
        $sql = <<<'SQL'
--
-- Table structure for table `activities`
--

DROP TABLE IF EXISTS `activities`;
CREATE TABLE `activities` (`id` int unsigned NOT NULL);
SQL;

        $statements = SqlDumpStatementSplitter::statements($sql);

        $this->assertContains('DROP TABLE IF EXISTS `activities`', $statements);
        $this->assertTrue(
            collect($statements)->contains(fn (string $s): bool => str_contains($s, 'CREATE TABLE `activities`')),
            'Expected CREATE TABLE statement: '.json_encode($statements),
        );
    }

    public function test_skips_comment_only_segments(): void
    {
        $sql = "-- header only\n;\nSELECT 1;\n";

        $statements = SqlDumpStatementSplitter::statements($sql);

        $this->assertSame(['SELECT 1'], $statements);
    }
}
