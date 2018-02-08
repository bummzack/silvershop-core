<?php

namespace SilverShop\Page;


use Page;
use SilverShop\Cart\ShoppingCart;
use SilverShop\Cart\ShoppingCartController;
use SilverShop\Extension\ProductVariationsExtension;
use SilverShop\Model\Buyable;
use SilverShop\Model\Order;
use SilverShop\Model\Product\OrderItem;
use SilverShop\Model\Variation\Variation;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Director;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBCurrency;
use SilverStripe\ORM\FieldType\DBDecimal;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;


/**
 * This is a standard Product page-type with fields like
 * Price, Weight, Model and basic management of
 * groups.
 *
 * It also has an associated Product_OrderItem class,
 * an extension of OrderItem, which is the mechanism
 * that links this page type class to the rest of the
 * eCommerce platform. This means you can add an instance
 * of this page type to the shopping cart.
 *
 * @mixin ProductVariationsExtension
 *
 * @property string $InternalItemID
 * @property string $Model
 * @property DBCurrency $CostPrice
 * @property DBCurrency $BasePrice
 * @property DBDecimal $Weight
 * @property DBDecimal $Height
 * @property DBDecimal $Width
 * @property DBDecimal $Depth
 * @property bool $Featured
 * @property bool $AllowPurchase
 * @property float $Popularity
 * @property int $ImageID
 *
 * @method ProductCategory[]|ManyManyList ProductCategories()
 */
class Product extends Page implements Buyable
{
    private static $db = [
        'InternalItemID' => 'Varchar(30)', //ie SKU, ProductID etc (internal / existing recognition of product)
        'Model' => 'Varchar(30)',

        'CostPrice' => 'Currency(19,4)', // Wholesale cost of the product to the merchant
        'BasePrice' => 'Currency(19,4)', // Base retail price the item is marked at.

        //physical properties
        // TODO: Move these to an extension (used in Variations as well)
        'Weight' => 'Decimal(12,5)',
        'Height' => 'Decimal(12,5)',
        'Width' => 'Decimal(12,5)',
        'Depth' => 'Decimal(12,5)',

        'Featured' => 'Boolean',
        'AllowPurchase' => 'Boolean',

        'Popularity' => 'Float' //storage for CalculateProductPopularity task
    ];

    private static $has_one = [
        'Image' => Image::class,
    ];

    private static $many_many = [
        'ProductCategories' => ProductCategory::class,
    ];

    private static $defaults = [
        'AllowPurchase' => true,
        'ShowInMenus' => false,
    ];

    private static $casting = [
        'Price' => 'Currency',
    ];

    private static $summary_fields = [
        'InternalItemID',
        'Title',
        'BasePrice.NiceOrEmpty',
        'canPurchase',
    ];

    private static $searchable_fields = [
        'InternalItemID',
        'Title' => ['title' => 'Title'],
        'Featured',
    ];

    private static $field_labels = [
        'InternalItemID' => 'SKU',
        'Title' => 'Title',
        'BasePrice' => 'Price',
        'BasePrice.NiceOrEmpty' => 'Price',
        'canPurchase' => 'Purchasable',
    ];

    private static $table_name = 'SilverShop_Product';

    private static $singular_name = 'Product';

    private static $plural_name = 'Products';

    private static $icon = 'silvershop/core: images/icons/package.gif';

    private static $default_parent = ProductCategory::class;

    private static $default_sort = '"Title" ASC';

    private static $global_allow_purchase = true;

    private static $allow_zero_price = false;

    private static $order_item = OrderItem::class;

    private static $min_opengraph_img_size = 0;

    // Physical Measurement
    private static $weight_unit = 'kg';

    private static $length_unit = 'cm';

    private static $indexes = [
        'Featured' => true,
        'AllowPurchase' => true,
        'InternalItemID' => true,
    ];

    /**
     * Add product fields to CMS
     *
     * @return FieldList updated field list
     */
    public function getCMSFields()
    {
        $self = $this;

        $this->beforeUpdateCMSFields(
            function (FieldList $fields) use ($self) {
                $fields->fieldByName('Root.Main.Title')
                    ->setTitle(_t(__CLASS__ . '.PageTitle', 'Product Title'));

                $fields->addFieldsToTab(
                    'Root.Main', [
                    TextField::create('InternalItemID', _t(__CLASS__ . '.InternalItemID', 'Product Code/SKU'), '', 30),
                    DropdownField::create('ParentID', _t(__CLASS__ . '.Category', 'Category'), $self->getCategoryOptions())
                    ->setDescription(_t(__CLASS__ . '.CategoryDescription', 'This is the parent page or default category.')),
                    TextField::create('Model', _t(__CLASS__ . '.Model', 'Model'), '', 30),
                    CheckboxField::create('Featured', _t(__CLASS__ . '.Featured', 'Featured Product')),
                    CheckboxField::create('AllowPurchase', _t(__CLASS__ . '.AllowPurchase', 'Allow product to be purchased'), 1),
                    ]
                );

                $fields->addFieldsToTab(
                    'Root.Pricing', [
                    TextField::create('BasePrice', $this->fieldLabel('BasePrice'))
                    ->setDescription(_t(__CLASS__ . '.PriceDesc', 'Base price to sell this product at.'))
                    ->setMaxLength(12),
                    TextField::create('CostPrice', $this->fieldLabel('CostPrice'))
                    ->setDescription(_t(__CLASS__ . '.CostPriceDescription', 'Wholesale price before markup.'))
                    ->setMaxLength(12),
                    ]
                );

                $fieldSubstitutes = [
                'LengthUnit' => $self::config()->length_unit
                ];

                $fields->addFieldsToTab(
                    'Root.Shipping', [
                    TextField::create(
                        'Weight',
                        _t(
                            __CLASS__ . '.WeightWithUnit', 'Weight ({WeightUnit})', '', [
                            'WeightUnit' => self::config()->weight_unit
                            ]
                        ),
                        '',
                        12
                    ),
                    TextField::create(
                        'Height',
                        _t(__CLASS__ . '.HeightWithUnit', 'Height ({LengthUnit})', '', $fieldSubstitutes),
                        '',
                        12
                    ),
                    TextField::create(
                        'Width',
                        _t(__CLASS__ . '.WidthWithUnit', 'Width ({LengthUnit})', '', $fieldSubstitutes),
                        '',
                        12
                    ),
                    TextField::create(
                        'Depth',
                        _t(__CLASS__ . '.DepthWithUnit', 'Depth ({LengthUnit})', '', $fieldSubstitutes),
                        '',
                        12
                    ),
                    ]
                );

                if (!$fields->dataFieldByName('Image')) {
                    $fields->addFieldToTab(
                        'Root.Images',
                        UploadField::create('Image', _t(__CLASS__ . '.Image', 'Product Image'))
                    );
                }
            }
        );

        return parent::getCMSFields();
    }

    /**
     * Fix grid field heading displaying "page name"
     */
    public function fieldLabels($includerelations = true)
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Title'] = 'Title';
        return $labels;
    }

    /**
     * Helper function for generating list of categories to select from.
     *
     * @return array categories
     */
    private function getCategoryOptions()
    {
        $categories = ProductCategory::get()->map('ID', 'NestedTitle')->toArray();
        $categories = [
            0 => _t('SiteTree.PARENTTYPE_ROOT', 'Top-level page'),
        ] + $categories;
        if ($this->ParentID && !($this->Parent() instanceof ProductCategory)) {
            $categories = [
                $this->ParentID => $this->Parent()->Title . ' (' . $this->Parent()->i18n_singular_name() . ')',
            ] + $categories;
        }

        return $categories;
    }

    /**
     * Helper function for generating a list of additional categories excluding the main parent.
     *
     * @return array categories
     */
    private function getCategoryOptionsNoParent()
    {
        $ancestors = $this->getAncestors()->map('ID', 'ID');
        $categories = ProductCategory::get();
        if (!empty($ancestors)) {
            $categories->filter('ID:not', $ancestors);
        }
        return $categories->map('ID', 'NestedTitle')->toArray();
    }

    /**
     * Get ids of all categories that this product appears in.
     *
     * @return array ids list
     */
    public function getCategoryIDs()
    {
        $ids = array();
        //ancestors
        foreach ($this->getAncestors() as $ancestor) {
            $ids[$ancestor->ID] = $ancestor->ID;
        }
        //additional categories
        $ids += $this->ProductCategories()->getIDList();

        return $ids;
    }

    /**
     * Get all categories that this product appears in.
     *
     * @return DataList category data list
     */
    public function getCategories()
    {
        return ProductCategory::get()->byIDs($this->getCategoryIDs());
    }

    /**
     * Conditions for whether a product can be purchased:
     *  - global allow purchase is enabled
     *  - product AllowPurchase field is true
     *  - if variations, then one of them needs to be purchasable
     *  - if not variations, selling price must be above 0
     *
     * Other conditions may be added by decorating with the canPurchase function
     *
     * @param Member $member
     * @param int    $quantity
     *
     * @return boolean
     */
    public function canPurchase($member = null, $quantity = 1)
    {
        $global = self::config()->global_allow_purchase;
        if (!$global || !$this->AllowPurchase) {
            return false;
        }
        $allowpurchase = false;
        $extension = self::has_extension('ProductVariationsExtension');
        if ($extension && Variation::get()->filter('ProductID', $this->ID)->first()) {
            foreach ($this->Variations() as $variation) {
                if ($variation->canPurchase($member, $quantity)) {
                    $allowpurchase = true;
                    break;
                }
            }
        } else {
            $allowpurchase = ($this->sellingPrice() > 0 || self::config()->allow_zero_price);
        }

        // Standard mechanism for accepting permission changes from decorators
        $permissions = $this->extend('canPurchase', $member, $quantity);
        $permissions[] = $allowpurchase;
        return min($permissions);
    }

    /**
     * Returns if the product is already in the shopping cart.
     *
     * @return boolean
     */
    public function IsInCart()
    {
        $item = $this->Item();
        return $item && $item->exists() && $item->Quantity > 0;
    }

    /**
     * Returns the order item which contains the product
     *
     * @return OrderItem
     */
    public function Item()
    {
        $filter = array();
        $this->extend('updateItemFilter', $filter);
        $item = ShoppingCart::singleton()->get($this, $filter);
        if (!$item) {
            //return dummy item so that we can still make use of Item
            $item = $this->createItem();
        }
        $this->extend('updateDummyItem', $item);
        return $item;
    }

    /**
     * @see Buyable::createItem()
     */
    public function createItem($quantity = 1, $filter = null)
    {
        $orderitem = self::config()->order_item;
        $item = new $orderitem();
        $item->ProductID = $this->ID;
        if ($filter) {
            //TODO: make this a bit safer, perhaps intersect with allowed fields
            $item->update($filter);
        }
        $item->Quantity = $quantity;
        return $item;
    }

    /**
     * The raw retail price the visitor will get when they
     * add to cart. Can include discounts or markups on the base price.
     */
    public function sellingPrice()
    {
        $price = $this->BasePrice;
        //TODO: this is not ideal, because prices manipulations will not happen in a known order
        $this->extend('updateSellingPrice', $price);
        //prevent negative values
        $price = $price < 0 ? 0 : $price;

        // NOTE: Ideally, this would be dependent on the locale but as of
        // now the Silverstripe Currency field type has 2 hardcoded all over
        // the place. In the mean time there is an issue where the displayed
        // unit price can not exactly equal the multiplied price on an order
        // (i.e. if the calculated price is 3.145 it will display as 3.15.
        // so if I put 10 of them in my cart I will expect the price to be
        // 31.50 not 31.45).
        return round($price, Order::config()->rounding_precision);
    }

    /**
     * This value is cased to Currency in temlates.
     */
    public function getPrice()
    {
        return $this->sellingPrice();
    }

    public function setPrice($price)
    {
        $price = $price < 0 ? 0 : $price;
        $this->setField('BasePrice', $price);
    }

    /**
     * Allow orphaned products to be viewed.
     */
    public function isOrphaned()
    {
        return false;
    }

    public function Link($action = null)
    {
        $link = parent::Link($action);
        $this->extend('updateLink', $link);
        return $link;
    }

    /**
     * If the product does not have an image, and a default image
     * is defined in SiteConfig, return that instead.
     *
     * @return Image
     * @throws \Exception
     */
    public function Image()
    {
        $image = $this->getComponent('Image');
        $this->extend('updateImage', $image);

        if ($image && $image->exists()) {
            return $image;
        }
        $image = SiteConfig::current_site_config()->DefaultProductImage();
        if ($image && $image->exists()) {
            return $image;
        }
        return null;
    }

    /**
     * Integration with opengraph module
     * TODO: Move this feature into a module
     *
     * @see    https://github.com/tractorcow/silverstripe-opengraph
     * @return string opengraph type
     */
    public function getOGType()
    {
        return 'product';
    }

    /**
     * Integration with the opengraph module
     * TODO: Move this feature into a module
     *
     * @return string url of product image
     */
    public function getOGImage()
    {
        if ($image = $this->Image()) {
            $min = self::config()->min_opengraph_img_size;
            $image = $min && $image->getWidth() < $min ? $image->setWidth($min) : $image;

            return Director::absoluteURL($image->URL);
        }
    }

    /**
     * Link to add this product to cart.
     *
     * @return string link
     */
    public function addLink()
    {
        return ShoppingCartController::add_item_link($this);
    }

    /**
     * Link to remove one of this product from cart.
     *
     * @return string link
     */
    public function removeLink()
    {
        return ShoppingCartController::remove_item_link($this);
    }

    /**
     * Link to remove all of this product from cart.
     *
     * @return string link
     */
    public function removeallLink()
    {
        return ShoppingCartController::remove_all_item_link($this);
    }
}
