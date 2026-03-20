<?php
use CRM_Discountaddlreg_ExtensionUtil as E;

return [
  'name' => 'PriceFieldValueDiscount',
  'table' => 'civicrm_price_field_value_discount',
  'class' => 'CRM_Discountaddlreg_DAO_PriceFieldValueDiscount',
  'getInfo' => fn() => [
    'title' => E::ts('Price Field Value Discount'),
    'title_plural' => E::ts('Price Field Value Discounts'),
    'description' => E::ts('Discount properties for Discountaddlreg extension'),
    'log' => TRUE,
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique PriceFieldValueDiscount ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'is_active' => [
      'title' => E::ts('Is this discount active?'),
      'sql_type' => 'boolean',
      'input_type' => 'CheckBox',
      'required' => TRUE,
      'default' => FALSE,
      'usage' => [
        'import',
        'export',
        'duplicate_matching',
      ],
    ],
    'price_field_value_id' => [
      'title' => E::ts('Price Field Value ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to Price Field Value'),
      'entity_reference' => [
        'entity' => 'PriceFieldValue',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'max_discount_each' => [
      'title' => E::ts('Maximum discount per person'),
      'sql_type' => 'decimal(18,9)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('Max dollar amount to discount per person'),
      'input_attrs' => [
        'size' => '8',
      ],
    ],
    'min_person' => [
      'title' => E::ts('First discounted participant'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('First participant for whom the discount should be applied.'),
      'default' => 1,
    ],
    'max_persons' => [
      'title' => E::ts('Maximum discounted participants'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'description' => E::ts('Maximum number of additional participants receiving this discount'),
    ],
    'discount_field_id' => [
      'title' => E::ts('Apply discount in this price field'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'required' => TRUE,
      'description' => E::ts('FK to Price Field'),
      'entity_reference' => [
        'entity' => 'PriceField',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
  ],
];
