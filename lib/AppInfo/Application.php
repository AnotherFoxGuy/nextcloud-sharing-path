<?php

declare(strict_types=1);

namespace OCA\SharingPath\AppInfo;

use OCA\SharingPath\Controller\PathController;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\Event;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IManager as IShareManager;
use OCP\Util;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {

	public const APP_ID = 'sharingpath';

	public const SETTINGS_KEY_DEFAULT_ENABLE = 'default_enabled';

	public const SETTINGS_KEY_ENABLE = 'enabled';

	public const SETTINGS_KEY_DEFAULT_COPY_PREFIX = 'default_copy_prefix';

	public const SETTINGS_KEY_COPY_PREFIX = 'copy_prefix';

	public const SETTINGS_KEY_DEFAULT_SHARING_FOLDER = 'default_sharing_folder';

	public const SETTINGS_KEY_SHARING_FOLDER = 'sharing_folder';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerService('PathController', fn (ContainerInterface $c): PathController => new PathController(
			$c->get('AppName'),
			$c->get(IRequest::class),
			$c->get(IConfig::class),
			$c->get(IAppConfig::class),
			$c->get(IUserManager::class),
			$c->get(IShareManager::class),
			$c->get(IRootFolder::class),
			$c->get(LoggerInterface::class),
			$c->get(IMimeTypeDetector::class),
			$c->get(IUserSession::class)
		));
		$context->registerEventListener(\OCA\Files\Event\LoadSidebar::class, self::class);
		$context->registerEventListener(\OCA\Files\Event\LoadAdditionalScriptsEvent::class, self::class);
	}

	public function boot(IBootContext $context): void {
	}

	public function handle(Event $event): void {
		Util::addScript(Application::APP_ID, 'sharingpath-main', 'files');
	}
}
