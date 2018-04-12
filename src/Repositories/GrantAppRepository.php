<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-04-04
 * Time: 오전 9:48
 */

namespace Monoless\Xe\OAuth2\Server\Repositories;


use League\OAuth2\Server\Repositories\RepositoryInterface;
use Monoless\Xe\OAuth2\Server\Entities\GrantAppEntity;
use Monoless\Xe\OAuth2\Server\Entities\GrantAppExtendEntity;
use Monoless\Xe\OAuth2\Server\Entities\PageContainerEntity;
use Monoless\Xe\OAuth2\Server\Utils\ScopeUtil;

class GrantAppRepository implements RepositoryInterface
{

    public function persisNewGrantApp(GrantAppEntity $grantAppEntity)
    {
        $entry = new \stdClass();
        $entry->unique_app_srl = $grantAppEntity->getUniqueAppSrl();
        $entry->member_srl = $grantAppEntity->getMemberSrl();
        $entry->revoked = $grantAppEntity->isRevoked() ? 'y' : 'n';
        $entry->created_at = $grantAppEntity->getCreatedAt();
        $entry->updated_at = $grantAppEntity->getUpdatedAt();
        $entry->expired_at = $grantAppEntity->getExpiredAt();

        // Check if client is registered
        $output = executeQuery('devcenter.findGrantAppByUniqueAppSrlAndMemberSrl', $entry);
        if (!$output->toBool() || !count($output->data)) {
            executeQuery('devcenter.insertGrantApp', $entry);
        } else {
            executeQuery('devcenter.updateGrantApp', $entry);
        }
    }

    /**
     * @param string $uniqueAppSrl
     * @param integer $memberSrl
     * @return boolean
     */
    public function revokeGrantApp($uniqueAppSrl, $memberSrl)
    {
        $args = new \stdClass();
        $args->unique_app_srl = $uniqueAppSrl;
        $args->member_srl = $memberSrl;
        $args->revoked = 'y';
        $args->updated_at = date('YmdHis');

        $output = executeQuery('devcenter.updateRevokedFromGrantApp', $args);
        return $output->toBool();
    }

    /**
     * @param string $uniqueAppSrl
     * @param integer $memberSrl
     * @return boolean
     */
    public function isGrantAppRevoked($uniqueAppSrl, $memberSrl)
    {
        $args = new \stdClass();
        $args->unique_app_srl = $uniqueAppSrl;
        $args->member_srl = $memberSrl;
        $output = executeQuery('devcenter.findGrantAppByAppSrlAndMemberSrl', $args);

        // Check if client is registered
        if (!$output->toBool() || !count($output->data)) {
            return true;
        }

        $entry = $output->data;

        return in_array($entry->revoked, ['y', 'Y']);
    }

    /**
     * @param integer $memberSrl
     * @param integer $listCount
     * @param integer $pageCount
     * @param integer $page
     * @return PageContainerEntity
     */
    public function getGrantAppsByMemberSrl($memberSrl, $listCount = 20, $pageCount = 10, $page = 1)
    {
        $args = new \stdClass();
        $args->member_srl = $memberSrl;
        $args->list_count = $listCount;
        $args->page_count = $pageCount;
        $args->page = $page;
        $output = executeQueryArray('devcenter.findGrantAppsByMemberSrl', $args);

        $container = new PageContainerEntity();
        if ($output->toBool() && 0 < count($output->data)) {
            $entries = [];
            foreach ($output->data as $data) {
                $entry = new GrantAppExtendEntity();

                $entry->setMemberSrl($memberSrl);
                $entry->setName($data->name);
                $entry->setThumbnail($data->thumbnail);
                $entry->setDescription($data->description);
                $entry->setScope(ScopeUtil::permissionToScope($data->access_level));
                $entry->setWebsiteUrl($data->website_url);
                $entry->setGrantSrl($data->grant_srl);
                $entry->setUniqueAppSrl($data->unique_app_srl);
                $entry->setRevoked('y' == $data->revoked);
                $entry->setCreatedAt($data->created_at);
                $entry->setUpdatedAt($data->updated_at);
                $entry->setExpiredAt($data->expired_at);
                $entries[] = $entry;
            }

            $container->setPageHandler($output->page_navigation);
            $container->setEntries($entries);
        }

        return $container;
    }

    /**
     * @return GrantAppEntity
     */
    public function getNewGrantApp()
    {
        return new GrantAppEntity();
    }
}