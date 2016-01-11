<?php

/**
 * Globally useful tools
 */
class ShopTools{

	public static function price_for_display($price) {
		$currency = ShopConfig::get_site_currency();
		$field = new Money("Price");
		$field->setAmount($price);
		$field->setCurrency($currency);
		return $field;
	}

    public static function get_current_locale()
    {
        if(class_exists('Translatable')){
            return Translatable::get_current_locale();
        }

        if(class_exists('Fluent')){
            return Fluent::current_locale();
        }

        return i18n::get_locale();
    }

    public static function install_locale($locale)
    {
        if(class_exists('Translatable')){
            Translatable::set_current_locale($locale);
        } else if(class_exists('Fluent')){
            Fluent::set_persist_locale($locale);
            Fluent::install_locale($locale, false);
        } else {
            i18n::set_locale($locale);
        }
    }

}
