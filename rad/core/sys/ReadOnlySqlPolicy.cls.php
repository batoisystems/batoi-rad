<?php
namespace Core\Sys;

final class ReadOnlySqlPolicy
{
    public static function assertSafe(string $sql): string
    {
        $sql = trim($sql);
        if ($sql === '' || strlen($sql) > 10000) {
            throw new \InvalidArgumentException('A read-only SQL query is required.');
        }
        if (!preg_match('/^(SELECT|WITH)\b/i', $sql)) {
            throw new \InvalidArgumentException('Only SELECT and WITH queries are allowed.');
        }
        if (preg_match('/;|--|#|\/\*/', $sql)) {
            throw new \InvalidArgumentException('SQL comments and multiple statements are not allowed.');
        }
        $blocked = '/\b(INTO\s+(OUTFILE|DUMPFILE)|FOR\s+UPDATE|LOCK\s+IN\s+SHARE\s+MODE|LOAD_FILE|SLEEP|BENCHMARK|GET_LOCK|RELEASE_LOCK)\b/i';
        if (preg_match($blocked, $sql)) {
            throw new \InvalidArgumentException('The query uses a blocked SQL operation.');
        }
        return $sql;
    }
}
