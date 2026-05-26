<?php
namespace Core\App;

/**
 * UI helper for rendering non-admin profile pages from rad/data/uitpl/profile/*.
 */
class ProfileUi {
    private array $runData;
    private UiTemplate $ui;

    public function __construct(array $runData, ?UiTemplate $ui = null) {
        $this->runData = $runData;
        $this->ui = $ui ?: new UiTemplate($runData['config'] ?? []);
    }

    public function renderOverview(array $vars = []): string {
        return $this->ui->render('profile/overview', $vars);
    }

    public function renderSessions(array $vars = []): string {
        return $this->ui->render('profile/sessions', $vars);
    }

    public function renderPreferences(array $vars = []): string {
        return $this->ui->render('profile/preferences', $vars);
    }

    public function renderNotifications(array $vars = []): string {
        return $this->ui->render('profile/notifications', $vars);
    }

    public function renderMfa(array $vars = []): string {
        return $this->ui->render('profile/mfa', $vars);
    }

    public function renderChangePassword(array $vars = []): string {
        return $this->ui->render('profile/change-password', $vars);
    }
}
