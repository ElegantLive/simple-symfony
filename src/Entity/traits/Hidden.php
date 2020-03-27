<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/19
 * Time: 10:50
 */

namespace App\Entity\Traits;

use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * Trait Hidden
 * @package App\Entity
 */
trait Hidden
{
    private $_filter = ['hidden', 'trust'];

    /**
     * @param $field
     */
    public function addHiddenItem ($field)
    {
        $fields = is_array($field) ? $field: explode(',', $field);

        $this->setHidden(array_merge($this->hidden, $fields));
    }

    /**
     * @param $field
     */
    public function removeHiddenItems ($field)
    {
        $fields = is_array($field) ? $field: explode(',', $field);

        $this->setHidden(array_diff($this->hidden, $fields));
    }

    /**
     * fresh entity what field hidden
     * @param array $filter
     * @param bool  $replace
     * @return array
     */
    public function filterHidden (array $filter = [], bool $replace = false)
    {
        $_filter = array_merge($this->_filter, $this->hidden);
        if (property_exists($this, 'deletedAt')) $_filter = array_merge($_filter, $this->deleteField);
        $_filter = $replace ? $filter: array_merge($_filter, $filter);

        return [AbstractNormalizer::IGNORED_ATTRIBUTES => $_filter];
    }

    /**
     * @return array
     */
    public function filterDeleted ()
    {
        return [AbstractNormalizer::ATTRIBUTES => $this->deleteField];
    }

    /**
     * @param array $fields
     */
    public function setHidden (array $fields)
    {
        $this->hidden = $fields;
    }
}