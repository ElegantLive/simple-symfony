<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/19
 * Time: 09:18
 */

namespace App\Entity\Traits;


trait Trust
{
    /**
     * @return array
     */
    public function getTrust (): array
    {
        return $this->trust;
    }

    /**
     * @param array $trust
     */
    public function setTrust (array $trust): void
    {
        $this->trust = $trust;
    }

    /**
     * @param array $data
     */
    public function setTrustFields (array $data)
    {
        if (empty($data)) return;

        foreach ($this->getTrust() as $item) {
            if (array_key_exists($item, $data)) {
                $field = sprintf('set%s', ucfirst($item));
                $this->$field($data[$item]);
            }
        }
    }
}