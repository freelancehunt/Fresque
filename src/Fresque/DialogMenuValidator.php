<?php

namespace Fresque;

/**
 * DialogMenuValidator Class
 *
 * ezComponent class for validating dialog menu input
 */
class DialogMenuValidator implements \ezcConsoleMenuDialogValidator
{
    protected $elements = [];

    public function __construct($elements)
    {
        $this->elements = $elements;
    }

    public function fixup($result)
    {
        return (string) $result;
    }

    public function getElements()
    {
        return $this->elements;
    }

    public function getResultString()
    {

    }

    public function validate($result)
    {
        return in_array($result, array_keys($this->elements));
    }
}
