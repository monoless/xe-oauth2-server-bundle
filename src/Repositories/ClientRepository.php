<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-08
 * Time: 오전 11:56
 */

namespace Monoless\Xe\OAuth2\Server\Repositories;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use Monoless\Xe\OAuth2\Server\Entities\ClientEntity;
use Monoless\Xe\OAuth2\Server\Entities\ClientExtendEntity;
use Monoless\Xe\OAuth2\Server\Entities\PageContainerEntity;
use Monoless\Xe\OAuth2\Server\Utils\RequestUtil;
use Monoless\Xe\OAuth2\Server\Utils\ScopeUtil;

class ClientRepository implements ClientRepositoryInterface
{
    const ACTION_REGISTERED = 1;
    const ACTION_UPDATED = 2;
    const ACTION_DELETED = 3;

    private function entityToArgument(ClientEntity $clientEntity)
    {
        $args = new \stdClass();
        $args->unique_app_srl = $clientEntity->getIdentifier();
        $args->member_srl = $clientEntity->getMemberSrl();
        $args->name = $clientEntity->getName();
        $args->thumbnail = $clientEntity->getThumbnail();
        $args->description = $clientEntity->getDescription();
        $args->website_url = $clientEntity->getWebsiteUrl();
        $args->callback_url = $clientEntity->getRedirectUri();
        $args->access_level = ScopeUtil::scopeToPermission($clientEntity->getScope());
        $args->client_secret = $clientEntity->getClientSecret();
        $args->created_at = $clientEntity->getCreatedAt();
        $args->updated_at = date("YmdHis");
        return $args;
    }

    private function argumentToEntity(\stdClass $data)
    {
        $entry = new ClientEntity();
        $entry->setIdentifier($data->unique_app_srl);
        $entry->setMemberSrl($data->member_srl);
        $entry->setName($data->name);
        $entry->setThumbnail($data->thumbnail);
        $entry->setDescription($data->description);
        $entry->setScope(ScopeUtil::permissionToScope($data->access_level));
        $entry->setWebsiteUrl($data->website_url);
        $entry->setRedirectUri($data->callback_url);
        $entry->setClientSecret($data->client_secret);
        $entry->setCreatedAt($data->created_at);
        $entry->setUpdatedAt($data->updated_at);
        return $entry;
    }

    private function argumentToExtendEntity(\stdClass $data)
    {
        $entry = new ClientExtendEntity();
        $entry->setIdentifier($data->unique_app_srl);
        $entry->setMemberSrl($data->member_srl);
        $entry->setName($data->name);
        $entry->setThumbnail($data->thumbnail);
        $entry->setDescription($data->description);
        $entry->setScope(ScopeUtil::permissionToScope($data->access_level));
        $entry->setWebsiteUrl($data->website_url);
        $entry->setRedirectUri($data->callback_url);
        $entry->setClientSecret($data->client_secret);
        $entry->setCreatedAt($data->created_at);
        $entry->setUpdatedAt($data->updated_at);
        $entry->setUserId($data->user_id);
        $entry->setNickName($data->nick_name);
        return $entry;
    }

    /**
     * @param ClientEntity $clientEntity
     * @return boolean
     */
    public function removeClient(ClientEntity $clientEntity)
    {
        $args = new \stdClass();
        $args->unique_app_srl = $clientEntity->getIdentifier();
        $output = executeQuery('devcenter.deleteExternalApp', $args);
        if ($output->error) {
            return false;
        }

        $this->saveLog($clientEntity, self::ACTION_DELETED);
        return true;
    }

    /**
     * @param ClientEntity $clientEntity
     * @return boolean
     */
    public function persistNewClient(ClientEntity $clientEntity)
    {
        $args = $this->entityToArgument($clientEntity);
        $output = executeQuery('devcenter.insertExternalApp', $args);
        if ($output->error) {
            return false;
        }

        $this->saveLog($clientEntity, self::ACTION_REGISTERED);
        return true;
    }

    /**
     * @param ClientEntity $clientEntity
     * @return boolean
     */
    public function persistClient(ClientEntity $clientEntity)
    {
        $args = $this->entityToArgument($clientEntity);
        $output = executeQuery('devcenter.updateExternalApp', $args);
        if ($output->error) {
            return false;
        }

        $this->saveLog($clientEntity, self::ACTION_UPDATED);
        return true;
    }

    /**
     * @param ClientEntity $clientEntity
     * @param integer $action
     */
    public function saveLog(ClientEntity $clientEntity, $action)
    {
        $args = new \stdClass();
        $args->unique_app_srl = $clientEntity->getIdentifier();
        $args->member_srl = $clientEntity->getMemberSrl();
        $args->action = $action;
        $args->description = '';
        $args->ip = ip2long(RequestUtil::getIp());
        $args->created_at = date("YmdHis");

        executeQuery('devcenter.insertExternalAppHistory', $args);
    }

    /**
     * @param string $clientIdentifier
     * @param null $grantType
     * @param null $clientSecret
     * @param bool $mustValidateSecret
     * @return ClientEntityInterface|ClientEntity|null
     */
    public function getClientEntity($clientIdentifier, $grantType = null, $clientSecret = null, $mustValidateSecret = true)
    {
        $args = new \stdClass();
        $args->unique_app_srl = $clientIdentifier;
        $output = executeQuery('devcenter.findAppByUniqueAppSrl', $args);

        // Check if client is registered
        if (!$output->toBool() || !count($output->data)) {
            return null;
        }

        $entry = $output->data;

        if ($mustValidateSecret === true && $entry->client_secret != $clientSecret) {
            return null;
        }

        return $this->argumentToEntity($entry);
    }

    /**
     * @param string $name
     * @return boolean
     */
    public function isAppExistByName($name)
    {
        $args = new \stdClass();
        $args->name = $name;
        $output = executeQuery('devcenter.findAppByName', $args);
        return ($output->toBool() && count($output->data));
    }

    /**
     * @param integer $listCount
     * @param integer $pageCount
     * @param integer $page
     * @return PageContainerEntity
     */
    public function getApps($listCount = 20, $pageCount = 10, $page = 1)
    {
        $args = new \stdClass();
        $args->list_count = $listCount;
        $args->page_count = $pageCount;
        $args->page = $page;
        $output = executeQueryArray('devcenter.findApps', $args);

        $container = new PageContainerEntity();
        if ($output->toBool() && 0 < count($output->data)) {
            $entries = [];
            foreach ($output->data as $data) {
                $entries[] = $this->argumentToExtendEntity($data);
            }

            $container->setPageHandler($output->page_navigation);
            $container->setEntries($entries);
        }

        return $container;
    }

    /**
     * @param integer $memberSrl
     * @param integer $listCount
     * @param integer $pageCount
     * @param integer $page
     * @return PageContainerEntity
     */
    public function getAppsByMemberSrl($memberSrl, $listCount = 20, $pageCount = 10, $page = 1)
    {
        $args = new \stdClass();
        $args->member_srl = $memberSrl;
        $args->list_count = $listCount;
        $args->page_count = $pageCount;
        $args->page = $page;
        $output = executeQueryArray('devcenter.findAppsByMemberSrl', $args);

        $container = new PageContainerEntity();
        if ($output->toBool() && 0 < count($output->data)) {
            $entries = [];
            foreach ($output->data as $data) {
                $entries[] = $this->argumentToEntity($data);
            }

            $container->setPageHandler($output->page_navigation);
            $container->setEntries($entries);
        }

        return $container;
    }
}