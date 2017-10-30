<?php
/**
 * Таблица для хранения информации о первой подписке пользователя на список рассылки
 * необходимо для реализации функционала автоматической подписки на рассылку
 * при первом включении пользователя в группу
 *
 * Модуль интеграции с сервисом Email рассылок SendPulse и BitrixCMS
 * @author Воробьев Александр
 * @version 1.0.0
 * @package vasoft.sendpulse
 * @see https://va-soft.ru/market/sendpulse/
 * @subpackage Библиотка модуля
 *
 */

namespace Vasoft\Sendpulse;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use \Bitrix\Main\Entity;
use Bitrix\Main\Type\Date;
use Bitrix\Main\UserGroupTable;

class FirstTable extends Entity\DataManager
{
	public static function getTableName()
	{
		return 'vasoft_sendpulse_first';
	}

	/**
	 * Структура таблицы
	 * ID - идентификатор пользователя в БД Bitrix
	 * LIST_ID - идентфикатор рассылки (адреной книги SendPulse)
	 * AUTOUNSUB - признак атоматической отписки по истечении времени
	 * DATE_CREATE - дата подписки
	 * USER - связь с таблицей поьзователей
	 * @return array
	 */
	public static function getMap()
	{
		return array(
			new Entity\IntegerField('ID', array(
				'primary' => true
			)),
			new Entity\IntegerField('LIST_ID', ['primary' => true]),
			new Entity\BooleanField(
				'AUTOUNSUB',
				[
					'default' => false
				]),
			new Entity\DateField('DATE_CREATE', []),
			new Entity\ReferenceField(
				'USER',
				'\Bitrix\Main\UserTable',
				array('=this.ID' => 'ref.ID')
			)
		);
	}

	/**
	 * Добавляет пользователей уже состоящих в переданных группах в таблицу
	 * @param $arGroups array массив идентификаторов групп
	 * @param $arLists array массив идентификаторов листов рассылок
	 */
	public static function setFromGroup($arGroups, $arLists)
	{
		if (!empty($arGroups) && !empty($arLists)) {
			$rsUsers = UserGroupTable::getList([
				'filter' => ['GROUP_ID' => $arGroups],
				'group' => ['USER_ID'],
				'select' => ['USER_ID']
			]);
			while ($arUser = $rsUsers->fetch()) {
				foreach ($arLists as $listId) {
					$arPrimary = [
						'ID' => $arUser['USER_ID'],
						'LIST_ID' => $listId
					];
					self::delete($arPrimary);
					$arPrimary['DATE_CREATE'] = new Date();
					$arPrimary['AUTOUNSUB'] = true;
					self::add($arPrimary);
				}
			}
		}
	}

	/**
	 * Агент выполняющийся раз в сутки и отписывающий (при соответсвующей настройке)
	 * пользователей от листов рассылки по истечению заданного времени
	 * @return string
	 */
	public static function agent()
	{
		if (Loader::includeModule('vasoft.sendpulse')) {
			$value = trim(Option::get('vasoft.sendpulse', 'GROUPFIRST'));
			if ($value != '') {
				$arConfig = unserialize($value);
				if (is_array($arConfig)) {
					$arData = [];
					$arUsers = [];
					foreach ($arConfig as $arRow) {
						$arRow['GROUPFIRST_REMOVE'] = intval($arRow['GROUPFIRST_REMOVE']);
						if ($arRow['GROUPFIRST_REMOVE'] > 0) {
							$date = new Date();
							$date->add('-P' . $arRow['GROUPFIRST_REMOVE'] . 'D');
							$rsUsers = self::GetList([
								'filter' => [
									'!AUTOUNSUB' => true,
									'<DATE_CREATE' => $date
								],
								'select' => ['ID', 'LIST_ID', 'EMAIL' => 'USER.EMAIL']
							]);

							while ($arUser = $rsUsers->fetch()) {
								if ($arUser['EMAIL'] != '') {
									$arUsers[$arUser['EMAIL']] = $arUser['ID'];
									if (isset($arData[$arUser['LIST_ID']])) {
										$arData[$arUser['LIST_ID']][] = $arUser['EMAIL'];
									} else {
										$arData[$arUser['LIST_ID']] = [$arUser['EMAIL']];
									}
								}
							}
						}
					}
					$id = Option::get('vasoft.sendpulse', 'ID');
					$code = Option::get('vasoft.sendpulse', 'SECURE');
					if (count($arData) > 0 && $id != '' && $code != '') {
						try {
							$api = new Client($id, $code);
							$connection = $api->isConnected();
						} catch (\Exception $e) {
							$connection = false;
						}
						if ($connection) {
							foreach ($arData as $listId => $arEmails) {
								$res = $api->removeEmails($listId, $arEmails);
								if (isset($res->result) && $res->result == 1) {
									foreach ($arEmails as $email) {
										self::update(
											['ID' => $arUsers[$email], 'LIST_ID' => $listId],
											['AUTOUNSUB' => true]
										);
									}
								}
							}
						}
					}
				}
			}
		}
		$time = trim(Option::get('vasoft.sendpulse', 'AGENT_TIME'));
		$arTime = explode(':', $time);
		if (count($arTime) != 2) {
			$arTime = [5, 0];
		}
		$hour = intval($arTime[0]);
		if ($hour < 0 || $hour > 24) {
			$hour = 0;
		}
		$min = intval($arTime[1]);
		if ($min < 0 || $min > 59) {
			$min = 0;
		}
		$GLOBALS['pPERIOD'] = mktime($hour, $min, 0, date('m'), date('d') + 1, date('Y')) - time();

		return '\Vasoft\Sendpulse\FirstTable::agent();';
	}
}