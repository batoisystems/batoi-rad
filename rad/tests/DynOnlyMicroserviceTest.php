<?php
declare(strict_types=1);

$rad = dirname(__DIR__);
require $rad . '/vendor/autoload.php';
require_once $rad . '/core/sys/Database.cls.php';

function assertDynOnly(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$schema = (string)file_get_contents($rad . '/admin/install/schema.sql');
assertDynOnly(
    str_contains($schema, "`s_type` enum('DYN')") && str_contains($schema, "NOT NULL DEFAULT 'DYN'"),
    'The install schema must constrain s_ms.s_type to DYN.'
);

$runtimeFiles = [
    $rad . '/core/sys/GenericController.cls.php',
    $rad . '/core/sys/ApiController.cls.php',
    $rad . '/core/sys/LoginController.cls.php',
];
$legacyBranches = ["== 'STA'", "=== 'STA'", "case 'STA'", "case 'ID'", "case 'UID'"];
foreach ($runtimeFiles as $file) {
    $source = (string)file_get_contents($file);
    foreach ($legacyBranches as $branch) {
        assertDynOnly(!str_contains($source, $branch), basename($file) . " still contains legacy routing branch {$branch}.");
    }
}

$admin = (string)file_get_contents($rad . '/admin/classes/Microservice.cls.php');
assertDynOnly(!str_contains($admin, 'function upgradetodyn'), 'The legacy type upgrade action must be removed.');
assertDynOnly(!str_contains($admin, "['STA', 'DYN'"), 'Admin imports must not accept legacy microservicelet types.');

$editView = (string)file_get_contents($rad . '/admin/ui/microservice-edit.html.php');
foreach (['value="STA"', 'value="ID"', 'value="UID"'] as $option) {
    assertDynOnly(!str_contains($editView, $option), "Microservicelet edit UI still exposes {$option}.");
}

class DynOnlyMigrationDatabase extends \Core\Sys\Database
{
    /** @var list<string> */
    public array $queries = [];

    public function __construct(private int $legacyCount)
    {
    }

    public function query($sql, $params = [])
    {
        $this->queries[] = (string)$sql;
        if (str_starts_with((string)$sql, 'SELECT COUNT(*)')) {
            return [['legacy_count' => $this->legacyCount]];
        }
        return [];
    }
}

$migration = require $rad . '/upgrades/20260811_0001_dyn_only_microservicelets.php';
$blockedDb = new DynOnlyMigrationDatabase(2);
try {
    $migration['run']($blockedDb);
    assertDynOnly(false, 'The migration must refuse databases containing non-DYN records.');
} catch (RuntimeException $exception) {
    assertDynOnly(str_contains($exception->getMessage(), '2 non-DYN'), 'The migration refusal must report the legacy record count.');
}
assertDynOnly(count($blockedDb->queries) === 1, 'A blocked migration must not alter the schema.');

$cleanDb = new DynOnlyMigrationDatabase(0);
$migration['run']($cleanDb);
assertDynOnly(
    isset($cleanDb->queries[1]) && str_contains($cleanDb->queries[1], "ENUM('DYN')"),
    'A clean migration must narrow the database enum to DYN.'
);

echo "DYN-only microservicelet tests passed.\n";
