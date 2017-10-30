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
use Bitrix\Main\SystemException;
use Bitrix\Main\EventManager, Bitrix\Main\Application, Bitrix\Main\Loader, Bitrix\Main\Entity\Base;


Loc::loadMessages(__FILE__);

class vasoft_sendpulse extends CModule
{
	var $MODULE_ID = 'vasoft.sendpulse';
	private $arTables = array(
		'\Vasoft\Sendpulse\FirstTable'
	);
	private $execlusionAdminFiles;

	function __construct()
	{
		$this->execlusionAdminFiles = array(
			'.',
			'..',
			'menu.php'
		);
		$arModuleVersion = array();
		include(__DIR__ . '/version.php');
		
		$this->MODULE_VERSION = $arModuleVersion['VERSION'];
		$this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
		$this->MODULE_NAME = Loc::getMessage("VSSP_MODULE_NAME");
		$this->MODULE_DESCRIPTION = Loc::getMessage("VSSP_MODULE_DESCRIPTION");
		$this->PARTNER_NAME = Loc::getMessage("VSSP_AUTHOR");
		$this->PARTNER_URI = 'https://va-soft.ru/';

		$this->SHOW_SUPER_ADMIN_GROUP_RIGHTS = 'Y';
		$this->MODULE_GROUP_RIGHTS = 'Y';
	}

	function DoInstall()
	{
		if (!$this->isVersionD7()) {
			throw new SystemException(Loc::getMessage("VSS_VERSION_REQUIRE"));
		} else {
			\Bitrix\Main\ModuleManager::registerModule($this->MODULE_ID);
			$this->installFiles();
			EventManager::getInstance()->registerEventHandler(
				"main",
				"OnAfterSetUserGroup",
				$this->MODULE_ID,
				"Vasoft\\Sendpulse\\Handlers",
				"OnAfterSetUserGroup"
			);
			EventManager::getInstance()->registerEventHandler(
				"main",
				"OnBeforeUserUpdate",
				$this->MODULE_ID,
				"Vasoft\\Sendpulse\\Handlers",
				"OnBeforeUserUpdate"
			);
			EventManager::getInstance()->registerEventHandler(
				"main",
				"OnAfterUserRegister",
				$this->MODULE_ID,
				"Vasoft\\Sendpulse\\Handlers",
				"OnAfterUserRegister"
			);
			$this->installDB();
		}
	}

	function DoUninstall()
	{
		global $APPLICATION;
		$context = Application::getInstance()->getContext();
		$request = $context->getRequest();
		if ($request['step'] < 2) {
			$APPLICATION->IncludeAdminFile(Loc::getMessage("VSSP_UNINSTALL_MODULE"), $this->GetPath() . '/install/unstep1.php');
		} elseif ($request['step'] == 2) {
			CAgent::RemoveModuleAgents($this->MODULE_ID);
			EventManager::getInstance()->unRegisterEventHandler(
				"main",
				"OnAfterSetUserGroup",
				$this->MODULE_ID,
				"Vasoft\\Sendpulse\\Handlers",
				"OnAfterSetUserGroup"
			);
			EventManager::getInstance()->unRegisterEventHandler(
				"main",
				"OnBeforeUserUpdate",
				$this->MODULE_ID,
				"Vasoft\\Sendpulse\\Handlers",
				"OnBeforeUserUpdate"
			);
			EventManager::getInstance()->unRegisterEventHandler(
				"main",
				"OnAfterUserRegister",
				$this->MODULE_ID,
				"Vasoft\\Sendpulse\\Handlers",
				"OnAfterUserRegister"
			);
			if ($request['savedata'] != 'Y') {
				$this->unInstallDB();
			}

			$this->unInstallFiles();
			\Bitrix\Main\ModuleManager::unRegisterModule($this->MODULE_ID);
			\Bitrix\Main\Config\Option::delete($this->MODULE_ID);
			$APPLICATION->IncludeAdminFile(Loc::getMessage("VSSP_UNINSTALL_MODULE"), $this->GetPath() . '/install/unstep2.php');
		}
	}

	function isVersionD7()
	{
		return CheckVersion(\Bitrix\Main\ModuleManager::getVersion('main'), '16.00.00');
	}

	function installFiles()
	{
		CopyDirFiles($this->GetPath() . '/install/components', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components', true, true);
	}

	function unInstallFiles()
	{
		DeleteDirFiles(dirname(__FILE__) . "/components", $_SERVER["DOCUMENT_ROOT"] . "/bitrix/components");
	}

	function GetPath($notDocumentRoot = false)
	{
		return ($notDocumentRoot)
			? preg_replace('#^(.*)\/(local|bitrix)\/modules#', '/$2/modules', dirname(__DIR__))
			: dirname(__DIR__);
	}

	function installDB()
	{
		if (\Bitrix\Main\Loader::includeModule($this->MODULE_ID)) {
			foreach ($this->arTables as $tableClass) {
				if (!Application::getConnection($tableClass::getConnectionName())->isTableExists(Base::getInstance($tableClass)->getDBTableName())) {
					Base::getInstance($tableClass)->createDbTable();
				}
			}
		}
	}

	function unInstallDB()
	{
		if (\Bitrix\Main\Loader::includeModule($this->MODULE_ID)) {
			foreach ($this->arTables as $tableClass) {
				Bitrix\Main\Application::getConnection($tableClass::getConnectionName())->queryExecute('drop table if exists ' . Base::getInstance($tableClass)->getDBTableName());
			}

		}
	}
}
