<?php
use Bitrix\Main\Localization\Loc;
if (!defined('B_PROLOG_INCLUDED') || !B_PROLOG_INCLUDED) {
	die();
}
$this->setFrameMode(true);
?>
<div class="subscribe-info">
	<div class="subscribe-email-default"><?= Loc::getMessage("VSSP_LABEL_EMAIL") ?><span><?= $arResult['EMAIL'] ?></span></div>
	<? if ($arResult['MESSAGE'] != ''): ?>
		<div
			class="subscribe-msg<? if ($arResult['RESULT_CODE'] > 0) echo ' error' ?>"><?= $arResult['MESSAGE'] ?></div>
	<? endif ?>
	<? if ($arResult['INACTIVE'] > 0): ?>
		<form action="<?= $APPLICATION->GetCurPage() ?>" method="post" class="subscribe-new">
			<?= bitrix_sessid_post() ?>
			<input type="hidden" name="action" value="subscribe">
			<div class="subscribe-row">
				<label><?= Loc::getMessage("VSSP_LABEL_SUBSCRIBE") ?></label>
				<select name="list" id="subscribe-list">
					<?php
					foreach ($arResult['LISTS'] as $obList):
						if ($obList->active) continue;
						?>
						<option value="<?= $obList->id ?>"><?= $obList->name ?></option>
					<? endforeach; ?>
				</select>
			</div>
			<div class="subscribe-row">
				<button><?= Loc::getMessage("VSSP_SUBSCRIBE") ?></button>
			</div>
		</form>
	<? endif ?>
	<? if (count($arResult['SUBSCRIBED']) > 0): ?>
		<div class="subscribe-registered">
			<? foreach ($arResult['SUBSCRIBED'] as $bookId => $obInfo): ?>
				<div class="subscribe-item">
					<div class="subscribe-title">
						<?=Loc::getMessage("VSSP_SUBSCRIBE_ON")?> <?= $arResult['LISTS'][$obInfo->book_id]->name ?> (<?= $obInfo->statusName ?>)
					</div>
					<? if ($obInfo->active): ?>
						<form action="<?= $APPLICATION->GetCurPage() ?>" method="post">
							<?= bitrix_sessid_post() ?>
							<input type="hidden" name="action" value="unsubscribe">
							<input type="hidden" name="list" value="<?= $obInfo->book_id ?>">
							<button><?= Loc::getMessage("VSSP_UNSUBSCRIBE") ?></button>
						</form>
					<? else: ?>
						<form action="<?= $APPLICATION->GetCurPage() ?>" method="post">
							<?= bitrix_sessid_post() ?>
							<input type="hidden" name="action" value="restore">
							<input type="hidden" name="list" value="<?= $obInfo->book_id ?>">
							<button><?= Loc::getMessage("VSSP_SUBSCRIBE_RESTORE") ?></button>
						</form>
					<? endif ?>
				</div>
			<? endforeach; ?>
		</div>
	<? endif; ?>
</div>
