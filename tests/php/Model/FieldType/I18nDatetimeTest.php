<?php

namespace SilverShop\Core\Tests\Model\FieldType;


use SilverShop\Core\Model\FieldType\I18nDatetime;
use SilverStripe\Dev\SapphireTest;



class I18nDatetimeTest extends SapphireTest
{
    public function testField()
    {

        $field = new I18nDatetime();
        $field->setValue('2012-11-21 11:54:13');

        $field->Nice();
        $field->NiceDate();
        $field->Nice24();

        $this->markTestIncomplete('assertions!');
    }
}