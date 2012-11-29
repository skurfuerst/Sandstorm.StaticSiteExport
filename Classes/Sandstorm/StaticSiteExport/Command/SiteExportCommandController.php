<?php
namespace Sandstorm\StaticSiteExport\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.StaticSiteExport".*
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * SiteExport command controller for the Sandstorm.StaticSiteExport package
 *
 * @Flow\Scope("singleton")
 */
class SiteExportCommandController extends \TYPO3\Flow\Cli\CommandController {

	/**
	 * @Flow\Inject
	 * @var \Sandstorm\StaticSiteExport\Service\SiteExportService
	 */
	protected $siteExportService;

	/**
	 * Export the whole site
	 *
	 * @param string $siteName The site name
	 * @return void
	 */
	public function exportAllCommand($siteName) {
		$this->outputLine('Starting export of <b>%s</b>.', array($siteName));
		$this->siteExportService->publishAll($siteName);
	}
}
?>