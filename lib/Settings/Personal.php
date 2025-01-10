<?php

declare(strict_types=1);

namespace OCA\SharingPath\Settings;

use OCA\SharingPath\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\Settings\ISettings;

class Personal implements ISettings {

	private $config;

	private $appConfig;

	public function __construct(IConfig $config, IAppConfig $appConfig) {
		$this->config = $config;
		$this->appConfig = $appConfig;
	}

	public function getForm() {
		$uid = \OC_User::getUser();
		$enabled = $this->config->getUserValue($uid, Application::APP_ID, Application::SETTINGS_KEY_ENABLE);
		$defaultEnabled = $this->appConfig->getValueString(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_ENABLE);
		$prefix = $this->config->getUserValue($uid, Application::APP_ID, Application::SETTINGS_KEY_COPY_PREFIX);
		$defaultPrefix = $this->appConfig->getValueString(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_COPY_PREFIX);
		$folder = $this->config->getUserValue($uid, Application::APP_ID, Application::SETTINGS_KEY_SHARING_FOLDER);
		$defaultFolder = $this->appConfig->getValueString(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_SHARING_FOLDER);

		return new TemplateResponse(Application::APP_ID, 'settings/personal', [
			'enabled' => $enabled,
			'default_enabled' => $defaultEnabled,
			'prefix' => $prefix,
			'default_prefix' => $defaultPrefix ? (trim($defaultPrefix, '/') . '/' . $uid) : '',
			'folder' => $folder,
			'default_folder' => $defaultFolder,
		]);
	}

	public function getSection(): string {
		return 'sharing';
	}

	public function getPriority(): int {
		return 100;
	}

}
