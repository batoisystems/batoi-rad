<?php
namespace Core\App;

/**
 * Simple UI template renderer for embedding auth screens in custom routes.
 *
 * Usage:
 * $ui = new \Core\App\UiTemplate($this->runData['config']);
 * echo $ui->render('auth/forgot-password', [
 *     'action' => '/forgot-password',
 *     'message' => 'Check your inbox.',
 * ]);
 */
class UiTemplate {
    private array $config;
    private string $baseDir;

    public function __construct(array $config, ?string $baseDir = null) {
        $this->config = $config;
        $this->baseDir = $baseDir ?: __DIR__ . '/../../data/uitpl';
    }

    public function render(string $path, array $vars = []): string {
        $path = ltrim($path, '/');
        $file = rtrim($this->baseDir, '/') . '/' . $path . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('UI template not found: ' . $file);
        }
        $vars['config'] = $this->config;
        ob_start();
        extract($vars, EXTR_SKIP);
        include $file;
        return ob_get_clean() ?: '';
    }
}
