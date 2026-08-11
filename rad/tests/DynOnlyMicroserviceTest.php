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

$apiController = (string)file_get_contents($rad . '/core/sys/ApiController.cls.php');
assertDynOnly(
    str_contains($apiController, '$this->serviceName = $routeName;'),
    'The API default route must set the DYN service name before execution.'
);

$genericController = (string)file_get_contents($rad . '/core/sys/GenericController.cls.php');
assertDynOnly(!str_contains($genericController, 'legacyWorkspaceDyn'), 'Legacy workspace route detection must be removed.');
assertDynOnly(
    !str_contains($genericController, '[\'livestatus\' => \'1\', \'uid\' => $slug]'),
    'Workspace routes must resolve the documented space slug, not a legacy UID segment.'
);

$admin = (string)file_get_contents($rad . '/admin/classes/Microservice.cls.php');
assertDynOnly(!str_contains($admin, 'function upgradetodyn'), 'The legacy type upgrade action must be removed.');
assertDynOnly(!str_contains($admin, "['STA', 'DYN'"), 'Admin imports must not accept legacy microservicelet types.');
assertDynOnly(
    !str_contains($admin, "\$routeRow['id'] ?? ''"),
    'DYN route file keys must not fall back to numeric route IDs.'
);

$editView = (string)file_get_contents($rad . '/admin/ui/microservice-edit.html.php');
foreach (['value="STA"', 'value="ID"', 'value="UID"'] as $option) {
    assertDynOnly(!str_contains($editView, $option), "Microservicelet edit UI still exposes {$option}.");
}

class DynOnlyMigrationDatabase extends \Core\Sys\Database
{
    /** @var list<string> */
    public array $queries = [];

    public function __construct(private int $legacyCount, private array $legacyRows = [])
    {
    }

    public function query($sql, $params = [])
    {
        $this->queries[] = (string)$sql;
        if (str_starts_with((string)$sql, 'SELECT COUNT(*)')) {
            return [['legacy_count' => $this->legacyCount]];
        }
        if (str_starts_with((string)$sql, 'SELECT id, s_name, s_type')) {
            return $this->legacyRows;
        }
        return [];
    }
}

$migration = require $rad . '/upgrades/20260811_0001_dyn_only_microservicelets.php';
$blockedDb = new DynOnlyMigrationDatabase(2, [
    ['id' => 7, 's_name' => 'legacy-static', 's_type' => 'STA'],
    ['id' => 9, 's_name' => 'legacy-id', 's_type' => 'ID'],
]);
try {
    $migration['run']($blockedDb);
    assertDynOnly(false, 'The migration must refuse databases containing non-DYN records.');
} catch (RuntimeException $exception) {
    assertDynOnly(str_contains($exception->getMessage(), '2 non-DYN'), 'The migration refusal must report the legacy record count.');
    assertDynOnly(str_contains($exception->getMessage(), 'legacy-static (STA)'), 'The migration refusal must identify offending records.');
}
assertDynOnly(count($blockedDb->queries) === 2, 'A blocked migration may inspect records but must not alter the schema.');
assertDynOnly(
    !array_filter($blockedDb->queries, static fn (string $query): bool => str_starts_with($query, 'ALTER TABLE')),
    'A blocked migration must not alter the schema.'
);

$cleanDb = new DynOnlyMigrationDatabase(0);
$migration['run']($cleanDb);
assertDynOnly(
    isset($cleanDb->queries[1]) && str_contains($cleanDb->queries[1], "ENUM('DYN')"),
    'A clean migration must narrow the database enum to DYN.'
);

echo "DYN-only microservicelet tests passed.\n";
