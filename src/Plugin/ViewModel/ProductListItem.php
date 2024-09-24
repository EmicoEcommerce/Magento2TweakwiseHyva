<?php

declare(strict_types=1);

namespace Tweakwise\TweakwiseHyva\Plugin\ViewModel;

use Hyva\Theme\ViewModel\ProductListItem as Subject;
use Magento\Framework\View\LayoutInterface;
use Magento\Customer\Model\Session;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\Magento2Tweakwise\Helper\Cache;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\AbstractBlock;
use Tweakwise\Magento2Tweakwise\Model\Visual;

class ProductListItem
{
    /**
     * @param Cache $cacheHelper
     * @param LayoutInterface $layout
     * @param StoreManagerInterface $storeManager
     * @param Session $customerSession
     */
    public function __construct(
        private readonly Cache $cacheHelper,
        private readonly LayoutInterface $layout,
        private readonly StoreManagerInterface $storeManager,
        private readonly Session $customerSession
    ) {
    }

    /**
     * @param Subject $subject
     * @param callable $proceed
     * @param AbstractBlock $itemRendererBlock
     * @param Product $product
     * @param AbstractBlock $parentBlock
     * @param string $viewMode
     * @param string $templateType
     * @param string $imageDisplayArea
     * @param bool $showDescription
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function aroundGetItemHtmlWithRenderer(
        Subject $subject,
        callable $proceed,
        AbstractBlock $itemRendererBlock,
        Product $product,
        AbstractBlock $parentBlock,
        string $viewMode,
        string $templateType,
        string $imageDisplayArea,
        bool $showDescription
    ) {
        $isVisual = $product instanceof Visual;
        if (
            !$this->cacheHelper->personalMerchandisingCanBeApplied() ||
            $this->cacheHelper->isTweakwiseAjaxRequest()
        ) {
            if ($isVisual) {
                return $this->getVisualHtml($product);
            }
            return $proceed($itemRendererBlock, $product, $parentBlock, $viewMode, $templateType, $imageDisplayArea, $showDescription);
        }

        $itemId = (string) $product->getId();
        $cardType = sprintf('renderer_%s', urlencode($itemRendererBlock->getNameInLayout()));
        if (!$this->cacheHelper->load($itemId, $cardType)) {
            if ($isVisual) {
                $itemHtml = $this->getVisualHtml($product);
            } else {
                $itemHtml = $proceed(
                    $itemRendererBlock,
                    $product,
                    $parentBlock,
                    $viewMode,
                    $templateType,
                    $imageDisplayArea,
                    $showDescription
                );
            }

            $this->cacheHelper->save($itemHtml, $itemId, $cardType);
        }

        $storeId = $this->storeManager->getStore()->getId();
        $customerGroupId = $this->customerSession->getCustomerGroupId();

        return sprintf(
            '<esi:include src="/%s?item_id=%s&store_id=%s&customer_group_id=%s&card_type=%s" />',
            Cache::PRODUCT_CARD_PATH,
            $itemId,
            $storeId,
            $customerGroupId,
            $cardType
        );
    }

    /**
     * @param Visual $visual
     * @return string
     */
    private function getVisualHtml(Visual $visual): string
    {
        /** @var AbstractBlock $visualRendererBlock */
        $visualRendererBlock = $this->layout->getBlock('tweakwise.catalog.product.list.visual');

        if (!$visualRendererBlock) {
            return '';
        }

        $visualRendererBlock->setData('visual', $visual);

        return $visualRendererBlock->toHtml();
    }
}
