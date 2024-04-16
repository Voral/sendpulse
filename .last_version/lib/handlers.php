<?php /** @noinspection ReturnTypeCanBeDeclaredInspection */
/** @noinspection PhpUnused */

/** @noinspection PhpMissingReturnTypeInspection */

namespace Vasoft\Sendpulse;


use Bitrix\Main\ArgumentException;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\Date;
use Bitrix\Main\UserTable;
use Exception;

class Handlers
{
    private static string $id = '';
    private static string $code = '';

    /**
     * @param $arFields
     * @return bool
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function OnBeforeUserUpdate(&$arFields)
    {
        if (!self::isModuleActive()) {
            return true;
        }
        if (isset($arFields['EMAIL'])) {
            $arInfo = UserTable::getList([
                'filter' => ['ID' => $arFields['ID']],
                'select' => ['EMAIL']
            ])->fetch();
            if ($arFields['EMAIL'] !== $arInfo['EMAIL']) {
                if ($arInfo['EMAIL'] === '') {
                    $bookId = (int)Option::get('vasoft.sendpulse', 'AUTOSUBSCRIB');
                    if ($bookId > 0) {
                        try {
                            $api = new Client(static::$id, static::$code);
                            $api->addEmails($bookId, $arFields['EMAIL']);
                        } catch (Exception $e) {
                            // @todo обработать ситуацию
                        }
                    }
                } else {
                    $result = false;
                    try {
                        $api = new Client(static::$id, static::$code);
                        $connection = $api->isConnected();
                    } catch (Exception $e) {
                        $connection = false;
                    }
                    if ($connection) {
                        $result = $api->changeEmail($arInfo['EMAIL'], $arFields['EMAIL']);
                    }
                    if (!$result) {
                        global $APPLICATION;
                        $APPLICATION->throwException(Loc::getMessage("VSSP_ERR_SERVER_EMAIL_NOTCHANGED"));
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * @return bool
     * @throws LoaderException
     */
    private static function isModuleActive(): bool
    {
        if (!Loader::includeModule('vasoft.sendpulse')) {
            return false;
        }
        static::$id = trim(Option::get('vasoft.sendpulse', 'ID'));
        static::$code = trim(Option::get('vasoft.sendpulse', 'SECURE'));

        return (static::$id !== '') && (static::$code !== '');
    }

    /**
     * @param $arFields
     * @return void
     * @throws LoaderException
     */
    public static function OnAfterUserRegister(&$arFields)
    {
        if (!self::isModuleActive()) {
            return;
        }
        if (isset($arFields['EMAIL']) && $arFields['EMAIL'] !== '') {
            $bookId = (int)Option::get('vasoft.sendpulse', 'AUTOSUBSCRIB');
            if ($bookId > 0) {
                try {
                    $api = new Client(static::$id, static::$code);
                    $api->addEmails($bookId, [$arFields['EMAIL']]);
                } catch (Exception $e) {
                    // @todo обработать ситуацию
                }
            }
        }
    }

    /**
     * @param $USER_ID
     * @param $arGroups
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function OnAfterSetUserGroup($USER_ID, $arGroups)
    {
        if (is_array(current($arGroups))) {
            /**
             * При обновлении битрикс примерно в декабре 2017 нарушена обратная совместимость - решаем проблему
             */
            $date = (int)date('Ymd');
            $arGroupsPre = [];
            foreach ($arGroups as $key => $arGroup) {
                if ($arGroup['DATE_ACTIVE_FROM'] !== '' && $date < (int)Date::createFromText($arGroup['DATE_ACTIVE_FROM'])->format('Ymd')) {
                    continue;
                }
                if ($arGroup['DATE_ACTIVE_TO'] !== '' && $date > (int)Date::createFromText($arGroup['DATE_ACTIVE_TO'])->format('Ymd')) {
                    continue;
                }
                $arGroupsPre[] = $key;
            }
            $arGroups = $arGroupsPre;
        }
        $value = trim(Option::get('vasoft.sendpulse', 'GROUPFIRST'));
        if ($value === '') {
            return;
        }
        $arFilter = unserialize($value, ['allowed_classes' => false]);
        if (!is_array($arFilter)) {
            return;
        }
        $needConnect = true;
        $email = '';
        $api = null;
        foreach ($arFilter as $arFilterRow) {
            $arExists = array_intersect($arGroups, $arFilterRow['GROUPFIRST']);
            if (!empty($arFilterRow['GROUPFIRST_LIST']) && count($arExists) > 0) {
                if ($needConnect) {
                    $arUser = UserTable::getList([
                        'filter' => ['ID' => $USER_ID],
                        'select' => ['EMAIL']
                    ])->fetch();
                    if (!$arUser || trim($arUser['EMAIL']) === '') {
                        return;
                    }
                    $email = $arUser['EMAIL'];
                    try {
                        $api = new Client(static::$id, static::$code);
                        $needConnect = !$api->isConnected();
                    } catch (Exception $e) {
                        return;
                    }
                }
                foreach ($arFilterRow['GROUPFIRST_LIST'] as $bookId) {
                    if (0 !== (int)$bookId) {
                        $arFields = [
                            'ID' => $USER_ID,
                            'LIST_ID' => $bookId
                        ];
                        $arFirst = FirstTable::getById($arFields)->fetch();
                        if (!$arFirst) {
                            $api->addEmails($bookId, [$email]);
                            $arFields['DATE_CREATE'] = new Date();
                            $arFields['AUTOUNSUB'] = false;

                            FirstTable::add($arFields);
                        }
                    }
                }
            }
        }
    }
}