<?php
/**
 * Класс компонета формы управления подпиской пользователя
 *
 * Модуль интеграции с сервисом Email рассылок SendPulse и BitrixCMS
 * @author Воробьев Александр
 * @version 1.0.0
 * @package vasoft.sendpulse
 * @see https://va-soft.ru/market/sendpulse/
 * @subpackage Компонент управления подпиской
 *
 */

use Bitrix\Main\Application;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Vasoft\Sendpulse,
	Bitrix\Main\Config\Option,
	Bitrix\Main\Loader,
	Bitrix\Main\Localization\Loc;

Loc::loadLanguageFile(__FILE__);

class VasoftSendpulseComponent extends CBitrixComponent
{
	private $errorCode = 0;
	private $message = '';

	/**
	 * Обработка входных параметров компонента
	 * @param array $arParams параметры
	 * @return array
	 */
	public function onPrepareComponentParams($arParams)
	{
		$arParams["CACHE_TIME"] = 0;
		return $arParams;
	}

	private function checkRequest()
	{
		global $USER;
		if ($USER->IsAuthorized()) {
			$request = Application::getInstance()->getContext()->getRequest();
			$action = trim($request->getPost("action"));
			$list = intval($request->getPost("list"));
			if (Loader::includeModule('vasoft.sendpulse') && $action != '' && $list > 0) {
				try {
					$api = new Sendpulse\Client(Option::get('vasoft.sendpulse', 'ID'), Option::get('vasoft.sendpulse', 'SECURE'));
					$connection = $api->isConnected();
				} catch (Exception $e) {
					$connection = false;
				}
				if ($connection) {
					if ($action == 'subscribe') {
						$result = $api->addEmails($list, [$USER->GetEmail()]);
						if ($result->result) {
							$this->message = Loc::getMessage("VSSP_MSG_SUBSCRIBE_OK");
						} else {
							$this->errorCode = 1;
							$this->message = Loc::getMessage("VSSP_MSG_SUBSCRIBE_ERROR");
						}
					} elseif ($action == 'unsubscribe') {
						$result = $api->removeEmails($list, [$USER->GetEmail()]);
						if ($result->result) {
							$this->message = Loc::getMessage("VSSP_MSG_UNSUBSCRIBE_OK");
						} else {
							$this->errorCode = 1;
							$this->message = Loc::getMessage("VSSP_MSG_UNSUBSCRIBE_ERROR");
						}
					} elseif ($action == 'restore') {
						/**
						 * АПИ не дает нормаьно отписывать потому ставим костыли
						 */
						$res = $api->getEmailInfo($list, $USER->GetEmail());
						if (!isset($res->is_error) && $res->status == 6) {
							$api->removeEmailFromAllBooks($USER->GetEmail());
						} else {
							$api->removeEmails($list, [$USER->GetEmail()]);
						}
						$result = $api->addEmails($list, [$USER->GetEmail()]);
						if ($result->result) {
							$this->message = Loc::getMessage("VSSP_MSG_RESTORE_OK");
						} else {
							$this->errorCode = 1;
							$this->message = Loc::getMessage("VSSP_MSG_RESTORE_ERROR");
						}
					}
				} else {
					$this->errorCode = 1;
					$this->message = Loc::getMessage("VSSP_MSG_SERVER_ERROR");
				}
			}
		}
	}

	private function generateResult()
	{
		global $USER;
		$this->arResult = [
			'EMAIL' => '',
			'LISTS' => [],
			'EMAILS' => [],
			'SUBSCRIBED' => [],
			'INACTIVE' => 0,
			'RESULT_CODE' => $this->errorCode,
			'MESSAGE' => $this->message
		];
		if ($USER->IsAuthorized() && Loader::includeModule('vasoft.sendpulse')) {
			$this->arResult['EMAIL'] = $USER->GetEmail();
			try {
				$api = new Sendpulse\Client(Option::get('vasoft.sendpulse', 'ID'), Option::get('vasoft.sendpulse', 'SECURE'));
				$connection = $api->isConnected();
			} catch (Exception $e) {
				$connection = false;
			}
			if (!$connection) return;
			$arBooks = $api->listAddressBooksFiltered();
			$this->arResult['INACTIVE'] = 0;
			foreach ($arBooks as $obBook) {
				$obBook->active = !$obBook->visible;
				if (!$obBook->active) {
					++$this->arResult['INACTIVE'];
				}
				$this->arResult['LISTS'][$obBook->id] = $obBook;
			}
			$arUserInfo = $api->getEmailGlobalInfo($this->arResult['EMAIL']);
			if (is_array($arUserInfo)) {
				foreach ($arUserInfo as $obInfo) {
					$obInfo->active = false;
					if (!isset($obInfo->is_error) && isset($obInfo->status)) {
						$obInfo->statusName = Sendpulse\Client::getStatusName($obInfo->status);
						if ($obInfo->status == 1 || $obInfo->status == 0) {
							$obInfo->active = true;
						}
						$this->arResult['SUBSCRIBED'][] = $obInfo;
						if (!$this->arResult['LISTS'][$obInfo->book_id]->active) {
							--$this->arResult['INACTIVE'];
							$this->arResult['LISTS'][$obInfo->book_id]->active = true;
						}
					}
				}
			}
		}
	}

	/**
	 * Выполнение компонента
	 */
	public function executeComponent()
	{
		$this->checkRequest();
		$this->generateResult();
		$this->includeComponentTemplate();
	}
}
