<?php

declare(strict_types=1);

namespace OCA\SharingPath\Controller;

use OC\Files\Filesystem;
use OC_Response;
use OCA\SharingPath\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\UnseekableException;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class PathController extends Controller {
	private $config;

	private $appConfig;

	private $userManager;

	private $shareManager;

	private $rootFolder;

	private $mimeTypeDetector;

	private $userSession;

	public function __construct(
		$appName,
		IRequest $request,
		IConfig $config,
		IAppConfig $appConfig,
		IUserManager $userManager,
		IManager $shareManager,
		IRootFolder $rootFolder,
		private LoggerInterface $logger,
		IMimeTypeDetector $mimeTypeDetector,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request);

		$this->config = $config;
		$this->appConfig = $appConfig;
		$this->userManager = $userManager;
		$this->shareManager = $shareManager;
		$this->rootFolder = $rootFolder;
		$this->mimeTypeDetector = $mimeTypeDetector;
		$this->userSession = $userSession;
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 */
	public function index(): void {
		$this->logger->warning('request index not allowed', ['app' => Application::APP_ID]);
		http_response_code(404);
		exit;
	}

	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 */
	public function handle($uid, $path): void {
		$this->logger->warning(sprintf('user: %s, file: %s', $uid, $path), ['app' => Application::APP_ID]);
		// check user & path exist
		$user = $this->userManager->get($uid);
		if (! $user || ! $path) {
			$this->logger->warning('user or file not exist', ['app' => Application::APP_ID]);
			http_response_code(404);
			exit;
		}

		// check use is enabled sharing path
		$enabled = $this->appConfig->getValueString(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_ENABLE);
		$userEnabled = $this->config->getUserValue($uid, Application::APP_ID, Application::SETTINGS_KEY_ENABLE);
		if ($userEnabled === 'no' || (! $userEnabled && $enabled !== 'yes')) {
			$this->logger->warning(sprintf('app not enabled, user enabled: %s, enabled: %s', $userEnabled, $enabled), ['app' => Application::APP_ID]);
			http_response_code(403);
			exit;
		}

		try {
			$userFolder = $this->rootFolder->getUserFolder($uid);
			$sharingFolder = $this->appConfig->getValueString(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_SHARING_FOLDER);
			$userSharingFolder = $this->config->getUserValue($uid, Application::APP_ID, Application::SETTINGS_KEY_SHARING_FOLDER);

			$sharingFolder = $userSharingFolder ?: $sharingFolder;
			$isPublic = $sharingFolder && str_starts_with(trim($path, '/') . '/', trim($sharingFolder, '/') . '/');
			// check file is under sharing folder or is shared
			if (! $isPublic && ! $this->isShared($uid, $path)) {
				$this->logger->warning('file not public, sharing folder: ' . $sharingFolder, ['app' => Application::APP_ID]);
				http_response_code(404);
				exit;
			}

			// todo version file handle

			// if user is logged in, need reset filesystem
			$loggedByOther = $this->userSession->isLoggedIn() && $this->userSession->getUser()->getUID() !== $uid;
			if ($loggedByOther) {
				\OC_Util::tearDownFS();
			}

			\OC_Util::setupFS($uid);
			$path = $userFolder->getRelativePath($userFolder->get($path)->getPath());
			$fileSize = Filesystem::filesize($path);

			$rangeArray = [];
			if (isset($_SERVER['HTTP_RANGE']) &&
				str_starts_with($this->request->getHeader('Range'), 'bytes=')) {
				$rangeArray = $this->parseHttpRangeHeader(substr($this->request->getHeader('Range'), 6), $fileSize);
			}

			$this->sendHeaders($path, $rangeArray);

			if ($this->request->getMethod() === 'HEAD') {
				exit;
			}

			$view = Filesystem::getView();
			if ($rangeArray !== []) {
				try {
					if (count($rangeArray) == 1) {
						$view->readfilePart($path, $rangeArray[0]['from'], $rangeArray[0]['to']);
					} else {
						// check if file is seekable (if not throw UnseekableException)
						// we have to check it before body contents
						$view->readfilePart($path, $rangeArray[0]['size'], $rangeArray[0]['size']);

						$type = $this->mimeTypeDetector->getSecureMimeType(Filesystem::getMimeType($path));

						foreach ($rangeArray as $range) {
							echo "\r\n--" . $this->getBoundary() . "\r\n" .
								'Content-type: ' . $type . "\r\n" .
								'Content-range: bytes ' . $range['from'] . '-' . $range['to'] . '/' . $range['size'] . "\r\n\r\n";
							$view->readfilePart($path, $range['from'], $range['to']);
						}

						echo "\r\n--" . $this->getBoundary() . "--\r\n";
					}
				} catch (UnseekableException) {
					// file is unseekable
					header_remove('Accept-Ranges');
					header_remove('Content-Range');
					http_response_code(200);
					$this->sendHeaders($path, []);
					$view->readfile($path);
				}
			} else {
				$view->readfile($path);
			}

			// FIXME: The exit is required here because otherwise the AppFramework is trying to add headers as well
			exit;
		} catch (NotFoundException) {
			http_response_code(404);
			$this->logger->warning(sprintf('not found, user: %s, file: %s', $uid, $path), ['app' => Application::APP_ID]);
			exit;
		} catch (\Exception $e) {
			http_response_code(500);
			$this->logger->error(sprintf('server error, user: %s, file: %s, message: %s', $uid, $path, $e->getMessage()), [
				'app' => Application::APP_ID,
				'extra_context' => $e->getTrace(),
			]);
			exit;
		}
	}

	private function isShared($uid, $path) {
		$segments = explode(DIRECTORY_SEPARATOR, $path);
		$len = count($segments);
		$now = \Carbon\Carbon::now()->timestamp;
		$shared = false;
		for ($i = $len; $i > 0; --$i) {
			$tmpPath = implode(DIRECTORY_SEPARATOR, array_slice($segments, 0, $i));
			$userPath = $this->rootFolder->getUserFolder($uid)->get($tmpPath);
			$shares = $this->shareManager->getSharesBy($uid, IShare::TYPE_LINK, $userPath);
			$share = $shares[0] ?? null;
			// shared but checked hide download or password protect or expired
			if ($share && (
				$share->getHideDownload() ||
				$share->getPassword() || (
					$share->getExpirationDate() &&
					$share->getExpirationDate()->getTimestamp() < $now))) {
				return false;
			}

			// shared but checked hide download or password protect or expired
			if ($share) {
				$shared = true;
			}
		}

		return $shared;
	}

	/**
	 * Copy from OC_Files without setContentDispositionHeader
	 * @param string $filename
	 * @param array $rangeArray ('from'=>int,'to'=>int), ...
	 */
	private function sendHeaders($filename, array $rangeArray): void {
		header('Content-Transfer-Encoding: binary', true);
		header('Pragma: public');// enable caching in IE
		header('Expires: 0');
		header('Access-Control-Allow-Origin: *');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		$fileSize = Filesystem::filesize($filename);
		$type = $this->mimeTypeDetector->getSecureMimeType(Filesystem::getMimeType($filename));
		if ($fileSize > -1) {
			if ($rangeArray !== []) {
				http_response_code(206);
				header('Accept-Ranges: bytes', true);
				if (count($rangeArray) > 1) {
					$type = 'multipart/byteranges; boundary=' . $this->getBoundary();
					// no Content-Length header here
				} else {
					header(sprintf('Content-Range: bytes %d-%d/%d', $rangeArray[0]['from'], $rangeArray[0]['to'], $fileSize), true);
					OC_Response::setContentLengthHeader($rangeArray[0]['to'] - $rangeArray[0]['from'] + 1);
				}
			} else {
				OC_Response::setContentLengthHeader($fileSize);
			}
		}

		header('Content-Type: ' . $type, true);
	}

	/**
	 * Copy from OC_Files
	 */
	private static string $multipartBoundary = '';

	private function getBoundary(): string {
		if (self::$multipartBoundary === '' || self::$multipartBoundary === '0') {
			self::$multipartBoundary = md5(mt_rand());
		}

		return self::$multipartBoundary;
	}

	/**
	 * Copy from OC_Files
	 * @param int $fileSize
	 * @return array $rangeArray ('from'=>int,'to'=>int), ...
	 */
	private function parseHttpRangeHeader(string $rangeHeaderPos, $fileSize): array {
		$rArray = explode(',', $rangeHeaderPos);
		$minOffset = 0;
		$ind = 0;

		$rangeArray = [];

		foreach ($rArray as $value) {
			$ranges = explode('-', $value);
			if (is_numeric($ranges[0])) {
				if ($ranges[0] < $minOffset) { // case: bytes=500-700,601-999
					$ranges[0] = $minOffset;
				}

				if ($ind > 0 && $rangeArray[$ind - 1]['to'] + 1 == $ranges[0]) { // case: bytes=500-600,601-999
					--$ind;
					$ranges[0] = $rangeArray[$ind]['from'];
				}
			}

			if (is_numeric($ranges[0]) && is_numeric($ranges[1]) && $ranges[0] < $fileSize && $ranges[0] <= $ranges[1]) {
				// case: x-x
				if ($ranges[1] >= $fileSize) {
					$ranges[1] = $fileSize - 1;
				}

				$rangeArray[$ind++] = ['from' => $ranges[0], 'to' => $ranges[1], 'size' => $fileSize];
				$minOffset = $ranges[1] + 1;
				if ($minOffset >= $fileSize) {
					break;
				}
			} elseif (is_numeric($ranges[0]) && $ranges[0] < $fileSize) {
				// case: x-
				$rangeArray[$ind++] = ['from' => $ranges[0], 'to' => $fileSize - 1, 'size' => $fileSize];
				break;
			} elseif (is_numeric($ranges[1])) {
				// case: -x
				if ($ranges[1] > $fileSize) {
					$ranges[1] = $fileSize;
				}

				$rangeArray[$ind++] = ['from' => $fileSize - $ranges[1], 'to' => $fileSize - 1, 'size' => $fileSize];
				break;
			}
		}

		return $rangeArray;
	}

}


if (! function_exists('str_starts_with')) {
	function str_starts_with(string $haystack, string $needle): bool {
		return str_starts_with($haystack, $needle);
	}
}
