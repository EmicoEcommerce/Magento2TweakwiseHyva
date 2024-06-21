<?php

declare(strict_types=1);

namespace Tweakwise\TweakwiseHyva\Plugin\ViewModel;

use Hyva\Theme\ViewModel\ProductListItem as Subject;
use Tweakwise\Magento2Tweakwise\Model\Config as TweakwiseConfig;
use Tweakwise\Magento2Tweakwise\Helper\Cache;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\AbstractBlock;

class ProductListItem
{
    /**
     * @param Cache $cacheHelper
     * @param TweakwiseConfig $config
     */
    public function __construct(
        private readonly Cache $cacheHelper,
        private readonly TweakwiseConfig $config,
    ) {
    }

    /**
     * @param Subject $subject
     * @param callable $proceed
     * @param Product $product
     * @param AbstractBlock $parentBlock
     * @param string $viewMode
     * @param string $templateType
     * @param string $imageDisplayArea
     * @param bool $showDescription
     * @return string
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function aroundGetItemHtml(
        Subject $subject,
        callable $proceed,
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
            return $proceed($product, $parentBlock, $viewMode, $templateType, $imageDisplayArea, $showDescription);
        }

        $productId = (int) $product->getId();
        if (!$this->cacheHelper->load($productId)) {
            $itemHtml = $proceed($product, $parentBlock, $viewMode, $templateType, $imageDisplayArea, $showDescription);
            $this->cacheHelper->save($itemHtml, $productId);
        }

        return sprintf('<esi:include src="/%s?product_id=%s" />', Cache::PRODUCT_CARD_PATH, $productId);
    }
}
