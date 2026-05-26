<?php
namespace Core\Sys;

use DateTimeImmutable;

class UpgradeController {
    private $config;
    private $db;
    private $logger;
    private $errorHandler;
    private $upgradeDir;
    private $checkpointFile;

    public function __construct(array $config, Database $db, Logger $logger, ErrorHandler $errorHandler) {
        $this->config = $config;
        $this->db = $db;
        $this->logger = $logger;
        $this->errorHandler = $errorHandler;

        $this->upgradeDir = rtrim($this->config['dir']['rad'], '/') . '/upgrades';
        $this->checkpointFile = rtrim($this->config['dir']['rad'], '/') . '/data/upgrade/checkpoints.json';
    }

    public function handleCli(array $argv): void {
        $this->writeln('RAD Upgrade Runner');
        $this->ensureDirectories();

        $checkpoints = $this->loadCheckpoints();
        $upgrades = $this->loadUpgrades();

        if (empty($upgrades)) {
            $this->writeln('No upgrade scripts found in ' . $this->upgradeDir);
            return;
        }

        $locks = $checkpoints['locked'] ?? [];
        $pending = array_filter($upgrades, function ($upgrade) use ($checkpoints, $locks) {
            return !in_array($upgrade['id'], $checkpoints['applied'], true)
                && !in_array($upgrade['id'], $locks, true);
        });

        $lockedPending = array_filter($upgrades, function ($upgrade) use ($checkpoints, $locks) {
            return !in_array($upgrade['id'], $checkpoints['applied'], true)
                && in_array($upgrade['id'], $locks, true);
        });
        foreach ($lockedPending as $lockedUpgrade) {
            $this->writeln(sprintf("Skipping %s (%s) - locked on this server.", $lockedUpgrade['id'], $lockedUpgrade['description']));
        }

        if (empty($pending)) {
            $this->writeln('No pending upgrades. Database is up to date.');
            return;
        }

        foreach ($pending as $upgrade) {
            $this->runUpgrade($upgrade, $checkpoints);
        }
    }

    private function runUpgrade(array $upgrade, array &$checkpoints): void {
        $this->writeln(sprintf("Running %s (%s)", $upgrade['id'], $upgrade['description']));

        try {
            call_user_func($upgrade['run'], $this->db, $this->logger, $this->config);
            $this->writeln(sprintf("✔ Completed %s", $upgrade['id']));

            $timestamp = (new DateTimeImmutable('now'))->format(DateTimeImmutable::ATOM);
            $checkpoints['applied'][] = $upgrade['id'];
            $checkpoints['history'][] = [
                'id' => $upgrade['id'],
                'description' => $upgrade['description'],
                'executed_at' => $timestamp,
                'file' => $upgrade['file'],
            ];

            $this->saveCheckpoints($checkpoints);
        } catch (\Throwable $e) {
            $this->writeln(sprintf("✘ Failed %s: %s", $upgrade['id'], $e->getMessage()));
            $this->logger->logError('Upgrade ' . $upgrade['id'] . ' failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function ensureDirectories(): void {
        if (!is_dir($this->upgradeDir)) {
            mkdir($this->upgradeDir, 0775, true);
        }
        $checkpointDir = dirname($this->checkpointFile);
        if (!is_dir($checkpointDir)) {
            mkdir($checkpointDir, 0775, true);
        }
    }

    private function loadUpgrades(): array {
        $files = glob($this->upgradeDir . '/*.php');
        sort($files);

        $upgrades = [];
        foreach ($files as $file) {
            $definition = include $file;
            if (!is_array($definition) || empty($definition['id']) || !isset($definition['run']) || !is_callable($definition['run'])) {
                $this->writeln('Skipping invalid upgrade file: ' . $file);
                continue;
            }

            $upgrades[] = [
                'id' => $definition['id'],
                'description' => $definition['description'] ?? 'No description',
                'run' => $definition['run'],
                'rollback' => $definition['rollback'] ?? null,
                'file' => $file,
            ];
        }

        return $upgrades;
    }

    public function rollbackUpgrade(string $upgradeId): array {
        $this->ensureDirectories();
        $checkpoints = $this->loadCheckpoints();
        $upgrades = $this->loadUpgrades();
        $target = null;
        foreach ($upgrades as $upgrade) {
            if ($upgrade['id'] === $upgradeId) {
                $target = $upgrade;
                break;
            }
        }

        if ($target === null) {
            throw new \Exception(sprintf('Upgrade %s was not found.', $upgradeId));
        }

        if (!isset($target['rollback']) || !is_callable($target['rollback'])) {
            throw new \Exception(sprintf('Upgrade %s does not define a rollback handler.', $upgradeId));
        }

        if (!in_array($upgradeId, $checkpoints['applied'], true)) {
            throw new \Exception(sprintf('Upgrade %s has not been applied yet.', $upgradeId));
        }

        $latestAppliedId = empty($checkpoints['applied']) ? null : max($checkpoints['applied']);
        if ($latestAppliedId !== $upgradeId) {
            throw new \Exception(sprintf('Only the latest applied upgrade (%s) may be rolled back.', $latestAppliedId ?? 'none'));
        }

        ob_start();
        call_user_func($target['rollback'], $this->db, $this->logger, $this->config);
        $buffer = trim(ob_get_clean());

        $timestamp = (new DateTimeImmutable('now'))->format(DateTimeImmutable::ATOM);
        $checkpoints['applied'] = array_values(array_filter(
            $checkpoints['applied'],
            function ($id) use ($upgradeId) {
                return $id !== $upgradeId;
            }
        ));
        if (isset($checkpoints['locked'])) {
            $checkpoints['locked'] = array_values(array_filter(
                $checkpoints['locked'],
                function ($id) use ($upgradeId) {
                    return $id !== $upgradeId;
                }
            ));
        }

        $checkpoints['history'][] = [
            'id' => $target['id'],
            'description' => $target['description'],
            'rolled_back_at' => $timestamp,
            'file' => $target['file'],
            'action' => 'rollback',
        ];

        $this->saveCheckpoints($checkpoints);

        return [
            'message' => sprintf('Rollback completed for %s.', $upgradeId),
            'output' => $buffer === '' ? [] : preg_split("/\r\n|\n|\r/", $buffer),
        ];
    }

    private function loadCheckpoints(): array {
        if (!is_file($this->checkpointFile)) {
            return [
                'applied' => [],
                'history' => [],
                'locked' => [],
            ];
        }

        $data = json_decode(file_get_contents($this->checkpointFile), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return [
                'applied' => [],
                'history' => [],
                'locked' => [],
            ];
        }

        $data['applied'] = $data['applied'] ?? [];
        $data['history'] = $data['history'] ?? [];
        $data['locked'] = $data['locked'] ?? [];

        return $data;
    }

    private function saveCheckpoints(array $data): void {
        file_put_contents($this->checkpointFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function writeln(string $message): void {
        echo $message . PHP_EOL;
    }
}
