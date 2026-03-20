<?php

require_once 'discountaddlreg.civix.php';
use CRM_Discountaddlreg_ExtensionUtil as E;

/**
 * Implements hook_civicrm_buildForm().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_buildForm/
 */
function discountaddlreg_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Price_Form_Option') {
    if (
      $form->_action != CRM_Core_Action::UPDATE
      && $form->_action != CRM_Core_Action::ADD
      && $form->_action != CRM_Core_Action::VIEW
    ) {
      return;
    }
    $priceSetId = $form->getVar('_sid');
    $priceSet = civicrm_api3('PriceSet', 'getSingle', [
      'sequential' => 1,
      'id' => $priceSetId,
    ]);
    $extends = (array) $priceSet['extends'];
    $civiEventComponentId = CRM_Core_Component::getComponentID('CiviEvent');
    if (!in_array($civiEventComponentId, $extends)) {
      // This price set isn't  used for events, so nothing to do here; just return;
      return;
    }

    $form->addElement('advcheckbox', 'discountaddlreg_is_active', E::ts('Provide discounts to additional participants?'));
    $form->add(
      'text',
      'discountaddlreg_max_discount_each',
      E::ts('Maximum discount amount per person'),
      ['size' => 8, 'maxlength' => 8],
      TRUE
    );
    $form->add(
      'select',
      'discountaddlreg_min_person',
      E::ts('First participant to recieve the discount'),
      CRM_Core_SelectValues::getNumericOptions(1, 10),
      TRUE
    );
    $form->add(
      'select',
      'discountaddlreg_max_persons',
      E::ts('Maximum discounted participants'),
      CRM_Core_SelectValues::getNumericOptions(1, 9)
    );

    $priceFieldOptions = ['' => '- ' . E::ts('select') . ' -'];
    $priceFieldValueGet = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'return' => ["price_field_id.label", "price_field_id.id"],
      'amount' => 1,
      'price_field_id.price_set_id' => $priceSetId,
      'price_field_id.is_enter_qty' => 1,
      'price_field_id.is_active' => 1,
    ]);
    foreach ($priceFieldValueGet['values'] as $value) {
      $priceFieldOptions[$value['price_field_id.id']] = $value['price_field_id.label'];
    }
    $form->add(
      'select',
      'discountaddlreg_discount_field_id',
      E::ts('Apply discount in this price field'),
      $priceFieldOptions,
      TRUE
    );

    $fieldNames = [
      'discountaddlreg_is_active',
      'discountaddlreg_max_discount_each',
      'discountaddlreg_min_person',
      'discountaddlreg_max_persons',
      'discountaddlreg_discount_field_id',
    ];
    $bhfe = $form->get_template_vars('beginHookFormElements');
    if (!$bhfe) {
      $bhfe = [];
    }
    foreach ($fieldNames as $fieldName) {
      $bhfe[] = $fieldName;
    }
    $form->assign('beginHookFormElements', $bhfe);

    CRM_Core_Resources::singleton()->addScriptFile('com.joineryhq.discountaddlreg', 'js/CRM_Price_Form_Option.js');
    $jsVars = [
      'fieldNames' => $fieldNames,
      'descriptions' => [
        'discountaddlreg_is_active' => E::ts('Should discounts be applied to Additional Participants if the primary participant selects this option?'),
        'discountaddlreg_max_discount_each' => E::ts("A discount will be a applied to each eligible participant, up to this amount, but never exceeding that participant's total event fees"),
        'discountaddlreg_min_person' => E::ts('Discounts will be applied starting with this participant  (1 = primary registrant; 2 = first additional registrant; etc.)'),
        'discountaddlreg_max_persons' => E::ts('Discounts will be applied to this number of participants, starting with the first Additional Participant.'),
        'discountaddlreg_discount_field_id' => E::ts('Price field to be used as a line item to contain the discounts derived from this selection. Must be an active field in this price set, of type "Text / Numeric Quantity", with an amount of 1.00. This field will be hidden on all participant forms, but will be displayed on receipts, confirmation pages, and contribution records, if the participant receives a discount from this selection. Therefore, please review the label for the selected field, as it will be visible to participants in some cases.'),
      ],
    ];

    CRM_Core_Resources::singleton()->addVars('discountaddlreg', $jsVars);

    // Set default values
    if ($oid = $form->getVar('_oid')) {
      $defaults = [];
      $config = CRM_Discountaddlreg_Util::getConfig($oid);
      foreach ($config as $configProperty => $configValue) {
        if ($configProperty == 'max_discount_each') {
          $configValue = CRM_Utils_Money::format($configValue, NULL, '%a');
        }
        $defaults["discountaddlreg_{$configProperty}"] = $configValue;
      }
      $form->setDefaults($defaults);
    }

    // Take specific action when form has been submitted; namely, we need to
    // avoid 'required' for our ostensibly required fields, if is_active is off.
    if ($form->_flagSubmitted) {
      $submitValues = $form->exportValues();
      if (!CRM_Utils_Array::value('discountaddlreg_is_active', $submitValues, 0)) {
        $elementNames = array_keys($form->getVar('_elementIndex'));
        foreach ($elementNames as $elementName) {
          if (substr($elementName, 0, 16) == 'discountaddlreg_') {
            $index = array_search($elementName, $form->_required);
            if ($index !== FALSE) {
              unset($form->_required[$index]);
            }
          }
        }
      }
    }
  }
}

/**
 * Implements hook_civicrm_postProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postProcess/
 */
function discountaddlreg_civicrm_postProcess($formName, $form) {
  if ($formName == 'CRM_Price_Form_Option') {
    $submitValues = $form->exportValues();
    $priceFieldValueId = $form->getVar('_oid');
    if (!$priceFieldValueId) {
      // On 'create', $priceFieldValueId is not available in this scope. Rely
      // on the fact that label must be unique.
      $priceFieldValueGet = civicrm_api3('PriceFieldValue', 'get', [
        'sequential' => 1,
        'label' => CRM_Utils_Array::value('label', $submitValues),
        'price_field_id' => $form->getVar('_fid'),
      ]);
      if ($priceFieldValueGet['count']) {
        $priceFieldValueId = $priceFieldValueGet['id'];
      }
    }
    if ($priceFieldValueId) {
      if (($submitValues['discountaddlreg_is_active'] ?? FALSE)) {
        // Get the existing settings record for this priceFieldValue, if any.
        $priceFieldValueConfig = CRM_Discountaddlreg_Util::getConfig($priceFieldValueId);
        // If existing record wasn't found, we'll create.
        if (empty($priceFieldValueConfig)) {
          $priceFieldValueDiscount = \Civi\Api4\PriceFieldValueDiscount::create()
            ->addValue('price_field_value_id', $priceFieldValueId);
        }
        // If it was found, we'll just update it.
        else {
          $priceFieldValueDiscount = \Civi\Api4\PriceFieldValueDiscount::update()
            ->addWhere('id', '=', $priceFieldValueConfig['id']);
        }
        // Whether create or update, add the values of our injected fields.
        foreach ($submitValues as $submitValueName => $submitValueValue) {
          if (substr($submitValueName, 0, 16) == 'discountaddlreg_') {
            $paramName = substr($submitValueName, 16);
            $priceFieldValueDiscount->addValue($paramName, $submitValueValue);
          }
        }
        // Create/update settings record.
        $priceFieldValueDiscount
          ->execute();
      }
    }
  }
}

/**
 * Implements hook_civicrm_buildAmount().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_buildAmount/
 */
function discountaddlreg_civicrm_buildAmount($pageType, &$form, &$amounts) {
  if ($pageType != 'event') {
    // If this isn't event registration, do nothing and return;
    return;
  }
  // Otherwise, figure out which form we're  on and act accordingly.
  $formName = $form->getVar('_name');
  $participantPositionId = NULL;
  if (
    (substr($formName, 0, 12) == 'Participant_')
    && preg_match('/^Participant_([0-9]+)$/', $formName, $matches)
  ) {
    $participantPositionId = ($matches[1] + 1);
  }
  elseif ($formName == 'Register') {
    $participantPositionId = 1;
  }

  if ($participantPositionId) {
    // Always hide the discount fields for every participant.
    CRM_Discountaddlreg_Util::hideDiscountFields($amounts);
    $actionName = $form->controller->_actionName[1];
    if ($actionName == 'display') {
      // On display, set 'amount' of discount fields to '0.00'. This is necessary
      // because when using the 'go back' button, the discount field may have a
      // positive value; since the field is actually on the page and only hidden by
      // css (reference CRM_Discountaddlreg_Util::hideDiscountFields()), this
      // situation will cause the total to display as discounted on-page,
      // which is incorrect.
      CRM_Discountaddlreg_Util::devalueDiscountFields($amounts);
    }
    elseif ($actionName == 'upload') {
      // If action is upload, it means this participant form is submitted,
      // so we can know the selected values, and therefore discount only those.
      // This way the discounts don't appear until the very end, which is really
      // the only way to do it, since we must apply discounts AFTER the price
      // selections are submitted.
      $params = $form->getVar('_params');
      $submitValues = $form->getVar('_submitValues');
      // Unset all values for discount fields in $submitValues, to ensure no tampering.
      CRM_Discountaddlreg_Util::stripSubmittedDiscountFieldValues($amounts, $submitValues);
      // Determine which discounts are available and selected, and their discount value.
      $availableDiscounts = CRM_Discountaddlreg_Util::getAvailableDiscountConfig($amounts);
      // Shorthand for primary registrant submitted values. If we're submitting an
      // primary registrant, $params[0] will be null and values are in $submitValues;
      // otherwise, values are in $params[0].
      $primaryParticipantParams = ($params[0] ?? $submitValues);
      $selectedDiscounts = CRM_Discountaddlreg_Util::getSelectedDiscounts($availableDiscounts, $primaryParticipantParams);
      // Adjust $participantPositionId based on whether or not the primary registrant is attending
      // (which is an option provided by the extension com.joineryhq.groupreg).
      $countedParticipantPositionId = CRM_Discountaddlreg_Util::adjustCountedParticipantPosition($participantPositionId, $primaryParticipantParams, $form->getVar('_eventId'));
      $participantDiscounts = CRM_Discountaddlreg_Util::calculateParticipantDiscounts($amounts, $selectedDiscounts, $submitValues, $countedParticipantPositionId);
      foreach ($participantDiscounts as $discountFieldId => $participantDiscount) {
        // If any discount is to be applied, add the value of the 'discount price field'
        // to reflect that amount in the negative.
        if ($discountFieldId) {
          $submitValues["price_{$discountFieldId}"] = (-1 * $participantDiscount);
        }
      }
      $form->setVar('_submitValues', $submitValues);
    }
  }
}

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function discountaddlreg_civicrm_config(&$config) {
  _discountaddlreg_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function discountaddlreg_civicrm_install() {
  _discountaddlreg_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function discountaddlreg_civicrm_enable() {
  _discountaddlreg_civix_civicrm_enable();
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 */
// function discountaddlreg_civicrm_preProcess($formName, &$form) {

// } // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
// function discountaddlreg_civicrm_navigationMenu(&$menu) {
//   _discountaddlreg_civix_insert_navigation_menu($menu, 'Mailings', array(
//     'label' => E::ts('New subliminal message'),
//     'name' => 'mailing_subliminal_message',
//     'url' => 'civicrm/mailing/subliminal',
//     'permission' => 'access CiviMail',
//     'operator' => 'OR',
//     'separator' => 0,
//   ));
//   _discountaddlreg_civix_navigationMenu($menu);
// } // */
