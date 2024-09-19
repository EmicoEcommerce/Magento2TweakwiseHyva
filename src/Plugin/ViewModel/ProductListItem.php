<?php

declare(strict_types=1);

namespace Tweakwise\TweakwiseHyva\Plugin\ViewModel;

use Hyva\Theme\ViewModel\ProductListItem as Subject;
use Magento\Customer\Model\Session;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\Magento2Tweakwise\Helper\Cache;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\AbstractBlock;

class ProductListItem
{
    /**
     * @param Cache $cacheHelper
     */
    public function __construct(
        private readonly Cache $cacheHelper,
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
        if (
            !$this->cacheHelper->personalMerchandisingCanBeApplied() ||
            $this->cacheHelper->isTweakwiseAjaxRequest()
        ) {
            return $proceed($itemRendererBlock, $product, $parentBlock, $viewMode, $templateType, $imageDisplayArea, $showDescription);
        }

        $productId = (int) $product->getId();
        $cardType = sprintf('renderer_%s', urlencode($itemRendererBlock->getNameInLayout()));
        if (!$this->cacheHelper->load($productId, $cardType)) {
            $itemHtml = $proceed($itemRendererBlock, $product, $parentBlock, $viewMode, $templateType, $imageDisplayArea, $showDescription);
            $this->cacheHelper->save($itemHtml, $productId, $cardType);
        }

        $storeId = $this->storeManager->getStore()->getId();
        $customerGroupId = $this->customerSession->getCustomerGroupId();

        return sprintf(
            '<esi:include src="/%s?product_id=%s&store_id=%s&customer_group_id=%s&card_type=%s" />',
            Cache::PRODUCT_CARD_PATH,
            $productId,
            $storeId,
            $customerGroupId,
            $cardType
        );
    }
}
