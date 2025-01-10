<?php

declare(strict_types=1);

namespace OCA\SharingPath\Controller;

use OCA\SharingPath\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;

class SettingsController extends Controller {

	private $config;

	private $appConfig;

	protected bool $isAdmin;

	public function __construct(
		IRequest $request,
		IConfig $config,
		IAppConfig $appConfig,
		private string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);

		$this->config = $config;
		$this->appConfig = $appConfig;
		$this->isAdmin = $this->request->getParam('type') === 'admin';
	}

	/**
	 * @NoAdminRequired
	 */
	public function index() {
		return new JSONResponse([
			Application::SETTINGS_KEY_DEFAULT_ENABLE => $this->config->getAppValue(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_ENABLE),
			Application::SETTINGS_KEY_ENABLE => $this->config->getUserValue($this->userId, Application::APP_ID, Application::SETTINGS_KEY_ENABLE),
			Application::SETTINGS_KEY_DEFAULT_COPY_PREFIX => $this->config->getAppValue(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_COPY_PREFIX),
			Application::SETTINGS_KEY_COPY_PREFIX => $this->config->getUserValue($this->userId, Application::APP_ID, Application::SETTINGS_KEY_COPY_PREFIX),
		]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function enable(string $enabled) {
		if ($this->isAdmin) {
			$this->appConfig->setValueString(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_ENABLE, $enabled);
		} else {
			$this->config->setUserValue($this->userId, Application::APP_ID, Application::SETTINGS_KEY_ENABLE, $enabled);
		}

		return new JSONResponse();
	}

	/**
	 * @NoAdminRequired
	 */
	public function setCopyPrefix(string $prefix) {
		if ($this->isAdmin) {
			$this->appConfig->setValueString(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_COPY_PREFIX, trim($prefix));
		} else {
			$this->config->setUserValue($this->userId, Application::APP_ID, Application::SETTINGS_KEY_COPY_PREFIX, trim($prefix));
		}

		return new JSONResponse();
	}

	/**
	 * @NoAdminRequired
	 */
	public function setSharingFolder(string $folder) {
		if ($this->isAdmin) {
			$this->appConfig->setValueString(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_SHARING_FOLDER, trim($folder));
		} else {
			$this->config->setUserValue($this->userId, Application::APP_ID, Application::SETTINGS_KEY_SHARING_FOLDER, trim($folder));
		}

		return new JSONResponse();
	}
}
