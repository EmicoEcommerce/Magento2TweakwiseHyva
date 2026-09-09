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
use Tweakwise\Magento2Tweakwise\Model\Analytics\GroupedProductIdResolver;
use Tweakwise\Magento2Tweakwise\Model\Config;
use Tweakwise\Magento2Tweakwise\Model\Visual;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;

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
        private readonly Session $customerSession,
        private readonly Config $config,
        private readonly GroupedProductIdResolver $groupedProductIdResolver
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
     *
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

            $itemHtml = $proceed(
                $itemRendererBlock,
                $product,
                $parentBlock,
                $viewMode,
                $templateType,
                $imageDisplayArea,
                $showDescription
            );

            return $this->addProductIdAttribute($itemHtml, $product);
        }

        $itemId = (string)$product->getId();
        $storeId = (int)$this->storeManager->getStore()->getId();
        $customerGroupId = (int)$this->customerSession->getCustomerGroupId();
        $cardType = sprintf('renderer_%s', urlencode($itemRendererBlock->getNameInLayout()));
        $hashedCacheKeyInfo = $this->cacheHelper->hashCacheKeyInfo(
            $itemId,
            $storeId,
            $customerGroupId,
            $this->cacheHelper->getImage($product),
            $cardType
        );

        if (!$this->cacheHelper->load($hashedCacheKeyInfo)) {
            if ($isVisual) {
                $itemHtml = $this->getVisualHtml($product);
                $this->cacheHelper->save($itemHtml, $hashedCacheKeyInfo);
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
                $itemHtml = $this->addProductIdAttribute($itemHtml, $product);
                $this->cacheHelper->save(
                    $itemHtml,
                    $hashedCacheKeyInfo,
                    [Product::CACHE_TAG, sprintf('%s_%s', Product::CACHE_TAG, $itemId)]
                );
            }
        }

        return sprintf(
            '<esi:include src="/%s?item_id=%s&cache_key_info=%s" />',
            Cache::PRODUCT_CARD_PATH,
            $itemId,
            $hashedCacheKeyInfo
        );
    }

    /**
     * @param Visual $visual
     *
     * @return string
     */
    private function getVisualHtml(Visual $visual): string
    {
        /** @var AbstractBlock $visualRendererBlock */
        $visualRendererBlock = $this->layout->getBlock('tweakwise.catalog.product.list.visual');

        // @phpstan-ignore-next-line
        if (!$visualRendererBlock) {
            return '';
        }

        $visualRendererBlock->setData('visual', $visual);

        return $visualRendererBlock->toHtml();
    }

    private function addProductIdAttribute(string $itemHtml, Product $product): string
    {
        $analyticsProductId = $this->getAnalyticsProductId($product);
        if ($analyticsProductId === '') {
            return $itemHtml;
        }

        $pattern = "/<([a-zA-Z0-9]+)([^>]*\\bclass=([\"'])[^\"']*(?<![\\w-])product-item(?![\\w-])[^\"']*\\3[^>]*)>/";

        return (string)preg_replace_callback(
            $pattern,
            static function (array $matches) use ($analyticsProductId): string {
                $tag = $matches[0];
                if (str_contains($tag, 'data-product-id=')) {
                    $replacedTag = preg_replace(
                        "/\\sdata-product-id=([\"'])[^\"']*\\1/",
                        sprintf(' data-product-id="%s"', $analyticsProductId),
                        $tag,
                        1
                    );

                    if ($replacedTag === null) {
                        return $tag;
                    }

                    return $replacedTag;
                }

                $updatedTag = preg_replace(
                    '/>$/',
                    sprintf(' data-product-id="%s">', $analyticsProductId),
                    $tag,
                    1
                );

                if ($updatedTag === null) {
                    return $tag;
                }

                return $updatedTag;
            },
            $itemHtml,
            1
        );
    }

    private function getAnalyticsProductId(Product $product): string
    {
        $parentId = (string)$product->getId();
        if (!$this->config->isGroupedProductsEnabled()) {
            return $parentId;
        }

        $childId = (string)$product->getData('tw_id');
        if ($childId !== '') {
            return $childId . Helper::GROUP_CODE_DELIMITER . $parentId;
        }

        $resolvedId = (string)$this->groupedProductIdResolver->resolve($product);
        if ($resolvedId === '') {
            return $parentId;
        }

        return $resolvedId;
    }
}
