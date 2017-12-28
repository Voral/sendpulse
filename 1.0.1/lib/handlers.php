<?php

namespace Vasoft\Sendpulse;


use Bitrix\Main\Loader;
use Bitrix\Main\Type\Date;
use Bitrix\Main\UserTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

class Handlers
{
	public function OnBeforeUserUpdate(&$arFields)
	{
		if (isset($arFields['EMAIL']) && Loader::includeModule('vasoft.sendpulse')) {
			$arInfo = UserTable::getList([
				'filter' => ['ID' => $arFields['ID']],
				'select' => ['EMAIL']
			])->fetch();
			if ($arFields['EMAIL'] != $arInfo['EMAIL']) {
				$id = Option::get('vasoft.sendpulse', 'ID');
				$code = Option::get('vasoft.sendpulse', 'SECURE');
				if ($arInfo['EMAIL'] == '') {
					$bookId = intval(Option::get('vasoft.sendpulse', 'AUTOSUBSCRIB'));
					if ($id != '' && $code != '' && $bookId > 0) {
						try {
							$api = new Client($id, $code);
							$connection = $api->isConnected();
						} catch (\Exception $e) {
							$connection = false;
						}
						if ($connection) {
							$api->addEmails($bookId, $arFields['EMAIL']);
						}
					}
				} else {
					$result = false;
					if ($id != '' && $code != '') {
						try {
							$api = new Client($id, $code);
							$connection = $api->isConnected();
						} catch (\Exception $e) {
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

	public function OnAfterUserRegister(&$arFields)
	{
		if (isset($arFields['EMAIL']) && $arFields['EMAIL'] != '' && Loader::includeModule('vasoft.sendpulse')) {
			$id = Option::get('vasoft.sendpulse', 'ID');
			$code = Option::get('vasoft.sendpulse', 'SECURE');
			$bookId = intval(Option::get('vasoft.sendpulse', 'AUTOSUBSCRIB'));
			if ($id != '' && $code != '' && $bookId > 0) {
				try {
					$api = new Client($id, $code);
					$connection = $api->isConnected();
				} catch (\Exception $e) {
					$connection = false;
				}
				if ($connection) {
					$api->addEmails($bookId, [$arFields['EMAIL']]);
				}
			}
		}
	}

	public function OnAfterSetUserGroup($USER_ID, $arGroups)
	{
		$value = trim(Option::get('vasoft.sendpulse', 'GROUPFIRST'));
		if ($value == '') return;
		$arFilter = unserialize($value);
		if (!is_array($arFilter)) return;
		$needConnect = true;
		$email = '';
		$api = null;
		foreach ($arFilter as $arFilterRow) {
			$arExists = array_intersect($arGroups, $arFilterRow['GROUPFIRST']);
			if (count($arExists) > 0 && !empty($arFilterRow['GROUPFIRST_LIST'])) {
				if ($needConnect) {
					$arUser = UserTable::getList([
						'filter' => ['ID' => $USER_ID],
						'select' => ['EMAIL']
					])->fetch();
					if (!$arUser || trim($arUser['EMAIL']) == '') {
						return;
					}
					$email = $arUser['EMAIL'];
					$id = Option::get('vasoft.sendpulse', 'ID');
					$code = Option::get('vasoft.sendpulse', 'SECURE');
					if ($id == '' || $code == '') {
						return;
					}
					try {
						$api = new Client($id, $code);
						$needConnect = !$api->isConnected();
					} catch (\Exception $e) {
						$needConnect = true;
					}
					if ($needConnect) {
						return;
					}
				}
				foreach ($arFilterRow['GROUPFIRST_LIST'] as $bookId) {
					if ($bookId != 0) {
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