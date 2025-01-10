<?php

declare(strict_types=1);

namespace OCA\SharingPath\Tests\Unit\Controller;

use OCA\SharingPath\Controller\PageController;

use OCP\AppFramework\Http\TemplateResponse;

use PHPUnit_Framework_TestCase;

class PageControllerTest extends PHPUnit_Framework_TestCase {
	private ?\OCA\SharingPath\Controller\PageController $controller = null;

	private string $userId = 'john';

	public function setUp(): void {
		$request = $this->getMockBuilder('OCP\IRequest')->getMock();

		$this->controller = new PageController(
			'sharingpath', $request, $this->userId
		);
	}

	public function testIndex(): void {
		$result = $this->controller->index();

		$this->assertEquals('index', $result->getTemplateName());
		$this->assertTrue($result instanceof TemplateResponse);
	}

}
