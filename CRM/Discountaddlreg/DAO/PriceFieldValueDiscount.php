<?php

/**
 * DAOs provide an OOP-style facade for reading and writing database records.
 *
 * DAOs are a primary source for metadata in older versions of CiviCRM (<5.74)
 * and are required for some subsystems (such as APIv3).
 *
 * This stub provides compatibility. It is not intended to be modified in a
 * substantive way. Property annotations may be added, but are not required.
 * @property string $id
 * @property bool|string $is_active
 * @property string $price_field_value_id
 * @property string $max_discount_each
 * @property string $min_person
 * @property string $max_persons
 * @property string $discount_field_id
 */
class CRM_Discountaddlreg_DAO_PriceFieldValueDiscount extends CRM_Discountaddlreg_DAO_Base {

  /**
   * Required by older versions of CiviCRM (<5.74).
   * @var string
   */
  public static $_tableName = 'civicrm_price_field_value_discount';

}
