<?php
/**
 * Обвязка для класса клиента REST API SendPulse
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

use Bitrix\Main\Config\Option,
    Bitrix\Main\Localization\Loc;

class Client extends ApiClient
{
    public const TTL = 3000;
    public const CACHE_DIR = 'vasoft.sendpulse';

    /**
     * Client constructor.
     * @param $userId string ID пользователя в системе SendPulse
     * @param $secret string Secret для пдключения к SendPulse
     * @throws \Exception
     */
    public function __construct($userId, $secret)
    {
        if (empty($userId) || empty($secret)) {
            throw new \Exception('Empty ID or SECRET');
        }

        $this->userId = $userId;
        $this->secret = $secret;

        $this->setToken();

        if (empty($this->token)) {
            throw new \Exception('Could not connect to api, check your ID and SECRET');
        }
    }

    /**
     * Получение и хранение в кеше токена обмена
     */
    public function setToken():void
    {
        $this->token = '';
        $hashName = md5($this->userId . '::' . $this->secret);
        $cache = \Bitrix\Main\Data\Cache::createInstance();
        if ($cache->initCache(self::TTL, $hashName, self::CACHE_DIR)) {
            $this->token = $cache->getVars();
        } elseif ($cache->startDataCache() && $this->getToken()) {
            $cache->endDataCache($this->token);
        }
    }

    /**
     * Возвращает истину если соединение устанавливается
     * @return bool
     */
    public function isConnected():bool
    {
        return !empty($this->token);
    }

    /**
     * Текстовые описания стаусов подписки
     * @param $code integer код статуса
     * @return string
     */
    public static function getStatusName($code)
    {
        switch ($code) {
            case 0:
                return Loc::getMessage("VSSP_STATUS_NEW");
            case 1:
                return Loc::getMessage("VSSP_STATUS_ACTIVE");
            case 2:
                return Loc::getMessage("VSSP_STATUS_ACTIVATION_REQUESTED");
            case 3:
                return Loc::getMessage("VSSP_STATUS_ACTIVATION_REQUESTED_ADMIN");
            case 4:
                return Loc::getMessage("VSSP_STATUS_UNSUBSCRIBED");
            case 5:
                return Loc::getMessage("VSSP_STATUS_REJECTED");
            case 6:
                return Loc::getMessage("VSSP_STATUS_UNSUBSCRIBED_ALL");
            case 7:
                return Loc::getMessage("VSSP_STATUS_SEND_ACTIVATION");
            case 8:
                return Loc::getMessage("VSSP_STATUS_EMAIL_BAN");
            case 9:
                return Loc::getMessage("VSSP_STATUS_SEND_ERROR");
            case 10:
                return Loc::getMessage("VSSP_STATUS_REJECTED_HOST");
            case 11:
                return Loc::getMessage("VSSP_STATUS_REJECTED_USERNAME");
            case 12:
                return Loc::getMessage("VSSP_STATUS_REJECTED_ADDRESS_PART");
            case 13:
                return Loc::getMessage("VSSP_STATUS_EMAIL_DELETED");
            case 14:
                return Loc::getMessage("VSSP_STATUS_NOT_AVAILABLE");
        }
        return Loc::getMessage("VSSP_STATUS_UNKNOWN");
    }

    /**
     * Смена адреса в списках рассылок
     * @param $from string старый адрес
     * @param $to string новый адрес
     * @return bool
     */
    public function changeEmail($from, $to)
    {
        $res = false;
        $arUserInfo = $this->getEmailGlobalInfo($from);
        $arSubscribe = [];
        foreach ($arUserInfo as $obInfo) {
            if (isset($obInfo->status) && ($obInfo->status == 0 || $obInfo->status == 1)) {
                $arSubscribe[] = $obInfo->book_id;
            }
        }
        $result = $this->removeEmailFromAllBooks($from);
        if (!isset($result->is_error)) {
            if (count($arSubscribe) > 0 && $to !== '') {
                foreach ($arSubscribe as $book_id) {
                    $this->addEmails($book_id, [$to]);
                }
            }
            $res = true;
        }
        return $res;
    }

    /**
     * Отправка запроса к сервису
     * @param string $path команда
     * @param string $method string GET | POST | DELETE | PUT метод отправки
     * @param array $data массив передаваемых данных
     * @param bool $useToken добавлять токен взаголовок
     * @return \stdClass
     */
    protected function sendRequest($path, $method = 'GET', $data = array(), $useToken = true)
    {
        $url = $this->apiUrl . '/' . $path;
        $method = strtoupper($method);

        $oHttp = new \Bitrix\Main\Web\HttpClient();

        if ($useToken && !empty($this->token)) {
            $oHttp->setHeader('Authorization', 'Bearer ' . $this->token);
        }
        $oHttp->setHeader('Content-Type', 'application/x-www-form-urlencoded');

        switch ($method) {
            case 'POST':
                $oHttp->post($url, $data);
                break;
            case 'GET':
                $oHttp->get($url, $data);
                break;
            default:
                $oHttp->query($method, $url, $data);

        }

        $headerCode = $oHttp->getStatus();
        $responseBody = $oHttp->getResult();

        if ($headerCode === 401 && $this->refreshToken === 0) {
            ++$this->refreshToken;
            $this->getToken();
            $retval = $this->sendRequest($path, $method, $data);
        } else {
            $retval = new \stdClass();
            $retval->data = json_decode($responseBody);
            $retval->http_code = $headerCode;
        }

        return $retval;
    }

    /**
     * Список адресных книг с простановкой признака фиьтрации из настроек модуля
     * @param integer|null $limit количество возвращаемых записей
     * @param integer|null $offset смещение от начала выборки
     * @return mixed
     */
    public function listAddressBooksFiltered($limit = null, $offset = null)
    {
        $result = $this->listAddressBooks($limit, $offset); // TODO: Change the autogenerated stub

        $arFilter = unserialize(Option::get('vasoft.sendpulse', 'SHOW'));

        if (!empty($arFilter) && is_array($result)) {
            foreach ($result as &$obBook) {
                $obBook->visible = in_array($obBook->id, $arFilter);
            }
        }
        return $result;
    }

}