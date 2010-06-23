<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv2.1 (or at your option, any later version).
 * @package Form
 * @subpackage Form_Plugin
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

/**
 * Floating value input
 *
 * Use for text inputs where you only want to accept floats. The value saved by
 * {@link pnForm::pnFormGetValues()} is either null or a valid float.
 */
class Form_Plugin_FloatInput extends Form_Plugin_TextInput
{
    /**
     * Minimum value for validation.
     * 
     * @var float
     */
    public $minValue;

    /**
     * Maximum value for validation.
     * 
     * @var float
     */
    public $maxValue;

    /**
     * Get filename of this file.
     * 
     * @return string
     */
    function getFilename()
    {
        return __FILE__;
    }

    /**
     * Create event handler.
     *
     * @param Form_Render &$render Reference to Form render object.
     * @param array       &$params Parameters passed from the Smarty plugin function.
     * 
     * @see    Form_Plugin
     * @return void
     */
    function create(&$render, &$params)
    {
        $this->maxLength = 30;
        $params['width'] = '6em';
        parent::create($render, $params);
    }

    /**
     * Validates the input.
     * 
     * @param Form_Render &$render Reference to Form render object.
     * 
     * @return void
     */
    function validate(&$render)
    {
        parent::validate($render);
        if (!$this->isValid) {
            return;
        }

        if ($this->text != '') {
            $this->text = DataUtil::transformNumberInternal($this->text);
            if (!is_numeric($this->text)) {
                $this->setError(__('Error! Invalid number.'));
            }

            $i = $this->text;
            if ($this->minValue != null && $i < $this->minValue || $this->maxValue != null && $i > $this->maxValue) {
                if ($this->minValue != null && $this->maxValue != null) {
                    $this->setError(__f('Error! Range error. Value must be between %1$s and %2$s.', array(
                        $this->minValue,
                        $this->maxValue)));
                } else if ($this->minValue != null) {
                    $this->setError(__f('Error! The value must be %s or more.', $this->minValue));
                } else if ($this->maxValue != null) {
                    $this->setError(__f('Error! The value must be %s or less.', $this->maxValue));
                }
            }
        }
    }

    /**
     * Parses a value.
     * 
     * @param Form_Render &$render Reference to Form render object.
     * @param string      $text    Text.
     * 
     * @return string Parsed Text.
     */
    function parseValue(&$render, $text)
    {
        if ($text == '') {
            return null;
        }

        // process float value
        $text = floatval($text);

        return $text;
    }

    /**
     * Format the value to specific format.
     * 
     * @param Form_Render &$render Reference to Form render object.
     * @param string      $value   The value to format.
     * 
     * @return string Formatted value.
     */
    function formatValue(&$render, $value)
    {
        return DataUtil::formatNumber($value);
    }
}

