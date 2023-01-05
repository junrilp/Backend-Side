<?php

namespace App\Repository\Enum;

class EnumRepository implements EnumInterface
{
    /**
     * @param int|null $data
     * @param string $enum
     * 
     * @return [type]
     */
    public static function getEnum(
        int $data = null,
        string $enum
    ) {
        if (!$data) {
            return '';
        }
        
        return [
            'id' => $data,
            'value' => self::getEnumName($data, $enum),
        ];
    }

    /**
     * @param array $data
     * @param string $enum
     * 
     * @return [type]
     */
    public static function getEnums(
        array $data,
        string $enum
    ) {
        $array = [];
        if (!empty($data[0])):
            foreach($data as $row) {
                $array[] = [
                    'id' => $row,
                    'value' => self::getEnumName($row, $enum),
                ];
            }
        endif;
        return $array;
    }

    /**
     * @param int $data
     * @param string $enum
     * 
     * @return [type]
     */
    public static function getEnumName(
        int $data,
        string $enum
    ) {
        foreach( $enum::map() as $map ) {
            if ($map['id'] == $data) {
                return $map['value'];
            }
        }
    }
}
