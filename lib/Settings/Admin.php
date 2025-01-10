<?php

declare(strict_types=1);

namespace OCA\SharingPath\Settings;

use OCA\SharingPath\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	private $appConfig;

	public function __construct(IConfig $config, IAppConfig $appConfig) {
		$this->appConfig = $appConfig;
	}

	public function getForm() {
		$enabled = $this->appConfig->getValueString(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_ENABLE);
		$prefix = $this->appConfig->getValueString(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_COPY_PREFIX);
		$folder = $this->appConfig->getValueString(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_SHARING_FOLDER);

		return new TemplateResponse(Application::APP_ID, 'settings/admin', [
			'enabled' => $enabled,
			'prefix' => $prefix,
			'folder' => $folder,
		]);
	}

	public function getSection(): string {
		return 'sharing';
	}

	public function getPriority(): int {
		return 100;
	}

}
