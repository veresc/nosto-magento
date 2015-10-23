<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Nosto
 * @package   Nosto_Tagging
 * @author    Nosto Solutions Ltd <magento@nosto.com>
 * @copyright Copyright (c) 2013-2015 Nosto Solutions Ltd (http://www.nosto.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Data Transfer object representing a product.
 * This is used during the order confirmation API request and the product
 * history export.
 *
 * @category Nosto
 * @package  Nosto_Tagging
 * @author   Nosto Solutions Ltd <magento@nosto.com>
 */
class Nosto_Tagging_Model_Meta_Product extends Nosto_Tagging_Model_Base implements NostoProductInterface
{
    /**
     * Product "can be directly added to cart" tag string.
     */
    const PRODUCT_ADD_TO_CART = 'add-to-cart';

    /**
     * @var string the absolute url to the product page in the shop frontend.
     */
    protected $_url;

    /**
     * @var string the product's unique identifier.
     */
    protected $_productId;

    /**
     * @var string the name of the product.
     */
    protected $_name;

    /**
     * @var string the absolute url the one of the product images in frontend.
     */
    protected $_imageUrl;

    /**
     * @var NostoPrice the product price including possible discounts and taxes.
     */
    protected $_price;

    /**
     * @var NostoPrice the product list price without discounts but incl taxes.
     */
    protected $_listPrice;

    /**
     * @var NostoCurrencyCode the currency code the product is sold in.
     */
    protected $_currency;

    /**
     * @var NostoPriceVariation the price variation currently in use.
     */
    protected $_priceVariation;

    /**
     * @var NostoProductAvailability the availability of the product.
     */
    protected $_availability;

    /**
     * @var array the tags for the product.
     */
    protected $_tags = array(
        'tag1' => array(),
        'tag2' => array(),
        'tag3' => array(),
    );

    /**
     * @var array the categories the product is located in.
     */
    protected $_categories = array();

    /**
     * @var string the product short description.
     */
    protected $_shortDescription;

    /**
     * @var string the product description.
     */
    protected $_description;

    /**
     * @var string the product brand name.
     */
    protected $_brand;

    /**
     * @var NostoDate the product publication date in the shop.
     */
    protected $_datePublished;

    /**
     * @var Nosto_Tagging_Model_Meta_Product_Price_Variation[] the product price variations.
     */
    protected $_priceVariations = array();

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init('nosto_tagging/meta_product');
    }

    /**
     * Loads the Data Transfer object.
     *
     * @param Mage_Catalog_Model_Product $product the product model.
     * @param Mage_Core_Model_Store|null $store the store to get the product data for.
     */
    public function loadData(Mage_Catalog_Model_Product $product, Mage_Core_Model_Store $store = null)
    {

        if (is_null($store)) {
            $store = Mage::app()->getStore();
        }

        /** @var Nosto_Tagging_Helper_Data $helper */
        $helper = Mage::helper('nosto_tagging');
        /** @var Nosto_Tagging_Helper_Price $priceHelper */
        $priceHelper = Mage::helper('nosto_tagging/price');

        $this->setUrl($this->buildUrl($product, $store));
        $this->setProductId($product->getId());
        $this->setName($product->getName());
        $this->setImageUrl($this->buildImageUrl($product, $store));
        $price = $priceHelper->getProductFinalPriceInclTax($product);
        $this->setPrice(new NostoPrice($price));
        $listPrice = $priceHelper->getProductPriceInclTax($product);
        $this->setListPrice(new NostoPrice($listPrice));
        $this->setCurrency(new NostoCurrencyCode($store->getBaseCurrencyCode()));
        $this->setAvailability(new NostoProductAvailability(
            $product->isAvailable()
                ? NostoProductAvailability::IN_STOCK
                : NostoProductAvailability::OUT_OF_STOCK
        ));

        foreach ($this->buildCategories($product) as $categoryString) {
            $this->addCategory($categoryString);
        }

        // Optional properties.

        if ($product->hasData('short_description')) {
            $this->setShortDescription($product->getData('short_description'));
        }
        if ($product->hasData('description')) {
            $this->setDescription($product->getData('description'));
        }
        if ($product->hasData('manufacturer')) {
            $this->setBrand($product->getAttributeText('manufacturer'));
        }
        if (($tags = $this->buildTags($product, $store)) !== array()) {
            $this->setTag1($tags);
        }

        if ($product->hasData('created_at')) {
            if (($timestamp = strtotime($product->getData('created_at')))) {
                $this->setDatePublished(new NostoDate($timestamp));
            }
        }

        if ($helper->getStoreHasMultiCurrency($store)) {
            $this->setPriceVariation(new NostoPriceVariation($store->getBaseCurrencyCode()));
            if ($helper->isMultiCurrencyMethodPriceVariation($store)) {
                foreach ($this->buildPriceVariations($product, $store) as $priceVariation) {
                    $this->addPriceVariation($priceVariation);
                }
            }
        }
    }

    /**
     * Build the product price variations.
     *
     * These are the different prices for the product's supported currencies.
     * Only used when the multi currency method is set to 'priceVariation'.
     *
     * @param Mage_Catalog_Model_Product $product the product model.
     * @param Mage_Core_Model_Store      $store the store model.
     *
     * @return array
     */
    protected function buildPriceVariations(Mage_Catalog_Model_Product $product, Mage_Core_Model_Store $store)
    {
        $variations = array();
        $currencyCodes = $store->getAvailableCurrencyCodes(true);
        foreach ($currencyCodes as $currencyCode) {
            // Skip base currency.
            if ($currencyCode === $store->getBaseCurrencyCode()) {
                continue;
            }
            try {
                /** @var Nosto_Tagging_Model_Meta_Product_Price_Variation $variation */
                $variation = Mage::getModel('nosto_tagging/meta_product_price_variation');
                $variation->loadData($product, $store, new NostoCurrencyCode($currencyCode));
                $variations[] = $variation;
            } catch (Exception $e) {
                // The price variation cannot be obtained if there are no
                // exchange rates defined for the currency and Magento will
                // throw and exception. Just ignore this and continue.
                continue;
            }
        }
        return $variations;
    }

    /**
     * Builds the "tag1" tags.
     *
     * These include any "tag/tag" model names linked to the product, as well
     * as a special "add-to-cart" tag if the product can be added to the
     * cart directly without any choices, i.e. it is a non-configurable simple
     * product.
     * This special tag can then be used in the store frontend to enable a
     * "add to cart" button in the product recommendations.
     *
     * @param Mage_Catalog_Model_Product $product the product model.
     * @param Mage_Core_Model_Store      $store the store model.
     *
     * @return array
     */
    protected function buildTags(Mage_Catalog_Model_Product $product, Mage_Core_Model_Store $store)
    {
        $tags = array();

        if (Mage::helper('core')->isModuleEnabled('Mage_Tag')) {
            $tagCollection = Mage::getModel('tag/tag')
                ->getCollection()
                ->addPopularity()
                ->addStatusFilter(Mage_Tag_Model_Tag::STATUS_APPROVED)
                ->addProductFilter($product->getId())
                ->setFlag('relation', true)
                ->addStoreFilter($store->getId())
                ->setActiveFilter();
            foreach ($tagCollection as $tag) {
                /** @var Mage_Tag_Model_Tag $tag */
                $tags[] = $tag->getName();
            }
        }

        if (!$product->canConfigure()) {
            $tags[] = self::PRODUCT_ADD_TO_CART;
        }

        return $tags;
    }

    /**
     * Builds the absolute store front url for the product page.
     *
     * The url includes the "___store" GET parameter in order for the Nosto
     * crawler to distinguish between stores that do not have separate domains
     * or paths.
     *
     * @param Mage_Catalog_Model_Product $product the product model.
     * @param Mage_Core_Model_Store      $store the store model.
     *
     * @return string
     */
    protected function buildUrl(Mage_Catalog_Model_Product $product, Mage_Core_Model_Store $store)
    {
        // Unset the cached url first, as it won't include the `___store` param
        // if it's cached. We need to define the specific store view in the url
        // in case the same domain is used for all sites.
        $product->unsetData('url');
        return $product
            ->getUrlInStore(
                array(
                    '_nosid' => true,
                    '_ignore_category' => true,
                    '_store' => $store->getCode(),
                )
            );
    }

    /**
     * Builds the product absolute image url for the store and returns it.
     * The image version is primarily taken from the store config, but falls
     * back the the base image if nothing is configured.
     *
     * @param Mage_Catalog_Model_Product $product the product model.
     * @param Mage_Core_Model_Store      $store the store model.
     *
     * @return null|string
     */
    protected function buildImageUrl(Mage_Catalog_Model_Product $product, Mage_Core_Model_Store $store)
    {
        $url = null;
        /** @var Nosto_Tagging_Helper_Data $helper */
        $helper = Mage::helper('nosto_tagging');
        $imageVersion = $helper->getProductImageVersion($store);
        $img = $product->getData($imageVersion);
        $img = $this->isValidImage($img) ? $img : $product->getData('image');
        if ($this->isValidImage($img)) {
            // We build the image url manually in order get the correct base
            // url, even if this product is populated in the backend.
            $baseUrl = rtrim($store->getBaseUrl('media'), '/');
            $file = str_replace(DS, '/', $img);
            $file = ltrim($file, '/');
            $url = $baseUrl.'/catalog/product/'.$file;
        }
        return $url;
    }

    /**
     * Adds a new price variation to the product
     *
     * @param Nosto_Tagging_Model_Meta_Product_Price_Variation $priceVariation
     * @param Mage_Core_Model_Store      $store the store model.
     *
     * @return void
     */
    protected function addPriceVariation(Nosto_Tagging_Model_Meta_Product_Price_Variation $priceVariation)
    {
        $this->_priceVariations[] = $priceVariation;
    }

    /**
     * Return array of categories for the product.
     * The items in the array are strings combined of the complete category
     * path to the products own category.
     *
     * Structure:
     * array (
     *     /Electronics/Computers
     * )
     *
     * @param Mage_Catalog_Model_Product $product the product model.
     *
     * @return array
     */
    protected function buildCategories(Mage_Catalog_Model_Product $product)
    {
        $data = array();

        /** @var Nosto_Tagging_Helper_Data $helper */
        $helper = Mage::helper('nosto_tagging');
        $categoryCollection = $product->getCategoryCollection();
        foreach ($categoryCollection as $category) {
            $categoryString = $helper->buildCategoryString($category);
            if (!empty($categoryString)) {
                $data[] = $categoryString;
            }
        }

        return $data;
    }

    /**
     * Checks if the given image file path is valid.
     *
     * @param string $image the image file path.
     *
     * @return bool
     */
    protected function isValidImage($image)
    {
        return (!empty($image) && $image !== 'no_selection');
    }

    /**
     * Returns the absolute url to the product page in the shop frontend.
     *
     * @return string the url.
     */
    public function getUrl()
    {
        return $this->_url;
    }

    /**
     * Returns the product's unique identifier.
     *
     * @return int|string the ID.
     */
    public function getProductId()
    {
        return $this->_productId;
    }

    /**
     * Setter for the product's unique identifier.
     *
     * @param int|string $productId the ID.
     */
    public function setProductId($productId)
    {
        $this->_productId = $productId;
    }

    /**
     * Returns the name of the product.
     *
     * @return string the name.
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Returns the absolute url the one of the product images in the frontend.
     *
     * @return string the url.
     */
    public function getImageUrl()
    {
        return $this->_imageUrl;
    }

    /**
     * Returns the absolute url to one of the product image thumbnails in the shop frontend.
     *
     * @return string the url.
     */
    public function getThumbUrl()
    {
        return null;
    }

    /**
     * Returns the price of the product including possible discounts and taxes.
     *
     * @return NostoPrice the price.
     */
    public function getPrice()
    {
        return $this->_price;
    }

    /**
     * Returns the list price of the product without discounts but incl taxes.
     *
     * @return NostoPrice the price.
     */
    public function getListPrice()
    {
        return $this->_listPrice;
    }

    /**
     * Returns the currency code (ISO 4217) the product is sold in.
     *
     * @return NostoCurrencyCode the currency ISO code.
     */
    public function getCurrency()
    {
        return $this->_currency;
    }

    /**
     * Returns the ID of the price variation that is currently in use.
     *
     * @return string the price variation ID.
     */
    public function getPriceVariationId()
    {
        return !is_null($this->_priceVariation)
            ? $this->_priceVariation->getId()
            : null;
    }

    /**
     * Returns the availability of the product, i.e. if it is in stock or not.
     *
     * @return NostoProductAvailability the availability
     */
    public function getAvailability()
    {
        return $this->_availability;
    }

    /**
     * Returns the tags for the product.
     *
     * @return array the tags array, e.g. array('tag1' => array("winter", "shoe")).
     */
    public function getTags()
    {
        return $this->_tags;
    }

    /**
     * Returns the categories the product is located in.
     *
     * @return array list of category strings, e.g. array("/shoes/winter").
     */
    public function getCategories()
    {
        return $this->_categories;
    }

    /**
     * Returns the product short description.
     *
     * @return string the short description.
     */
    public function getShortDescription()
    {
        return $this->_shortDescription;
    }

    /**
     * Returns the product description.
     *
     * @return string the description.
     */
    public function getDescription()
    {
        return $this->_description;
    }

    /**
     * Returns the product brand name.
     *
     * @return string the brand name.
     */
    public function getBrand()
    {
        return $this->_brand;
    }

    /**
     * Returns the product publication date in the shop.
     *
     * @return NostoDate the date.
     */
    public function getDatePublished()
    {
        return $this->_datePublished;
    }

    /**
     * Returns the product price variations if any exist.
     *
     * @return NostoProductPriceVariationInterface[] the price variations.
     */
    public function getPriceVariations()
    {
        return $this->_priceVariations;
    }

    /**
     * Returns the full product description,
     * i.e. both the "short" and "normal" descriptions concatenated.
     *
     * @return string the full descriptions.
     */
    public function getFullDescription()
    {
        $descriptions = array();
        if (!empty($this->_shortDescription)) {
            $descriptions[] = $this->_shortDescription;
        }
        if (!empty($this->_description)) {
            $descriptions[] = $this->_description;
        }
        return implode(' ', $descriptions);
    }

    /**
     * Setter for availability
     * @param NostoProductAvailability $availability
     * @return void
     */
    public function setAvailability(NostoProductAvailability $availability)
    {
        $this->_availability = $availability;
    }

    /**
     * Setter for brand
     * @param string $brand
     * @return void
     */
    public function setBrand($brand)
    {
        $this->_brand = $brand;
    }

    /**
     * Adder for category
     * @param string $category
     * @return void
     */
    public function addCategory($category)
    {
        $this->_categories[] = $category;
    }

    /**
     * Setter for currency
     * @param NostoCurrencyCode $currency
     * @return void
     */
    public function setCurrency(NostoCurrencyCode $currency)
    {
        $this->_currency = $currency;
    }

    /**
     * Setter for datePublished
     * @param NostoDate $datePublished
     * @return void
     */
    public function setDatePublished(NostoDate $datePublished)
    {
        $this->_datePublished = $datePublished;
    }

    /**
     * Setter for description
     * @param string $description
     * @return void
     */
    public function setDescription($description)
    {
        $this->_description = $description;
    }

    /**
     * Setter for image url
     * @param string $imageUrl
     * @return void
     */
    public function setImageUrl($imageUrl)
    {
        $this->_imageUrl = $imageUrl;
    }

    /**
     * Setter for listPrice
     * @param NostoPrice $listPrice
     * @return void
     */
    public function setListPrice(NostoPrice $listPrice)
    {
        $this->_listPrice = $listPrice;
    }

    /**
     * Setter for name
     * @param string $name
     * @return void
     */
    public function setName($name)
    {
        $this->_name = $name;
    }

    /**
     * Setter for price
     * @param NostoPrice $price
     * @return void
     */
    public function setPrice(NostoPrice $price)
    {
        $this->_price = $price;
    }

    /**
     * Setter for priceVariation
     * @param NostoPriceVariation $priceVariation
     * @return void
     */
    public function setPriceVariation(NostoPriceVariation $priceVariation)
    {
        $this->_priceVariation = $priceVariation;
    }

    /**
     * Setter for shortDescription
     * @param string $shortDescription
     * @return void
     */
    public function setShortDescription($shortDescription)
    {
        $this->_shortDescription = $shortDescription;
    }

    /**
     * Setter for url
     * @param string $url
     * @return void
     */
    public function setUrl($url)
    {
        $this->_url = $url;
    }

    /**
     * Sets all the tags to the `tag1` field.
     *
     * The tags must be an array of non-empty string values.
     *
     * Usage:
     * $object->setTag1(array('customTag1', 'customTag2'));
     *
     * @param array $tags the tags.
     *
     * @throws InvalidArgumentException
     */
    public function setTag1(array $tags)
    {
        $this->_tags['tag1'] = array();
        foreach ($tags as $tag) {
            $this->addTag1($tag);
        }
    }
    /**
     * Adds a new tag to the `tag1` field.
     *
     * The tag must be a non-empty string value.
     *
     * Usage:
     * $object->addTag1('customTag');
     *
     * @param string $tag the tag to add.
     *
     * @throws InvalidArgumentException
     */
    public function addTag1($tag)
    {
        if (!is_string($tag) || empty($tag)) {
            throw new NostoInvalidArgumentException('Tag must be a non-empty string value.');
        }
        $this->_tags['tag1'][] = $tag;
    }
    /**
     * Sets all the tags to the `tag2` field.
     *
     * The tags must be an array of non-empty string values.
     *
     * Usage:
     * $object->setTag2(array('customTag1', 'customTag2'));
     *
     * @param array $tags the tags.
     *
     * @throws InvalidArgumentException
     */
    public function setTag2(array $tags)
    {
        $this->_tags['tag2'] = array();
        foreach ($tags as $tag) {
            $this->addTag2($tag);
        }
    }
    /**
     * Adds a new tag to the `tag2` field.
     *
     * The tag must be a non-empty string value.
     *
     * Usage:
     * $object->addTag2('customTag');
     *
     * @param string $tag the tag to add.
     *
     * @throws InvalidArgumentException
     */
    public function addTag2($tag)
    {
        if (!is_string($tag) || empty($tag)) {
            throw new InvalidArgumentException('Tag must be a non-empty string value.');
        }
        $this->_tags['tag2'][] = $tag;
    }
    /**
     * Sets all the tags to the `tag3` field.
     *
     * The tags must be an array of non-empty string values.
     *
     * Usage:
     * $object->setTag3(array('customTag1', 'customTag2'));
     *
     * @param array $tags the tags.
     *
     * @throws InvalidArgumentException
     */
    public function setTag3(array $tags)
    {
        $this->_tags['tag3'] = array();
        foreach ($tags as $tag) {
            $this->addTag3($tag);
        }
    }
    /**
     * Adds a new tag to the `tag3` field.
     *
     * The tag must be a non-empty string value.
     *
     * Usage:
     * $object->addTag3('customTag');
     *
     * @param string $tag the tag to add.
     *
     * @throws InvalidArgumentException
     */
    public function addTag3($tag)
    {
        if (!is_string($tag) || empty($tag)) {
            throw new InvalidArgumentException('Tag must be a non-empty string value.');
        }
        $this->_tags['tag3'][] = $tag;
    }

}
