<?php
namespace Core\Sys;

class DataSchemaService {
    private Database $db;
    private ErrorHandler $errorHandler;

    private array $systemColumns = [];
    private array $systemColumnMeta = [
        ['Field' => 'id', 'Type' => 'bigint(20)', 'Null' => 'NO', 'Default' => null, 'Extra' => 'auto_increment'],
        ['Field' => 'uid', 'Type' => 'char(36)', 'Null' => 'YES', 'Default' => null, 'Extra' => ''],
        ['Field' => 'livestatus', 'Type' => "enum('0','1','2','3')", 'Null' => 'NO', 'Default' => '0', 'Extra' => "COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended'"],
        ['Field' => 'versioncode', 'Type' => 'int(11)', 'Null' => 'YES', 'Default' => null, 'Extra' => ''],
        ['Field' => 'wf_status', 'Type' => 'int(11)', 'Null' => 'NO', 'Default' => '0', 'Extra' => ''],
        ['Field' => 'space_id', 'Type' => 'bigint(20)', 'Null' => 'NO', 'Default' => '0', 'Extra' => ''],
        ['Field' => 'tenant_id', 'Type' => 'bigint(20)', 'Null' => 'YES', 'Default' => null, 'Extra' => ''],
        ['Field' => 'createdby', 'Type' => 'bigint(20)', 'Null' => 'YES', 'Default' => null, 'Extra' => ''],
        ['Field' => 'createstamp', 'Type' => 'datetime', 'Null' => 'YES', 'Default' => null, 'Extra' => ''],
        ['Field' => 'updatedby', 'Type' => 'bigint(20)', 'Null' => 'YES', 'Default' => null, 'Extra' => ''],
        ['Field' => 'updatestamp', 'Type' => 'timestamp', 'Null' => 'YES', 'Default' => 'CURRENT_TIMESTAMP', 'Extra' => ''],
    ];
    private array $systemIndexes = [
        'PRIMARY KEY (`id`)',
        'UNIQUE KEY `uid` (`uid`)'
    ];

    private array $inputTypeMap = [
        'text' => ['pattern' => 'VARCHAR(%length%)', 'length' => 255, 'category' => 'string'],
        'email' => ['pattern' => 'VARCHAR(255)', 'category' => 'string'],
        'url' => ['pattern' => 'VARCHAR(512)', 'category' => 'string'],
        'password' => ['pattern' => 'VARCHAR(255)', 'category' => 'string'],
        'number' => ['pattern' => 'INT', 'category' => 'numeric'],
        'decimal' => ['pattern' => 'DECIMAL(%precision%,%scale%)', 'precision' => 15, 'scale' => 4, 'category' => 'numeric'],
        'phone' => ['pattern' => 'VARCHAR(32)', 'category' => 'string'],
        'phone_no_cc' => ['pattern' => 'VARCHAR(32)', 'category' => 'string'],
        'money' => ['pattern' => 'DECIMAL(15,2)', 'category' => 'numeric'],
        'money_no_cc' => ['pattern' => 'DECIMAL(15,2)', 'category' => 'numeric'],
        'percentage' => ['pattern' => 'DECIMAL(7,4)', 'category' => 'numeric'],
        'single_line_encrypted_text' => ['pattern' => 'VARBINARY(512)', 'category' => 'binary'],
        'multi_line_encrypted_text' => ['pattern' => 'VARBINARY(2048)', 'category' => 'binary'],
        'auto_suggest' => ['pattern' => 'VARCHAR(255)', 'category' => 'string'],
        'text_area' => ['pattern' => 'TEXT', 'category' => 'string'],
        'rich_text' => ['pattern' => 'LONGTEXT', 'category' => 'string'],
        'enum' => ['pattern' => 'VARCHAR(255)', 'category' => 'string'],
        'single_checkbox' => ['pattern' => 'TINYINT(1)', 'category' => 'numeric'],
        'multi_checkbox' => ['pattern' => 'TEXT', 'category' => 'json'],
        'multi_select' => ['pattern' => 'TEXT', 'category' => 'json'],
        'radio' => ['pattern' => 'VARCHAR(255)', 'category' => 'string'],
        'date' => ['pattern' => 'DATE', 'category' => 'date'],
        'datetime' => ['pattern' => 'DATETIME', 'category' => 'date'],
        'time' => ['pattern' => 'TIME', 'category' => 'date'],
        'boolean' => ['pattern' => 'TINYINT(1)', 'category' => 'numeric'],
        'json' => ['pattern' => 'JSON', 'category' => 'json'],
        'time_picker' => ['pattern' => 'TIME', 'category' => 'date'],
        'date_range_picker' => ['pattern' => 'VARCHAR(255)', 'category' => 'string'],
        'color_picker' => ['pattern' => 'VARCHAR(32)', 'category' => 'string'],
        'credit_card_field' => ['pattern' => 'VARBINARY(1024)', 'category' => 'binary'],
        'file' => ['pattern' => 'VARCHAR(512)', 'category' => 'string'],
        'multi_file' => ['pattern' => 'TEXT', 'category' => 'json'],
        'foreign_key' => ['pattern' => 'BIGINT(20)', 'category' => 'numeric'],
        'csv_foreign_keys' => ['pattern' => 'TEXT', 'category' => 'string'],
        'custom' => ['pattern' => '', 'category' => 'string'],
    ];
    private array $uiInputConfig = [
        'text' => ['control' => 'text'],
        'email' => ['control' => 'email'],
        'url' => ['control' => 'url'],
        'password' => ['control' => 'password'],
        'number' => ['control' => 'number'],
        'decimal' => ['control' => 'number', 'attributes' => ['step' => '0.01']],
        'phone' => ['control' => 'tel'],
        'phone_no_cc' => ['control' => 'tel'],
        'money' => ['control' => 'number', 'attributes' => ['step' => '0.01']],
        'money_no_cc' => ['control' => 'number', 'attributes' => ['step' => '0.01']],
        'percentage' => ['control' => 'number', 'attributes' => ['step' => '0.01']],
        'single_line_encrypted_text' => ['control' => 'password', 'encrypted' => true],
        'multi_line_encrypted_text' => ['control' => 'textarea', 'encrypted' => true],
        'auto_suggest' => ['control' => 'auto-suggest', 'supports_source' => true],
        'text_area' => ['control' => 'textarea'],
        'rich_text' => ['control' => 'textarea', 'rich_text' => true],
        'enum' => ['control' => 'select', 'supports_options' => true],
        'single_checkbox' => ['control' => 'checkbox'],
        'multi_checkbox' => ['control' => 'checkbox', 'supports_options' => true, 'multiple' => true],
        'multi_select' => ['control' => 'select', 'supports_options' => true, 'multiple' => true],
        'radio' => ['control' => 'radio', 'supports_options' => true],
        'date' => ['control' => 'date'],
        'datetime' => ['control' => 'datetime-local'],
        'time' => ['control' => 'time'],
        'time_picker' => ['control' => 'time'],
        'date_range_picker' => ['control' => 'date-range'],
        'color_picker' => ['control' => 'color'],
        'credit_card_field' => ['control' => 'credit-card'],
        'file' => ['control' => 'file'],
        'multi_file' => ['control' => 'file', 'multiple' => true],
        'foreign_key' => ['control' => 'select', 'supports_foreign_key' => true],
        'csv_foreign_keys' => ['control' => 'select', 'supports_foreign_key' => true, 'multiple' => true],
        'custom' => ['control' => 'text', 'supports_custom_sql' => true],
    ];

    public function __construct(Database $db, ErrorHandler $errorHandler) {
        $this->db = $db;
        $this->errorHandler = $errorHandler;
        $this->systemColumns = array_map(fn($col) => $col['Field'], $this->systemColumnMeta);
    }

    public function resolveController($identifier): array {
        $where = [];
        if (is_numeric($identifier)) {
            $where['id'] = (int)$identifier;
        } else {
            $where['uid'] = $identifier;
        }
        $rows = $this->db->select('s_mscontroller', $where, true);
        if (count($rows) !== 1) {
            throw new \RuntimeException('Controller not found.');
        }
        $controller = $rows[0];
        if (($controller['s_type'] ?? '') !== 'DM') {
            throw new \RuntimeException('Only Data Manager controllers can be managed via schema service.');
        }
        return $controller;
    }

    public function listFields(int $controllerId): array {
        return $this->db->select(
            's_data_field',
            ['s_mscontroller_id' => $controllerId],
            true,
            ['s_sort_order' => 'ASC']
        );
    }

    public function describeFieldTypeRow(array $fieldType): array {
        $definitionJson = $fieldType['s_definition'] ?? '';
        $definition = $definitionJson ? json_decode($definitionJson, true) : [];
        $inputType = strtolower($definition['input_type'] ?? ($fieldType['s_name'] ?? 'text'));
        $map = $this->inputTypeMap[$inputType] ?? $this->inputTypeMap['text'];
        $pattern = $map['pattern'] ?? '';
        $supportsLength = strpos($pattern, '%length%') !== false;
        $supportsPrecision = strpos($pattern, '%precision%') !== false;
        $supportsScale = strpos($pattern, '%scale%') !== false;
        $ui = $this->uiInputConfig[$inputType] ?? ['control' => 'text'];
        return [
            'input_type' => $inputType,
            'supports_length' => $supportsLength,
            'default_length' => $map['length'] ?? null,
            'supports_precision' => $supportsPrecision,
            'default_precision' => $map['precision'] ?? null,
            'supports_scale' => $supportsScale,
            'default_scale' => $map['scale'] ?? null,
            'category' => $map['category'] ?? 'string',
            'ui' => $ui,
            'definition_defaults' => $definition,
        ];
    }

    public function addField(int $controllerId, array $payload, int $userId = 0): array {
        $controller = $this->resolveController($controllerId);
        $tableName = $this->controllerTable($controller);
        $fieldName = $this->sanitizeFieldName($payload['field_name'] ?? $payload['label'] ?? '');
        if ($fieldName === '') {
            return ['success' => false, 'message' => 'Field name cannot be empty.'];
        }
        if ($this->isSystemColumn($fieldName)) {
            return ['success' => false, 'message' => 'The specified field name is reserved.'];
        }
        $columns = $this->describeTable($tableName);
        if (isset($columns[$fieldName])) {
            return ['success' => false, 'message' => 'Column already exists on this table.'];
        }

        $fieldTypeId = (int)($payload['field_type_id'] ?? 0);
        $fieldType = $this->fetchFieldType($fieldTypeId);
        if (!$fieldType) {
            return ['success' => false, 'message' => 'Invalid field type.'];
        }

        $nullable = array_key_exists('nullable', $payload) ? (bool)$payload['nullable'] : true;
        $shouldIndex = !empty($payload['create_index']) && !$this->isSystemColumn($fieldName);
        $definition = $this->determineSqlDefinition($fieldType, $payload);
        $defaultSql = $this->defaultFragment($definition['category'], $nullable);
        $nullFragment = $nullable ? 'NULL' : 'NOT NULL';

        $alter = sprintf(
            "ALTER TABLE `%s` ADD COLUMN `%s` %s %s %s",
            $tableName,
            $fieldName,
            $definition['definition'],
            $nullFragment,
            $defaultSql
        );

        try {
            $this->db->query($alter);
        } catch (\Throwable $e) {
            $this->errorHandler->reportError('Schema alteration failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to add column to table.'];
        }

        if ($shouldIndex) {
            $this->addManagedIndex($tableName, $fieldName, (int)$controller['id']);
        }

        $label = trim($payload['label'] ?? ucwords(str_replace('_', ' ', $fieldName)));
        $definitionPayload = $this->buildDefinitionPayload(array_merge($payload, ['create_index' => $shouldIndex]));

        $insertData = [
            's_mscontroller_id' => $controllerId,
            's_field_group_id' => $payload['field_group_id'] ?? null,
            's_sort_order' => $payload['sort_order'] ?? null,
            's_field_name' => $fieldName,
            's_field_label' => $label,
            's_help_text' => $payload['help_text'] ?? '',
            's_field_type_id' => $fieldTypeId,
            's_is_nullable' => $nullable ? 1 : 0,
            's_definition' => $definitionPayload,
        ];

        $newId = $this->db->insert('s_data_field', $insertData);
        return [
            'success' => true,
            'field_id' => $newId,
            'message' => 'Field created successfully.'
        ];
    }

    public function updateField(int $controllerId, int $fieldId, array $payload): array {
        $controller = $this->resolveController($controllerId);
        $tableName = $this->controllerTable($controller);
        $field = $this->fetchField($controllerId, $fieldId);
        if (!$field) {
            return ['success' => false, 'message' => 'Field not found.'];
        }
        $oldName = $field['s_field_name'];
        if ($this->isSystemColumn($oldName)) {
            return ['success' => false, 'message' => 'System fields cannot be modified.'];
        }

        $newName = $this->sanitizeFieldName($payload['field_name'] ?? $oldName);
        $nullable = array_key_exists('nullable', $payload) ? (bool)$payload['nullable'] : (bool)$field['s_is_nullable'];
        $fieldTypeId = isset($payload['field_type_id']) ? (int)$payload['field_type_id'] : (int)$field['s_field_type_id'];
        $fieldType = $this->fetchFieldType($fieldTypeId);
        if (!$fieldType) {
            return ['success' => false, 'message' => 'Invalid field type.'];
        }
        $payload = $this->mergeDefinitionDefaults($payload, $field['s_definition']);
        $definition = $this->determineSqlDefinition($fieldType, $payload);
        $defaultSql = $this->defaultFragment($definition['category'], $nullable);
        $nullFragment = $nullable ? 'NULL' : 'NOT NULL';
        $previousIndex = $this->definitionHasIndex($field['s_definition']);
        if (!array_key_exists('create_index', $payload)) {
            $payload['create_index'] = $previousIndex;
        }
        $shouldIndex = !empty($payload['create_index']) && !$this->isSystemColumn($newName);

        $alter = sprintf(
            "ALTER TABLE `%s` CHANGE COLUMN `%s` `%s` %s %s %s",
            $tableName,
            $oldName,
            $newName,
            $definition['definition'],
            $nullFragment,
            $defaultSql
        );

        try {
            $this->db->query($alter);
        } catch (\Throwable $e) {
            $this->errorHandler->reportError('Schema alteration failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update database column.'];
        }

        if ($previousIndex && (!$shouldIndex || $oldName !== $newName)) {
            $this->dropManagedIndex($tableName, $oldName, (int)$controller['id']);
        }
        if ($shouldIndex) {
            $this->addManagedIndex($tableName, $newName, (int)$controller['id']);
        }

        $definitionPayload = $this->buildDefinitionPayload($payload);

        $updateData = [
            's_field_name' => $newName,
            's_field_label' => trim($payload['label'] ?? $field['s_field_label']),
            's_help_text' => $payload['help_text'] ?? $field['s_help_text'],
            's_field_type_id' => $fieldTypeId,
            's_is_nullable' => $nullable ? 1 : 0,
            's_field_group_id' => $payload['field_group_id'] ?? $field['s_field_group_id'],
            's_sort_order' => $payload['sort_order'] ?? $field['s_sort_order'],
            's_definition' => $definitionPayload,
        ];

        $this->db->update('s_data_field', $updateData, ['id' => $fieldId]);

        return ['success' => true, 'message' => 'Field updated successfully.'];
    }

    public function deleteField(int $controllerId, int $fieldId): array {
        $controller = $this->resolveController($controllerId);
        $tableName = $this->controllerTable($controller);
        $field = $this->fetchField($controllerId, $fieldId);
        if (!$field) {
            return ['success' => false, 'message' => 'Field not found.'];
        }
        $column = $field['s_field_name'];
        if ($this->isSystemColumn($column)) {
            return ['success' => false, 'message' => 'System fields cannot be removed.'];
        }

        try {
            $sql = sprintf("ALTER TABLE `%s` DROP COLUMN `%s`", $tableName, $column);
            $this->db->query($sql);
        } catch (\Throwable $e) {
            $this->errorHandler->reportError('Schema alteration failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to drop column from table.'];
        }

        if ($this->definitionHasIndex($field['s_definition'])) {
            $this->dropManagedIndex($tableName, $column, (int)$controller['id']);
        }

        $this->db->delete('s_data_field', ['id' => $fieldId]);

        return ['success' => true, 'message' => 'Field deleted successfully.'];
    }

    public function applyIndexState(int $controllerId, string $fieldName, bool $shouldHaveIndex): void {
        $controller = $this->resolveController($controllerId);
        $tableName = $this->controllerTable($controller);
        if ($this->isSystemColumn($fieldName)) {
            return;
        }
        if ($shouldHaveIndex) {
            $this->addManagedIndex($tableName, $fieldName, $controllerId);
        } else {
            $this->dropManagedIndex($tableName, $fieldName, $controllerId);
        }
    }

    private function controllerTable(array $controller): string {
        $name = trim((string)($controller['s_name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('Controller name missing.');
        }
        $table = 'a_' . $name;
        if (!$this->isManagedTable($table)) {
            throw new \RuntimeException('Only tables starting with a_ can be managed.');
        }
        return $table;
    }

    public function ensureControllerTable(array $controller): void {
        $table = $this->controllerTable($controller);
        $columns = [];
        try {
            $columns = $this->describeTable($table);
        } catch (\Throwable $e) {
            // If table exists but describe failed, do not attempt to recreate (avoid duplicate system columns)
            if ($this->tableExists($table)) {
                return;
            }
            $this->createBaseControllerTable($controller);
            $columns = $this->describeTable($table);
        }
        $this->synchronizeColumns($controller, $columns);
    }

    private function tableExists(string $table): bool {
        try {
            $result = $this->db->query('SHOW TABLES LIKE :t', [':t' => $table]);
            return is_array($result) && count($result) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getSystemColumns(): array {
        return $this->systemColumns;
    }

    public function isSystemColumn(string $column): bool {
        return in_array(strtolower($column), $this->systemColumns, true);
    }

    public function getSystemColumnMetadata(): array {
        return $this->systemColumnMeta;
    }

    public function buildSqlFromFieldRow(array $fieldRow): ?array {
        $fieldTypeId = (int)($fieldRow['s_field_type_id'] ?? 0);
        $fieldType = $this->fetchFieldType($fieldTypeId);
        if (!$fieldType) {
            return null;
        }
        $decodedDefinition = [];
        if (!empty($fieldRow['s_definition'])) {
            $decodedDefinition = json_decode($fieldRow['s_definition'], true) ?: [];
        }
        $nullable = (bool)($fieldRow['s_is_nullable'] ?? true);
        $definition = $this->determineSqlDefinition($fieldType, $decodedDefinition);
        $defaultSql = $this->defaultFragment($definition['category'], $nullable);
        $nullFragment = $nullable ? 'NULL' : 'NOT NULL';

        return [
            'sql' => trim(sprintf('%s %s %s', $definition['definition'], $nullFragment, $defaultSql)),
            'category' => $definition['category'],
            'nullable' => $nullable,
            'definition' => $definition['definition'],
            'index' => $this->definitionHasIndex($fieldRow['s_definition']),
        ];
    }

    private function columnMetaToSql(array $meta): string {
        $definition = sprintf('`%s` %s', $meta['Field'], strtoupper($meta['Type']));
        if (($meta['Null'] ?? 'NO') === 'NO') {
            $definition .= ' NOT NULL';
        } else {
            $definition .= ' NULL';
        }
        if (isset($meta['Default']) && $meta['Default'] !== null) {
            $definition .= " DEFAULT " . (strtoupper($meta['Default']) === 'CURRENT_TIMESTAMP'
                ? 'CURRENT_TIMESTAMP'
                : "'" . $meta['Default'] . "'");
        } elseif (($meta['Null'] ?? 'YES') === 'YES') {
            $definition .= ' DEFAULT NULL';
        }
        if (!empty($meta['Extra'])) {
            $definition .= ' ' . $meta['Extra'];
        }
        return $definition;
    }

    private function isManagedTable(string $table): bool {
        return (bool)preg_match('/^a_[A-Za-z0-9_]+$/', $table);
    }

    private function createBaseControllerTable(array $controller): void {
        $table = $this->controllerTable($controller);
        // Avoid creating if table already exists to prevent duplicate system columns
        if ($this->tableExists($table)) {
            return;
        }
        $seen = [];
        $columns = [];
        foreach ($this->systemColumnMeta as $meta) {
            $key = strtolower($meta['Field']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $columns[] = $this->columnMetaToSql($meta);
        }
        $indexes = $this->systemIndexes;
        $blueprint = array_merge($columns, $indexes);
        $sql = sprintf(
            "CREATE TABLE `%s` (\n%s\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            $table,
            implode(",\n", $blueprint)
        );
        $this->db->query($sql);
    }

    private function synchronizeColumns(array $controller, array $currentColumns): void {
        $table = $this->controllerTable($controller);
        $currentMap = [];
        foreach ($currentColumns as $column) {
            $currentMap[strtolower($column['Field'])] = $column;
        }
        $fields = $this->listFields((int)($controller['id'] ?? 0));
        foreach ($fields as $fieldRow) {
            if (empty($fieldRow['s_field_name'])) {
                continue;
            }
            $fieldName = strtolower($fieldRow['s_field_name']);
            if ($this->isSystemColumn($fieldName)) {
                continue;
            }
            $definition = $this->buildSqlFromFieldRow($fieldRow);
            if (!$definition) {
                continue;
            }
            if (!isset($currentMap[$fieldName])) {
                $sql = sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $fieldRow['s_field_name'], $definition['sql']);
                try {
                    $this->db->query($sql);
                    $currentMap[$fieldName] = [
                        'Field' => $fieldRow['s_field_name'],
                        'Type' => $definition['definition'],
                        'Null' => $definition['nullable'] ? 'YES' : 'NO',
                        'Default' => null,
                        'Extra' => '',
                    ];
                } catch (\Throwable $e) {
                    if (stripos($e->getMessage(), 'Duplicate column') === false) {
                        throw $e;
                    }
                }
            }
            if (isset($definition['index'])) {
                $this->applyIndexState((int)$controller['id'], $fieldRow['s_field_name'], (bool)$definition['index']);
            }
        }
    }

    private function sanitizeFieldName(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value);
        $value = trim($value, '_');
        if ($value === '') {
            $value = 'field';
        }
        if (strpos($value, 'a_') !== 0) {
            $value = 'a_' . $value;
        }
        return $value;
    }

    private function fetchFieldType(int $fieldTypeId): ?array {
        if ($fieldTypeId <= 0) {
            return null;
        }
        $rows = $this->db->select('s_data_field_type', ['id' => $fieldTypeId], true);
        return $rows[0] ?? null;
    }

    private function fetchField(int $controllerId, int $fieldId): ?array {
        $rows = $this->db->select(
            's_data_field',
            ['id' => $fieldId, 's_mscontroller_id' => $controllerId],
            true
        );
        return $rows[0] ?? null;
    }

    private function describeTable(string $table): array {
        $columns = [];
        $result = $this->db->query(sprintf('SHOW COLUMNS FROM `%s`', $table));
        foreach ($result as $row) {
            $columns[$row['Field']] = $row;
        }
        return $columns;
    }

    private function determineSqlDefinition(array $fieldType, array $payload): array {
        $definitionJson = $fieldType['s_definition'] ?? null;
        $definition = $definitionJson ? json_decode($definitionJson, true) : [];
        $inputType = strtolower($definition['input_type'] ?? $fieldType['s_name'] ?? 'text');
        $map = $this->inputTypeMap[$inputType] ?? $this->inputTypeMap['text'];

        $sql = $map['pattern'];
        if (str_contains($sql, '%length%')) {
            $length = isset($payload['length']) ? max(1, (int)$payload['length']) : ($map['length'] ?? 255);
            $sql = str_replace('%length%', (string)$length, $sql);
        }
        if (str_contains($sql, '%precision%')) {
            $precision = isset($payload['precision']) ? (int)$payload['precision'] : ($map['precision'] ?? 15);
            $scale = isset($payload['scale']) ? (int)$payload['scale'] : ($map['scale'] ?? 4);
            $sql = str_replace(['%precision%', '%scale%'], [$precision, $scale], $sql);
        }

        return [
            'definition' => $sql,
            'category' => $map['category'] ?? 'string',
        ];
    }

    private function defaultFragment(string $category, bool $nullable): string {
        if ($nullable) {
            return 'DEFAULT NULL';
        }
        return match ($category) {
            'numeric' => 'DEFAULT 0',
            'date' => "DEFAULT '1970-01-01'",
            'binary' => "DEFAULT ''",
            default => "DEFAULT ''",
        };
    }

    private function buildDefinitionPayload(array $payload): ?string {
        $meta = [];
        if (isset($payload['length']) && $payload['length'] !== '') {
            $meta['length'] = (int)$payload['length'];
        }
        if (isset($payload['precision']) && $payload['precision'] !== '') {
            $meta['precision'] = (int)$payload['precision'];
        }
        if (isset($payload['scale']) && $payload['scale'] !== '') {
            $meta['scale'] = (int)$payload['scale'];
        }
        if (isset($payload['create_index'])) {
            $meta['index'] = (bool)$payload['create_index'];
        }
        if (!empty($payload['options']) && is_array($payload['options'])) {
            $meta['options'] = $this->normalizeOptions($payload['options']);
        }
        if (!empty($payload['foreign_table'])) {
            $meta['related_table'] = $payload['foreign_table'];
        }
        if (!empty($payload['foreign_field'])) {
            $meta['related_field'] = $payload['foreign_field'];
        }
        if (!empty($payload['source'])) {
            $meta['source'] = $payload['source'];
        }
        if (!empty($payload['custom_sql'])) {
            $meta['custom_sql'] = $payload['custom_sql'];
        }
        return !empty($meta) ? json_encode($meta) : null;
    }

    private function mergeDefinitionDefaults(array $payload, ?string $definition): array {
        if (!$definition) {
            return $payload;
        }
        $decoded = json_decode($definition, true);
        if (!is_array($decoded)) {
            return $payload;
        }
        foreach (['length', 'precision', 'scale'] as $key) {
            if (!isset($payload[$key]) && isset($decoded[$key])) {
                $payload[$key] = $decoded[$key];
            }
        }
        if (!isset($payload['create_index']) && isset($decoded['index'])) {
            $payload['create_index'] = (bool)$decoded['index'];
        }
        foreach (['options', 'related_table', 'related_field', 'source', 'custom_sql'] as $key) {
            if (!isset($payload[$key]) && isset($decoded[$key])) {
                $payload[$key] = $decoded[$key];
            }
        }
        return $payload;
    }

    private function normalizeOptions(array $raw): array {
        $options = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $value = trim($item);
                if ($value === '') {
                    continue;
                }
                $options[] = ['value' => $value, 'label' => $value];
                continue;
            }
            if (is_array($item)) {
                $value = trim((string)($item['value'] ?? $item[0] ?? ''));
                if ($value === '') {
                    continue;
                }
                $label = trim((string)($item['label'] ?? $item['text'] ?? $item[1] ?? $value));
                $options[] = ['value' => $value, 'label' => $label];
                continue;
            }
            if (is_object($item) && isset($item->value)) {
                $value = trim((string)$item->value);
                if ($value === '') {
                    continue;
                }
                $label = trim((string)($item->label ?? $value));
                $options[] = ['value' => $value, 'label' => $label];
            }
        }
        return $options;
    }

    private function definitionHasIndex(?string $definition): bool {
        if (!$definition) {
            return false;
        }
        $decoded = json_decode($definition, true);
        if (!is_array($decoded)) {
            return false;
        }
        return !empty($decoded['index']);
    }

    private function addManagedIndex(string $table, string $column, int $controllerId): void {
        $indexName = $this->managedIndexName($controllerId, $column);
        if ($this->indexExists($table, $indexName)) {
            return;
        }
        try {
            $this->db->query(sprintf(
                'ALTER TABLE `%s` ADD INDEX `%s` (`%s`)',
                $table,
                $indexName,
                $column
            ));
        } catch (\Throwable $e) {
            $this->errorHandler->reportError('Unable to create index: ' . $e->getMessage());
        }
    }

    private function dropManagedIndex(string $table, string $column, int $controllerId): void {
        $indexName = $this->managedIndexName($controllerId, $column);
        if (!$this->indexExists($table, $indexName)) {
            return;
        }
        try {
            $this->db->query(sprintf(
                'ALTER TABLE `%s` DROP INDEX `%s`',
                $table,
                $indexName
            ));
        } catch (\Throwable $e) {
            $this->errorHandler->reportError('Unable to drop index: ' . $e->getMessage());
        }
    }

    private function managedIndexName(int $controllerId, string $column): string {
        $base = 'idx_dm_' . $controllerId . '_' . $column;
        return substr($base, 0, 60);
    }

    private function indexExists(string $table, string $indexName): bool {
        $rows = $this->db->query(sprintf(
            'SHOW INDEX FROM `%s` WHERE Key_name = :key_name',
            $table
        ), [':key_name' => $indexName]);
        return !empty($rows);
    }
}
