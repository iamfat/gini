<?php

namespace Gini\Controller\CLI;

class IDE extends \Gini\Controller\CLI
{
    public function actionVSCode()
    {
        $vscodeSettingsPath = APP_PATH . '/.vscode/settings.json';
        if (file_exists($vscodeSettingsPath)) {
            $vscodeSettings = json_decode(file_get_contents($vscodeSettingsPath), true);
        } else {
            $vscodeSettings = [];
        }

        $vscodeSettings['intelephense.environment.includePaths'] = array_map(function ($module) {
            $base =  \Gini\File::relativePath($module->path, APP_PATH) ?: '.';
            return $base . '/class';
        }, array_values(\Gini\Core::$MODULE_INFO));

        $vscodeSettingsContent = json_encode($vscodeSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($vscodeSettingsPath, $vscodeSettingsContent);
        echo "VSCode settings file generated.\n";
    }
}
