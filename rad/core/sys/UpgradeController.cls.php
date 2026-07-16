<?php
namespace Core\Sys;

final class UpgradeController
{
    private MigrationService $migrations;

    public function __construct(
        array $config,
        Database $db,
        Logger $logger,
        ErrorHandler $errorHandler,
    ) {
        $this->migrations = new MigrationService($config, $db, $logger);
    }

    public function handleCli(array $argv): void
    {
        $this->writeln('RAD Upgrade Runner');
        if (in_array('--status', $argv, true)) {
            foreach ($this->status() as $migration) {
                $this->writeln(sprintf('%-12s %s', strtoupper($migration['status']), $migration['id']));
            }
            return;
        }
        foreach ($argv as $argument) {
            if (str_starts_with((string)$argument, '--rollback=')) {
                $id = substr((string)$argument, strlen('--rollback='));
                $result = $this->rollbackUpgrade($id);
                $this->writeln($result['message']);
                return;
            }
        }
        $this->migrations->applyPending(fn (string $message) => $this->writeln($message));
    }

    public function status(): array
    {
        return $this->migrations->status();
    }

    public function rollbackUpgrade(string $upgradeId): array
    {
        return $this->migrations->rollback($upgradeId);
    }

    private function writeln(string $message): void
    {
        echo $message . PHP_EOL;
    }
}
