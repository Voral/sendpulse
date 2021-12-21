<?php
/**
 * Страница настроек модуля vasoft.sendpulse
 * @author Воробьев Александр
 * @see https://va-soft.ru/
 * @package vasoft.sendpulse
 * @version 1.0.3
 * @subpackage Настройки модуя
 */

use Bitrix\Main\Loader,
    Bitrix\Main\Config\Option,
    Bitrix\Main\Localization\Loc,
    Vasoft\Sendpulse;

Loc::loadMessages(__FILE__);
//ini_set('display_errors',1);
//ini_set('error_reporting',2047);

$module_id = "vasoft.sendpulse";
$MODULE_RIGHT = $APPLICATION->GetGroupRight($module_id);
if ($MODULE_RIGHT >= "W" && Loader::includeModule($module_id)) {

    $arDisplayOptions = array(
        "ID" => array(
            'TAB' => 'account',
            'NAME' => 'ID',
            'DEFAULT' => '',
            'EXT' => false,
            'IS_GROUP' => false,
            'OPTIONS' => array(
                'TYPE' => 'string',
                'SIZE' => 40
            )
        ),
        "SECURE" => array(
            'TAB' => 'account',
            'NAME' => 'Secret',
            'EXT' => false,
            'DEFAULT' => '',
            'IS_GROUP' => false,
            'OPTIONS' => array(
                'TYPE' => 'string',
                'SIZE' => 40
            )
        ),
        'SHOW' => array(
            'TAB' => 'option',
            'EXT' => false,
            'NAME' => Loc::getMessage("VSSP_OPTION_SHOW_NAME"),
            'DEFAULT' => Option::get($module_id, 'SHOW'),
            'IS_GROUP' => false,
            'OPTIONS' => array(
                'MULTI' => true,
                'TYPE' => 'select',
                'LIST' => array()
            )
        ),
        'AGENT_TIME' => array(
            'TAB' => 'option',
            'EXT' => false,
            'NAME' => Loc::getMessage("VSSP_OPTION_AGENT_TIME"),
            'DEFAULT' => Option::get($module_id, 'AGENT_TIME'),
            'IS_GROUP' => false,
            'OPTIONS' => array(
                'TYPE' => 'string',
                'SIZE' => 20
            )
        ),

//		"ONE_EMAIL" => array(
//			'TAB' => 'option',
//			'NAME' => Loc::getMessage("VSSP_OPTION_ONEEMAIL"),
//			'DEFAULT' => Option::get($module_id, 'SECURE'),
//			'IS_GROUP' => false,
//			'OPTIONS' => array(
//				'TYPE' => 'select',
//				'LIST' => array(
//					'Y' => Loc::getMessage("VSSP_OPTION_ONEEMAIL_Y"),
//					'N' => Loc::getMessage("VSSP_OPTION_ONEEMAIL_N")
//				)
//			)
//		),
        'AUTOSUBSCRIB' => array(
            'TAB' => 'auto',
            'EXT' => false,
            'NAME' => Loc::getMessage("VSSP_OPTION_AUTOSUBSCRIBE"),
            'IS_GROUP' => false,
            'DEFAULT' => Option::get($module_id, 'AUTOSUBSCRIB'),
            'OPTIONS' => array(
                'TYPE' => 'select',
                'LIST' => array()
            )
        ),
        'GROUPFIRST' => array(
            'TAB' => 'auto',
            'EXT' => true,
            'NAME' => Loc::getMessage("VSSP_OPTION_GROUPFIRST"),
            'DEFAULT' => Option::get($module_id, 'GROUPFIRST'),
            'IS_GROUP' => true,
            'OPTIONS' =>
                [
                    'GROUPFIRST' => [
                        'NAME' => Loc::getMessage("VSSP_OPTION_GROUPFIRST"),
                        'MULTI' => true,
                        'TYPE' => 'select',
                        'LIST' => array()
                    ],
                    'GROUPFIRST_LIST' => [
                        'NAME' => Loc::getMessage("VSSP_OPTION_GROUPFIRST_LIST"),
                        'MULTI' => true,
                        'TYPE' => 'select',
                        'LIST' => array()
                    ],
                    'GROUPFIRST_REMOVE' => [
                        'NAME' => Loc::getMessage("VSSP_OPTION_GROUPFIRST_REMOVE"),
                        'TYPE' => 'string',
                    ],
                    'GROUPFIRST_UPDATE' => [
                        'NAME' => Loc::getMessage("VSSP_OPTION_GROUPFIRST_UPDATE"),
                        'TYPE' => 'select',
                        'LIST' => array(
                            0 => Loc::getMessage("VSSP_NO"),
                            1 => Loc::getMessage("VSSP_YES")
                        )
                    ]
                ],
        ),
    );
    $aTabs = array();
    if ($REQUEST_METHOD == "GET" && $MODULE_RIGHT == "W" && strlen($RestoreDefaults) > 0 && check_bitrix_sessid()) {
        COption::RemoveOption($module_id);
        $z = CGroup::GetList($v1 = "id", $v2 = "asc", array("ACTIVE" => "Y", "ADMIN" => "N"));
        while ($zr = $z->Fetch()) {
            $APPLICATION->DelGroupRight($module_id, array($zr["ID"]));
        }
    }
    if ($REQUEST_METHOD == "POST" && strlen($Update) > 0 && $MODULE_RIGHT == "W" && check_bitrix_sessid()) {
        foreach ($arDisplayOptions as $key => $arOption) {

            if (!array_key_exists($key, $_POST)) {
                if ($key == 'GROUPFIRST') {
                    Option::set($module_id, $key, '');
                }
                continue;
            }
            if ($key == 'SHOW') {
                Option::set($module_id, $key, serialize(${$key}));
            } elseif ($key == 'AGENT_TIME') {
                $arValue = explode(':', ${$key});
                if (count($arValue) != 2) {
                    $value = '05:00';
                } else {
                    $hour = intval($arValue[0]);
                    if ($hour < 0 || $hour > 24) {
                        $hour = 0;
                    }
                    $min = intval($arValue[1]);
                    if ($min < 0 || $min > 59) {
                        $min = 0;
                    }
                    $value = sprintf('%02d:%02d', $hour, $min);
                }
                Option::set($module_id, $key, $value);
            } elseif ($key == 'GROUPFIRST') {
                $arFieldValue = [];
                $needAgent = false;
                foreach ($_POST['GROUPFIRST'] as $index => $group) {
                    $arValue = [
                        'GROUPFIRST' => $_POST['GROUPFIRST'][$index],
                        'GROUPFIRST_LIST' => $_POST['GROUPFIRST_LIST'][$index],
                        'GROUPFIRST_REMOVE' => intval($_POST['GROUPFIRST_REMOVE'][$index])
                    ];
                    if ($arValue['GROUPFIRST_REMOVE'] > 0) $needAgent = true;
                    if (intval($_POST['GROUPFIRST_UPDATE'][$index]) == 1) {
                        Sendpulse\FirstTable::setFromGroup($arValue['GROUPFIRST'], $arValue['GROUPFIRST_LIST']);
                    }
                    $arFieldValue[] = $arValue;
                }
                Option::set($module_id, $key, serialize($arFieldValue));
                CAgent::RemoveAgent("\Vasoft\Sendpulse\FirstTable::agent();", "vasoft.sendpulse");
                if ($needAgent) {
                    $date = new \Bitrix\Main\Type\Date();
                    $date->add('P1D');
                    $strDate = $date->format("d.m.Y") . ' 05:00:00';
                    CAgent::AddAgent(
                        "\Vasoft\Sendpulse\FirstTable::agent();",
                        "vasoft.sendpulse",
                        "N",
                        86400,
                        $strDate,
                        "Y",
                        $strDate,
                        30);
                }
            } else {
                Option::set($module_id, $key, ${$key});
            }
        }
    }
    $arDisplayOptions['ID']['DEFAULT'] = Option::get($module_id, 'ID');
    $arDisplayOptions['SECURE']['DEFAULT'] = Option::get($module_id, 'SECURE');
    $connection = false;
    try {
        $client = new Sendpulse\Client($arDisplayOptions['ID']['DEFAULT'], $arDisplayOptions['SECURE']['DEFAULT']);
        $connection = $client->isConnected();
        if ($connection) {
            $arBooks = $client->listAddressBooks();
            if (count($arBooks) > 0) {
                $arDisplayOptions['AUTOSUBSCRIB']['OPTIONS']['LIST'] = [0 => Loc::getMessage("VSSP_OPTIONS_NO_SUTOSUBSCRIBE")];
                foreach ($arBooks as $arBook) {
                    $arDisplayOptions['AUTOSUBSCRIB']['OPTIONS']['LIST'][$arBook->id] = $arBook->name;
                    $arDisplayOptions['SHOW']['OPTIONS']['LIST'][$arBook->id] = $arBook->name;
                    $arDisplayOptions['GROUPFIRST']['OPTIONS']['GROUPFIRST_LIST']['LIST'][$arBook->id] = $arBook->name;
                }
            }
        }
    } catch (Exception $e) {
        $connection = false;
    }
    if (empty($arDisplayOptions['AUTOSUBSCRIB']['OPTIONS']['LIST'])) {
        unset($arDisplayOptions['AUTOSUBSCRIB']);
        unset($arDisplayOptions['GROUPFIRST']);
        unset($arDisplayOptions['SHOW']);
    } else {
        $rsGroups = \Bitrix\Main\GroupTable::getList([
            'select' => ['ID', 'NAME'],
            'order' => ['C_SORT' => 'ASC']
        ]);
        while ($arGroup = $rsGroups->fetch()) {
            $arDisplayOptions['GROUPFIRST']['OPTIONS']['GROUPFIRST']['LIST'][$arGroup['ID']] = $arGroup['NAME'];
        }
        $aTabs[] = array("DIV" => "option", "TAB" => Loc::getMessage("VSSP_OPTION_TAB_OPTIONS"), "TITLE" => Loc::getMessage("VSSP_OPTION_TAB_OPTIONS"));
        $aTabs[] = array("DIV" => "auto", "TAB" => Loc::getMessage("VSSP_OPTION_TAB_AUTOSUBSRIBE"), "TITLE" => Loc::getMessage("VSSP_OPTION_TAB_AUTOSUBSRIBE"));
    }

    $arViewOptions = [];
    foreach ($arDisplayOptions as $key => $arOption) {
        $arOption['VALUE'] = Option::get($module_id, $key, $arOption['DEFAULT']);
        if ($arOption['EXT'] || (isset($arOption['OPTIONS']['MULTI']) && $arOption['OPTIONS']['MULTI'])) {
            $arOption['VALUE'] = unserialize($arOption['VALUE']);
        }
        $arOption['CODE'] = $key;
        $arOption['FIELD_NAME_PART'] = '';
        $arOption['FIELD_NAME_PART2'] = (isset($arOption['OPTIONS']['MULTI']) && $arOption['OPTIONS']['MULTI']) ? '[]' : '';
        $arOption['FIELD_NAME'] = $key;
        if ($arOption['EXT']) {
            $arValues = $arOption['VALUE'];
            $i = 0;
            foreach ($arValues as $i => $arCurValues) {
                $arOption['VALUE'] = $arCurValues;
                $arOption['FIELD_NAME'] = $key;
                $arOption['FIELD_NAME_PART'] = '[' . $i . ']';
                $arViewOptions[] = $arOption;
                $arViewOptions[] = ['SEPARATOR' => true, 'TAB' => $arOption['TAB']];
            }
            $arOption['FIELD_NAME_PART'] = '[' . ++$i . ']';
            $arOption['VALUE'] = false;
            $arViewOptions[] = $arOption;
        } else {
            $arViewOptions[] = $arOption;
        }
    }
    $arViewOptions2 = [];
    foreach ($arViewOptions as $arOption) {
        if (isset($arOption['IS_GROUP']) && $arOption['IS_GROUP']):
            $arViewOptions2[] = ['SEPARATOR' => true, 'TAB' => $arOption['TAB']];
            $arOptionList = $arOption['OPTIONS'];
            $arOptionValues = $arOption['VALUE'];
            foreach ($arOptionList as $key => $arOptionItem) {
                $arOption['FIELD_NAME_PART2'] = (isset($arOptionItem['MULTI']) && $arOptionItem['MULTI']) ? '[]' : '';
                $arOption['VALUE'] = (isset($arOptionValues[$key])) ? $arOptionValues[$key] : false;
                $arOption['NAME'] = $arOptionItem['NAME'];
                $arOption['FIELD_NAME'] = $key . $arOption['FIELD_NAME_PART'] . $arOption['FIELD_NAME_PART2'];
                $arOption['OPTIONS'] = $arOptionItem;
                $arViewOptions2[] = $arOption;
            }
            $arViewOptions2[] = ['SEPARATOR' => true, 'TAB' => $arOption['TAB']];
        else:
            if (isset($arOption['NAME'])) {
                $arOption['FIELD_NAME'] = $arOption['FIELD_NAME'] . $arOption['FIELD_NAME_PART'] . $arOption['FIELD_NAME_PART2'];
            }
            $arViewOptions2[] = $arOption;
        endif;
    }

    $aTabs[] = array("DIV" => "account", "TAB" => Loc::getMessage("VSSP_OPTION_TAB_ACCOUNT"), "TITLE" => Loc::getMessage("VSSP_OPTION_TAB_ACCOUNT"));
    $aTabs[] = array("DIV" => "rights", "TAB" => GetMessage("MAIN_TAB_RIGHTS"), "TITLE" => GetMessage("MAIN_TAB_TITLE_RIGHTS"));
    echo BeginNote();
    echo ($connection) ? Loc::getMessage("VSSP_CONNECTION_SUCCESS") : Loc::getMessage("VSSP_CONNECTION_NO");
    echo EndNote();
    ?>
    <?
    $tabControl = new CAdminTabControl("tabControl", $aTabs);
    $tabControl->Begin();
    ?>
    <form method="post"
          action="<? echo $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialcharsbx($mid) ?>&lang=<?= LANGUAGE_ID ?>">
        <?php
        echo bitrix_sessid_post();
        foreach ($aTabs as $arTab):
            $tabControl->BeginNextTab();
            if ($arTab['DIV'] == 'rights'):
                require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/admin/group_rights.php");
            else:
                $prevSeparator = false;
                foreach ($arViewOptions2 as $arOption):
                    if ($arOption['TAB'] != $arTab['DIV']) continue;
                    if (isset($arOption['SEPARATOR'])):
                        if ($prevSeparator) continue;
                        $prevSeparator = true;
                        ?>
                        <tr>
                            <td colspan="2">
                                <hr>
                            </td>
                        </tr>
                    <?php
                    else:
                        $prevSeparator = false;
                        ?>
                        <tr>
                            <td class="adm-detail-content-cell-l"><?= $arOption['NAME']; ?></td>
                            <td class="adm-detail-content-cell-r">
                                <?php
                                if ($arOption['OPTIONS']['TYPE'] === 'string'): ?>
                                    <input size="<?= $arOption['OPTIONS']['SIZE'] ?>" maxlength="255"
                                           value="<?= $arOption['VALUE'] ?>" type="text"
                                           name="<?= $arOption['FIELD_NAME'] ?>">
                                <?php
                                elseif ($arOption['OPTIONS']['TYPE'] === 'select'):
                                    if (LANG_CHARSET === "windows-1251") {
                                        $arOption['OPTIONS']['LIST'] = Sendpulse\Utils::mbConvertArray($arOption['OPTIONS']['LIST']);
                                    }
                                    ?>
                                    <select
                                            name="<?= $arOption['FIELD_NAME'] ?>"<? if ($arOption['OPTIONS']['MULTI']) echo ' multiple' ?>>

                                        <? foreach ($arOption['OPTIONS']['LIST'] as $val => $name): ?>
                                            <option
                                                    value="<?= $val ?>"<?php
                                            if (
                                                (is_array($arOption['VALUE']) && in_array($val, $arOption['VALUE'], false))
                                                || ($val == $arOption['VALUE'])
                                            ) echo ' selected';
                                            ?>><?= $name ?></option>
                                        <? endforeach; ?>
                                    </select>
                                <? endif ?>
                            </td>
                        </tr>
                    <?endif;
                    if ($arOption['CODE'] == 'SECURE'):
                        echo BeginNote();
                        echo Loc::getMessage("VSSP_SENDPULSE_INFO");
                        echo EndNote();
                    endif;
                endforeach;
            endif;
        endforeach;
        $tabControl->Buttons(); ?>
        <script language="JavaScript">
            function RestoreDefaults() {
                if (confirm('<?echo AddSlashes(GetMessage("MAIN_HINT_RESTORE_DEFAULTS_WARNING"))?>'))
                    window.location = "<?echo $APPLICATION->GetCurPage()?>?RestoreDefaults=Y&lang=<?=LANGUAGE_ID?>&mid=<?echo urlencode($mid)?>&<?=bitrix_sessid_get()?>";
            }
        </script>
        <input <? if ($MODULE_RIGHT < "W") echo "disabled" ?> type="submit" name="Update"
                                                              value="<?= Loc::getMessage("VSSP_SAVE") ?>">
        <input type="hidden" name="Update" value="Y">
        <input <? if ($MODULE_RIGHT < "W") echo "disabled" ?> type="button"
                                                              title="<? echo Loc::getMessage("MAIN_HINT_RESTORE_DEFAULTS") ?>"
                                                              onclick="RestoreDefaults();"
                                                              value="<?= Loc::getMessage("VSSP_DEFAULT") ?>">
        <? $tabControl->End(); ?>
    </form>
    <?
}


