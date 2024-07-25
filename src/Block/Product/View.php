<?php

declare(strict_types=1);

namespace Tweakwise\TweakwiseHyva\Block\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductTypes\ConfigInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Json\EncoderInterface as JsonEncoderInterface;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Framework\Url\EncoderInterface;
use Magento\Catalog\Block\Product\View as MagentoView;
use Tweakwise\Magento2Tweakwise\Helper\Cache;

class View extends MagentoView
{
    private const NO_CACHE_BLOCKS = [
        'related',
        'upsell',
        'crosssell'
    ];

    /**
     * @param Context $context
     * @param EncoderInterface $urlEncoder
     * @param JsonEncoderInterface $jsonEncoder
     * @param StringUtils $string
     * @param ProductHelper $productHelper
     * @param ConfigInterface $productTypeConfig
     * @param FormatInterface $localeFormat
     * @param Session $customerSession
     * @param ProductRepositoryInterface $productRepository
     * @param PriceCurrencyInterface $priceCurrency
     * @param Cache $cacheHelper
     * @param array $data
     */
    public function __construct(
        private readonly Context $context,
        EncoderInterface           $urlEncoder,
        JsonEncoderInterface       $jsonEncoder,
        StringUtils                $string,
        ProductHelper                    $productHelper,
        ConfigInterface            $productTypeConfig,
        FormatInterface            $localeFormat,
        Session                    $customerSession,
        ProductRepositoryInterface $productRepository,
        PriceCurrencyInterface $priceCurrency,
        private readonly Cache $cacheHelper,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $urlEncoder,
            $jsonEncoder,
            $string,
            $productHelper,
            $productTypeConfig,
            $localeFormat,
            $customerSession,
            $productRepository,
            $priceCurrency,
            $data
        );
    }

    /**
     * @return int|bool|null
     */
    protected function getCacheLifetime()
    {
        if (
            !$this->cacheHelper->personalMerchandisingCanBeApplied() ||
            !in_array($this->getNameInLayout(), self::NO_CACHE_BLOCKS, true)
        ) {
            return parent::getCacheLifetime();
        }

        $this->setData('ttl', Cache::PRODUCT_LIST_TTL);
        $this->setData('cache_lifetime', Cache::PRODUCT_LIST_TTL);
        return $this->getData('cache_lifetime');
    }

    /**
     * @return ProductInterface|Product
     * @throws NoSuchEntityException
     */
    public function getProduct()
    {
        $product = parent::getProduct();

        if ($product) {
            return $product;
        }

        $request = $this->context->getRequest();
        $productId = $request->getParam('product_id');

        if(
            !$productId ||
            !$this->cacheHelper->isEsiRequest($request) ||
            !$this->cacheHelper->personalMerchandisingCanBeApplied()
        ) {
            return $product;
        }

        $product = $this->productRepository->getById($productId);
        $this->_coreRegistry->register('product', $product);

        return $product;
    }

    /**
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getUrl($route = '', $params = [])
    {
        $request = $this->context->getRequest();
        if (
            !$this->cacheHelper->personalMerchandisingCanBeApplied() ||
            $this->cacheHelper->isEsiRequest($request)
        ) {
            return parent::getUrl($route, $params);
        }

        $queryParams = [];
        $productId = $request->getParam('id');
        if ($productId) {
            $queryParams['product_id'] = $productId;
        }

        $params['_query'] = $queryParams;

        return parent::getUrl($route, $params);
    }
}
