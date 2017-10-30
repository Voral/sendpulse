<?
/**
 * Описание компонента формы управения подпиской
 *
 * Модуль интеграции с сервисом Email рассылок SendPulse и BitrixCMS
 * @author Воробьев Александр
 * @version 1.0.0
 * @package vasoft.sendpulse
 * @see https://va-soft.ru/market/sendpulse/
 * @subpackage Компонент управления подпиской
 *
 */
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
use Bitrix\Main\Localization\Loc;

Loc::loadLanguageFile(__FILE__);

$arComponentDescription = array(
	"NAME" => Loc::getMessage("VSSP_COMP_FORM"),
	"DESCRIPTION" => Loc::getMessage("VSSP_COMP_FORM_DESCRIPTION"),
	"SORT" => 20,
	"CACHE_PATH" => "Y",
	"PATH" => array(
		"ID" => ""
	),
	"COMPLEX" => "N",
);
