<?php
namespace Sandstorm\StaticSiteExport\Service;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Sandstorm.StaticSiteExport".*
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Model\Workspace;
use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\Flow\Utility\Files;

/**
 * SiteExport Service
 *
 * @Flow\Scope("singleton")
 */
class SiteExportService {

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Configuration\ConfigurationManager
	 */
	protected $configurationManager;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Mvc\Routing\RouterInterface
	 */
	protected $router;

	/**
	 * @var \SplObjectStorage
	 */
	protected $modifiedFolderNodes;

	/**
	 * @var \SplObjectStorage
	 */
	protected $deletedFolderNodes;

	protected $resourcePublishingTargetDirectory = array();

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Property\PropertyMapper
	 */
	protected $propertyMapper;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\TYPO3CR\Domain\Repository\NodeRepository
	 */
	protected $nodeRepository;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Neos\View\TypoScriptView
	 */
	protected $typoScriptView;

	/**
	 * @var array
	 */
	protected $settings;

	public function __construct() {
		$this->modifiedFolderNodes = new \SplObjectStorage();
		$this->deletedFolderNodes = new \SplObjectStorage();
	}

	public function injectSettings(array $settings) {
		$this->settings = $settings;
	}

	public function initializeObject() {
		$this->initializeRouter();
	}

	/**
	 * Initialize the injected router-object
	 *
	 * @return void
	 */
	protected function initializeRouter() {
		putenv('REDIRECT_FLOW_REWRITEURLS=TRUE');
		$routesConfiguration = $this->configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_ROUTES);
		$this->router->setRoutesConfiguration($routesConfiguration);
	}

	/**
	 *
	 * @param \TYPO3\TYPO3CR\Domain\Model\NodeInterface $node
	 * @param \TYPO3\TYPO3CR\Domain\Model\Workspace $currentWorkspace
	 * @param \TYPO3\TYPO3CR\Domain\Model\Workspace $targetWorkspace
	 */
	public function onNodeDeletion(NodeInterface $node, Workspace $currentWorkspace, Workspace $targetWorkspace) {
		if ($targetWorkspace->getName() !== 'live') {
			return;
		}

		if ($node->getContentType()->isOfType('TYPO3.TYPO3CR:Folder')) {
			$this->deletedFolderNodes->attach($node);
		} else {
			$this->onNodePublication($node, $currentWorkspace, $targetWorkspace);
		}
	}

	/**
	 *
	 * @param \TYPO3\TYPO3CR\Domain\Model\NodeInterface $node
	 * @param \TYPO3\TYPO3CR\Domain\Model\Workspace $currentWorkspace
	 * @param \TYPO3\TYPO3CR\Domain\Model\Workspace $targetWorkspace
	 */
	public function onNodePublication(NodeInterface $node, Workspace $currentWorkspace, Workspace $targetWorkspace) {
		if ($targetWorkspace->getName() !== 'live') {
			return;
		}

		$flowQuery = new FlowQuery(array($node));
		$parentFolderNode = $flowQuery->parents('[instanceof TYPO3.TYPO3CR:Folder]')->get(0);
		$this->modifiedFolderNodes->attach($parentFolderNode);
	}

	/**
	 *
	 */
	public function publish() {
		$this->modifiedFolderNodes->removeAll($this->deletedFolderNodes);

		foreach ($this->modifiedFolderNodes as $modifiedFolderNode) {
			$this->publishFolderNode($modifiedFolderNode);
		}

		$this->modifiedFolderNodes = new \SplObjectStorage();
		$this->deletedFolderNodes = new \SplObjectStorage();

		foreach ($this->resourcePublishingTargetDirectory as $directory) {
			Files::copyDirectoryRecursively(Files::concatenatePaths(array(FLOW_PATH_WEB, '_Resources')), $directory);
		}
	}

	public function publishFolderNode(NodeInterface $node) {
		$settings = $this->getExportSettingsForNode($node);

		$request = new \TYPO3\Flow\Mvc\ActionRequest(\TYPO3\Flow\Http\Request::create(new \TYPO3\Flow\Http\Uri($settings['baseUri'])));
		$controllerContext = new \TYPO3\Flow\Mvc\Controller\ControllerContext(
			$request,
			new \TYPO3\Flow\Http\Response(),
			new \TYPO3\Flow\Mvc\Controller\Arguments(array()),
			new \TYPO3\Flow\Mvc\Routing\UriBuilder($request),
			new \TYPO3\Flow\Mvc\FlashMessageContainer()
		);
		$this->typoScriptView->setControllerContext($controllerContext);

		$this->nodeRepository->getContext()->setCurrentNode($node);
		$this->typoScriptView->assign('value', $node);
		$output = $this->typoScriptView->render();
		$path = substr($node->getPath(), strlen($settings['rootNode'])) . '.html';
		$path = trim($path, '/');
		$exportDirectoryAndFilename = Files::concatenatePaths(array($settings['exportDirectory'], $path));

		Files::createDirectoryRecursively(dirname($exportDirectoryAndFilename));

		$targetResourceDirectory = dirname($exportDirectoryAndFilename) . '/_Resources';
		$this->resourcePublishingTargetDirectory[$targetResourceDirectory] = $targetResourceDirectory;

		file_put_contents($exportDirectoryAndFilename, $output);
	}

	/**
	 *
	 */
	public function publishAll($siteName) {
		$rootNode = $this->propertyMapper->convert('/sites/' . $siteName . '@live', 'TYPO3\TYPO3CR\Domain\Model\NodeInterface');
		$this->addFolderNodesToModifiedList($rootNode);
		$this->publish();
	}

	/**
	 * @param \TYPO3\TYPO3CR\Domain\Model\NodeInterface $node
	 */
	protected function addFolderNodesToModifiedList(NodeInterface $node) {
		foreach ($node->getChildNodes('TYPO3.TYPO3CR:Folder') as $childNode) {
			$this->modifiedFolderNodes->attach($childNode);
			$this->addFolderNodesToModifiedList($childNode);
		}
	}

	protected function getExportSettingsForNode(NodeInterface $node) {
		foreach ($this->settings['sites'] as $configuration) {
			if (strpos($node->getPath(), $configuration['rootNode']) === 0) {
				return $configuration;
			}
		}
	}
}
?>