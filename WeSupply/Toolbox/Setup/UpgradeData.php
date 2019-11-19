<?php

namespace WeSupply\Toolbox\Setup;

use Magento\Catalog\Model\Product;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\PageFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Store\Model\ScopeInterface;
use WeSupply\Toolbox\Logger\Logger as Logger;

use Magento\Catalog\Setup\CategorySetupFactory;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * WeSupply tracking page url
     */
    const WESUPPLY_TRACKING_ID = 'wesupply-tracking-info';

    /**
     * WeSupply store locator page url
     */
    const WESUPPLY_STORE_LOCATOR_ID = 'wesupply-store-locator';

    /**
     * WeSupply store-details page url
     */
    const WESUPPLY_STORE_DETAILS_ID = 'wesupply-store-details';

    /**
     * @var State
     */
    private $state;

    /**
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * @var PageRepositoryInterface
     */
    private $pageRepository;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var CategorySetupFactory
     */
    private $catalogSetupFactory;

    /**
     * UpgradeData constructor.
     * @param WriterInterface $configWriter
     * @param ScopeConfigInterface $scopeConfig
     * @param PageFactory $pageFactory
     * @param PageRepositoryInterface $pageRepository
     * @param Logger $logger
     * @param State $state
     * @param CategorySetupFactory $categorySetupFactory
     * @throws LocalizedException
     */
    public function __construct(
        WriterInterface $configWriter,
        ScopeConfigInterface $scopeConfig,
        PageFactory $pageFactory,
        PageRepositoryInterface $pageRepository,
        Logger $logger,
        State $state,
        CategorySetupFactory $categorySetupFactory
    ) {
        $this->configWriter = $configWriter;
        $this->scopeConfig = $scopeConfig;
        $this->state = $state;
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);

        $this->pageFactory = $pageFactory;
        $this->pageRepository = $pageRepository;
        $this->logger = $logger;

        $this->catalogSetupFactory = $categorySetupFactory;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @throws \Exception
     */
    public function upgrade(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.3') < 0) {
            $cmsPages = [
                [
                    'title' => 'Tracking Info',
                    'identifier' => $this->getTrackingPageIdentifier()
                ],
                [
                    'title' => 'Store Locator',
                    'identifier' => $this->getStoreLocatorPageIdentifier()
                ],
                [
                    'title' => 'Store Details',
                    'identifier' => $this->getStoreDetailsPageIdentifier()
                ]
            ];

            foreach ($cmsPages as $pageData) {
                $page = $this->pageFactory->create()
                    ->setTitle($pageData['title'])
                    ->setIdentifier($pageData['identifier'])
                    ->setIsActive(true)
                    ->setPageLayout('1column')
                    ->setStores([0])
                    ->setContent($this->createIframeContainer());

                try {
                    $page->save();
                } catch (\Exception $e) {
                    $message = __('WeSupply_Toolbox is trying to create a cms page with URL key "%1" but this identifier already exists!', $pageData['identifier']);
                    $this->logger->addNotice($message . ' ' . $e->getMessage());
                }
            }

            /**
             * since 1.0.3 wesupply_subdomaine was moved from step_1 to step_2
             * so we have to copy the old saved value into the new config path if exists
             */
            if ($wesupplySubdomain = $this->scopeConfig->getValue('wesupply_api/step_1/wesupply_subdomain', ScopeInterface::SCOPE_STORE)) {
                $this->configWriter->save('wesupply_api/step_2/wesupply_subdomain', $wesupplySubdomain, $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0);
            }
        }

        if (version_compare($context->getVersion(), '1.0.4') < 0) {
            /**
             * delete 'wesupply-tracking-info' cms page as we do not use it anymore
             */
            $existingPage = $this->pageFactory->create()->load($this->getTrackingPageIdentifier());
            if ($existingPage->getId()) {
                try {
                    $this->pageRepository->deleteById($existingPage->getId());
                } catch (NoSuchEntityException $e) {
                    $message = __('WeSupply_Toolbox is trying to delete an existing cms page with URL key "%1" but an unknown error occurred! Please delete it manually if exists.', $this->getTrackingPageIdentifier());
                    $this->logger->addNotice($message . ' ' . $e->getMessage());
                }
            }
        }

        if (version_compare($context->getVersion(), '1.0.5') < 0) {
            $attributeName = 'wesupply_estimation_display';
            /** @var \Magento\Catalog\Setup\CategorySetup $categorySetup */
            $catalogSetup = $this->catalogSetupFactory->create(['setup' => $setup]);

            $catalogSetup->addAttribute(Product::ENTITY, $attributeName, [
                'type' => 'int',
                'label' => 'Display WeSupply Delivery Estimation',
                'input' => 'select',
                'required' => false,
                'sort_order' => 10,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                'wysiwyg_enabled' => false,
                'is_html_allowed_on_front' => false,
                'group' => 'WeSupply Options',
                'default' => 1,
                'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
                'note' => 'WeSupply Delivery Estimation will not be displayed if the WeSupply Toolbox module is disabled.'
            ]);
        }

        $setup->endSetup();
    }

    /**
     * @return string
     */
    private function getTrackingPageIdentifier()
    {
        return self::WESUPPLY_TRACKING_ID;
    }

    /**
     * @return string
     */
    private function getStoreLocatorPageIdentifier()
    {
        return self::WESUPPLY_STORE_LOCATOR_ID;
    }

    /**
     * @return string
     */
    private function getStoreDetailsPageIdentifier()
    {
        return self::WESUPPLY_STORE_DETAILS_ID;
    }

    /**
     * @return string
     */
    private function createIframeContainer()
    {
        $container  = '<!-- Do not delete or edit this container -->' . "\n";
        $container .= '<div class="embedded-iframe-container"></div>';

        return $container;
    }
}
