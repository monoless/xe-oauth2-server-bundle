<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-21
 * Time: 오후 5:21
 */

namespace Monoless\Xe\OAuth2\Server\Supports;

use Monoless\Xe\OAuth2\Server\Utils\XpressUtil;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use GuzzleHttp\Psr7\ServerRequest;

class XpressSupport
{
    public static function temporaryPassCsrf()
    {
        $_SERVER['HTTP_REFERER'] = \Context::getDefaultUrl();
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }


    /**
     * @param integer $memberSrl
     * @return bool
     */
    public static function temporaryInitializeSession($memberSrl)
    {
        /**
         * @var \memberModel $memberModel
         */
        $memberModel = \getModel('member');

        $memberInfo = $memberModel->getMemberInfoByMemberSrl($memberSrl);
        if (!$memberInfo) {
            return false;
        } elseif ('Y' == $memberInfo->denied) {
            return false;
        }

        $loggedInfo = $memberInfo;
        $siteModuleInfo = \Context::get('site_module_info');
        if ($siteModuleInfo->site_srl) {
            $loggedInfo->group_list = $memberModel->getMemberGroups($memberSrl, $siteModuleInfo->site_srl);
        } else {
            if (0 === count($loggedInfo->group_list)) {
                $defaultGroup = $memberModel->getDefaultGroup(0);
                $memberController = \getController('member');
                $memberController->addMemberToGroup($memberSrl, $defaultGroup->group_srl, 0);
                $loggedInfo->group_list = [
                    $defaultGroup->group_srl =>  $defaultGroup->title
                ];
            }
        }

        \Context::set('is_logged', true);
        \Context::set('logged_info', $loggedInfo);

        return true;
    }

    /**
     * @return \stdClass|null
     */
    public static function getDefaultModule()
    {
        $moduleModel = \getModel('module');
        return $moduleModel->getDefaultMid();
    }

    /**
     * @param string $mId
     * @return \stdClass|null
     */
    public static function getModuleInfoByMId($mId)
    {
        $siteModuleInfo = \Context::get('site_module_info');
        $moduleModel = \getModel('module');
        $result = $moduleModel->getModuleInfoByMid($mId, $siteModuleInfo->site_srl);
        if (!($result instanceof \stdClass)) {
            return null;
        }

        return $result;
    }

    /**
     * @param integer $articleSrl
     * @return \stdClass|null
     */
    public static function getModuleInfoByArticleSrl($articleSrl)
    {
        $moduleModel = \getModel('module');
        $result = $moduleModel->getModuleInfoByDocumentSrl($articleSrl);
        if (!($result instanceof \stdClass)) {
            return null;
        }

        return $result;
    }

    /**
     * @param \stdClass $moduleInfo
     * @param string $action
     * @param string $view
     * @return boolean
     * @throws \Exception
     */
    public static function executeModule(\stdClass $moduleInfo, $action, $type = 'view')
    {
        $moduleModel = \getModel('module');
        $xmlInfo = $moduleModel->getModuleActionXml($moduleInfo->module);

        $moduleInstance = &\ModuleHandler::getModuleInstance($moduleInfo->module, $type);
        $moduleInstance->setAct($action);
        $moduleInstance->setModuleInfo($moduleInfo, $xmlInfo);
        return $moduleInstance->proc();
    }

    /**
     * @param string $moduleName
     * @param string $action
     * @param string $type
     * @return boolean
     * @throws \Exception
     */
    public static function executeModuleByName($moduleName, $action, $type = 'view')
    {
        $moduleInfo = self::getDefaultModule();

        $moduleModel = \getModel('module');
        $xmlInfo = $moduleModel->getModuleActionXml($moduleName);

        $moduleInstance = &\ModuleHandler::getModuleInstance($moduleName, $type);
        $moduleInstance->setAct($action);
        $moduleInstance->setModuleInfo($moduleInfo, $xmlInfo);
        return $moduleInstance->proc();
    }

    /**
     * @return int
     */
    public static function getSiteSrl()
    {
        $siteSrl = null;
        $moduleInfo = \Context::get('site_module_info');

        if ($moduleInfo) {
            $siteSrl = (int)$moduleInfo->site_srl;
        }

        return $siteSrl;
    }

    public static function temporarySessionCheck()
    {
        $session = $_SESSION['is_logged'];
        if (!$session) {
            $_SESSION['is_logged'] = true;
            $_SESSION['ipaddress'] = $_SERVER['REMOTE_ADDR'];
        }
    }

    public static function temporarySessionUncheck()
    {
        $session = $_SESSION['is_logged'];
        if ($session) {
            $_SESSION['is_logged'] = null;
            $_SESSION['ipaddress'] = null;
        }
    }

    /**
     * @return array|null
     */
    public static function getSitemap()
    {
        /**
         * @var \admin $admin
         */
        $admin = \getClass('admin');

        /**
         * @var \menuAdminModel $menuModel
         */
        $menuModel = \getAdminModel('menu');

        $menus = $menuModel->getMenus();
        $output = null;
        if (is_array($menus)) {
            $output = [];
            foreach ($menus as $key => $value) {
                if ($value->title == $admin->getAdminMenuName()) {
                    continue;
                }

                $cacheFile = sprintf(_XE_PATH_ . 'files/cache/menu/%s.php', $value->menu_srl);
                if (file_exists($cacheFile)) {
                    include($cacheFile);

                    if (!isset($menu)) {
                        $menu = new \stdClass();
                    }

                    $items = XpressUtil::convertMenuItem($menu->list);
                } else {
                    $items = null;
                }

                $output[] = [
                    'menuSrl' => $value->menu_srl,
                    'title' => $value->title,
                    'description' => $value->desc,
                    'items' => $items
                ];
            }
        }

        return $output;
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param string|null $scope
     * @return integer
     */
    public static function checkAuthPermission(RequestInterface $request, $scope = null)
    {
        $memberSrl = $request->getAttribute('oauth_user_id');

        if ($scope && !in_array($scope, $request->getAttribute('oauth_scopes'))) {
            throw new AccessDeniedException('permission_denied');
        }

        if (!self::temporaryInitializeSession($memberSrl)) {
            throw new AccessDeniedException('permission_denied');
        }

        return $memberSrl;
    }

    /**
     * @param int $memberSrl
     * @param int $currentPage
     * @param int $pagePerCount
     * @return \BaseObject|null
     */
    public static function getCommentListByMemberSrl($memberSrl, $currentPage = 0, $pagePerCount = 20)
    {
        $args = new \stdClass();
        $args->member_srl = $memberSrl;
        $args->list_count = $pagePerCount;
        $args->page = $currentPage;
        $output = executeQuery('comment.getCommentListByMemberSrl', $args, [
            'comment_srl',
            'document_srl',
            'nick_name',
            'member_srl',
            'content',
            'voted_count',
            'regdate',
            'last_update'
        ]);

        if (!$output->toBool() || !count($output->data)) {
            return null;
        }

        return $output;
    }
}