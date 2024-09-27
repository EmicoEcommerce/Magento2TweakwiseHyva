<?php

declare(strict_types=1);

namespace Tweakwise\TweakwiseHyva\ViewModel;

use Hyva\Theme\ViewModel\BlockCache;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Swatches\Helper\Data as SwatchHelper;

use function array_map as map;
use function array_keys as keys;

class SwatchRenderer implements ArgumentInterface
{
    /**
     * @var SwatchHelper
     */
    private $swatchHelper;

    /**
     * @var HttpRequest
     */
    private $httpRequest;

    /**
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * @var BlockCache
     */
    private $blockCache;

    /**
     * @param SwatchHelper $swatchHelper
     */
    public function __construct(
        SwatchHelper $swatchHelper,
        HttpRequest $httpRequest,
        EavConfig $eavConfig,
        BlockCache $blockCache
    ) {
        $this->swatchHelper = $swatchHelper;
        $this->httpRequest  = $httpRequest;
        $this->eavConfig    = $eavConfig;
        $this->blockCache   = $blockCache;
    }

    /**
     * @param Attribute $attribute
     */
    public function isSwatchAttribute($attribute): bool
    {
        return $this->swatchHelper->isSwatchAttribute($attribute);
    }

    public function beforeListItemToHtml(AbstractBlock $itemRendererBlock, Product $product): void
    {
        if ($product->getTypeId() !== Configurable::TYPE_CODE) {
            return;
        }

        $filterAttributes = $this->getUsedSwatchFilters($product);
        ksort($filterAttributes);
        $swatchCacheKey = implode(
            '',
            map(
                function (string $code) use ($filterAttributes): string {
                    if (is_array($filterAttributes[$code])) {
                        $codes = '';
                        foreach ($filterAttributes[$code] as $filterAttributesValues) {
                            $codes .= $filterAttributesValues;
                        }

                        return "$code={$codes}";
                    }

                    return "$code={$filterAttributes[$code]}";
                },
                keys($filterAttributes)
            )
        );

        if ($swatchCacheKey) {
            $newKey = $this->blockCache->hashCacheKeyInfo([$itemRendererBlock->getData('cache_key'), $swatchCacheKey]);
            $itemRendererBlock->setData('cache_key', $newKey);
        }
    }

    /**
     * @see \Magento\Swatches\Model\Plugin\ProductImage::getFilterArray
     */
    private function getUsedSwatchFilters(Product $product): array
    {
        $requestParams = $this->httpRequest->getParams();
        $allAttributes = $this->eavConfig->getEntityAttributes(Product::ENTITY, $product);
        $usedFilterAttributes = [];
        foreach ($requestParams as $code => $value) {
            if (isset($allAttributes[$code])) {
                $attribute = $allAttributes[$code];
                if ($this->canReplaceImageWithSwatch($attribute)) {
                    $usedFilterAttributes[$code] = $value;
                }
            }
        }

        return $usedFilterAttributes;
    }

    /**
     * Check if we can replace original image with swatch image on catalog/category/list page
     *
     * @see \Magento\Swatches\Model\Plugin\ProductImage::canReplaceImageWithSwatch
     */
    private function canReplaceImageWithSwatch($attribute)
    {
        $result = true;
        if (!$this->isSwatchAttribute($attribute)) {
            $result = false;
        }

        if (
            !$attribute->getUsedInProductListing()
            || !$attribute->getIsFilterable()
            || !$attribute->getData('update_product_preview_image')
        ) {
            $result = false;
        }

        return $result;
    }
}
