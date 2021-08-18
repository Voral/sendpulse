<?
namespace Vasoft\Sendpulse;

class Utils
{
    /*Рекрусивная смена кодировки значений массива*/
    public static function mbConvertArray($array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::mbConvertArray($value);
            } else {
                $array[$key] = self::detectAndConvertToWin($value);
            }
        }
        return $array;
    }

    public static function detectEncode($string)
    {
        return mb_detect_encoding($string, implode(',', mb_list_encodings()));
    }

    public static function detectAndConvertToWin($string)
    {
        if ($string && detectEncode($string) == "UTF-8") {
            return mb_convert_encoding($string, "windows-1251", "UTF-8");
        }
        return $string;
    }

}
?>