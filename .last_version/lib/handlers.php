<?php /** @noinspection ReturnTypeCanBeDeclaredInspection */
/** @noinspection PhpUnused */

/** @noinspection PhpMissingReturnTypeInspection */

namespace Vasoft\Sendpulse;


use Bitrix\Main\ArgumentException;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\Date;
use Bitrix\Main\UserTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Exception;

class Handlers
{
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
        if (isset($arFields['EMAIL']) && Loader::includeModule('vasoft.sendpulse')) {
            $arInfo = UserTable::getList([
                'filter' => ['ID' => $arFields['ID']],
                'select' => ['EMAIL']
            ])->fetch();
            if ($arFields['EMAIL'] !== $arInfo['EMAIL']) {
                $id = Option::get('vasoft.sendpulse', 'ID');
                $code = Option::get('vasoft.sendpulse', 'SECURE');
                if ($arInfo['EMAIL'] === '') {
                    $bookId = (int)Option::get('vasoft.sendpulse', 'AUTOSUBSCRIB');
                    if ($id !== '' && $code !== '' && $bookId > 0) {
                        try {
                            $api = new Client($id, $code);
                            $api->addEmails($bookId, $arFields['EMAIL']);
                        } catch (Exception $e) {
                            // @todo обработать ситуацию
                        }
                    }
                } else {
                    $result = false;
                    if ($id !== '' && $code !== '') {
                        try {
                            $api = new Client($id, $code);
                            $connection = $api->isConnected();
                        } catch (Exception $e) {
                            $connection = false;
                        }
                        if ($connection) {
                            $result = $api->changeEmail($arInfo['EMAIL'], $arFields['EMAIL']);
                        }
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

    public static function OnAfterUserRegister(&$arFields)
    {
        if (isset($arFields['EMAIL']) && $arFields['EMAIL'] !== '' && Loader::includeModule('vasoft.sendpulse')) {
            $id = Option::get('vasoft.sendpulse', 'ID');
            $code = Option::get('vasoft.sendpulse', 'SECURE');
            $bookId = (int)Option::get('vasoft.sendpulse', 'AUTOSUBSCRIB');
            if ($id !== '' && $code !== '' && $bookId > 0) {
                try {
                    $api = new Client($id, $code);
                    $api->addEmails($bookId, [$arFields['EMAIL']]);
                } catch (Exception $e) {
                    // @todo обработать ситуацию
                }
            }
        }
    }

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
                    $id = Option::get('vasoft.sendpulse', 'ID');
                    $code = Option::get('vasoft.sendpulse', 'SECURE');
                    if ($id === '' || $code === '') {
                        return;
                    }
                    try {
                        $api = new Client($id, $code);
                        $needConnect = !$api->isConnected();
                    } catch (Exception $e) {
                        $needConnect = true;
                    }
                    if ($needConnect) {
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