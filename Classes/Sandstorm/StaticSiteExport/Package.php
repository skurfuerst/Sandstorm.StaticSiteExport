<?php
namespace Sandstorm\StaticSiteExport;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.StaticSiteExport".*
 *                                                                        *
 *                                                                        */

use \TYPO3\Flow\Package\Package as BasePackage;

/**
 * The Sandstorm.StaticSiteExport Package
 */
class Package extends BasePackage {

	/**
	 * Invokes custom PHP code directly after the package manager has been initialized.
	 *
	 * @param \TYPO3\Flow\Core\Bootstrap $bootstrap The current bootstrap
	 * @return void
	 */
	public function boot(\TYPO3\Flow\Core\Bootstrap $bootstrap) {
		$dispatcher = $bootstrap->getSignalSlotDispatcher();
		$dispatcher->connect('TYPO3\TYPO3CR\Domain\Model\Workspace', 'nodeDeletion', 'Sandstorm\StaticSiteExport\Service\SiteExportService', 'onNodeDeletion');
		$dispatcher->connect('TYPO3\TYPO3CR\Domain\Model\Workspace', 'nodePublication', 'Sandstorm\StaticSiteExport\Service\SiteExportService', 'onNodePublication');
		$dispatcher->connect('TYPO3\Flow\Mvc\Dispatcher', 'afterControllerInvocation', 'Sandstorm\StaticSiteExport\Service\SiteExportService', 'publish');
	}

}

?>