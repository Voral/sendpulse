<?php
/**
 * Второй шаг деинсталяции модуля
 *
 * Модуль интеграции с сервисом Email рассылок SendPulse и BitrixCMS
 * @author Воробьев Александр
 * @version 1.0.0
 * @package vasoft.sendpulse
 * @see https://va-soft.ru/market/sendpulse/
 * @subpackage Установка модуля
 *
 */

use Bitrix\Main\Localization\Loc;

if (!check_bitrix_sessid()) {
	return;
}
if ($ex = $APPLICATION->GetException()) {
	echo CAdminMessage::ShowMessage(array(
		'TYPE' => 'ERROR',
		'MESSAGE' => Loc::getMessage("MOD_UNINST_ERROR"),
		'DETAILS' => $ex->String(),
		'HTML' => true
	));
} else {
	echo CAdminMessage::ShowNote(Loc::getMessage('MOD_UNINST_OK'));
}
?>
<form action="<? echo $APPLICATION->GetCurPage(); ?>">
	<input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
	<input type="submit" value="<?= Loc::getMessage('MOD_BACK') ?>">
</form>