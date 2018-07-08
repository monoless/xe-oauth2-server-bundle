<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-21
 * Time: 오전 11:48
 */

namespace Monoless\Xe\OAuth2\Server\Services;


use GuzzleHttp\Psr7\ServerRequest;
use Monoless\Xe\OAuth2\Server\Supports\XpressSupport;
use Monoless\Xe\OAuth2\Server\Utils\CommonUtil;
use Monoless\Xe\OAuth2\Server\Utils\XpressUtil;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Zend\Diactoros\Response\JsonResponse;

class XpressService
{
    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return ResponseInterface|JsonResponse
     */
    public static function getProfile(RequestInterface $request, ResponseInterface $response)
    {
        try {
            XpressSupport::checkAuthPermission($request);
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        // ignore xe memberInfo system
        \Context::set('member_unique_srl', '');

        $queries = $request->getQueryParams();
        if (array_key_exists('member_unique_srl', $queries)) {
            if (!in_array('message', $request->getAttribute('oauth_scopes'))) {
                return new JsonResponse([
                    'error' => 'permission_denied',
                    'message' => 'scope invalid',
                ], 401);
            }

            $targetSrl = CommonUtil::decodeId($queries['member_unique_srl']);
            if (!$targetSrl || !is_numeric($targetSrl)) {
                return new JsonResponse([
                    'error' => 'not_found',
                    'message' => 'The resource is gone',
                ], 404);
            }

            \Context::set('member_srl', $targetSrl);
        }

        try {
            XpressSupport::executeModuleByName('member', 'dispMemberInfo');
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            if ('not_found' == $message) {
                return new JsonResponse([
                    'error' => 'not_found',
                    'message' => 'The resource is gone',
                ], 404);
            }

            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $message,
            ], 500);
        }

        $memberInfo = \Context::get('memberInfo');

        /**
         * @var array $displayDatas
         */
        $displayDatas = \Context::get('displayDatas');

        $entries = [
            'member_unique_srl' => CommonUtil::encodeId($memberInfo['member_srl']),
            'joined_at' => strtotime($memberInfo['regdate']),
            'allow_mailing' => ('Y' == $memberInfo['allow_mailing']),
            'allow_message' => ('Y' == $memberInfo['allow_message']),
        ];

        foreach ($displayDatas as $item) {
            $entries[$item->name] = ($item->required || $item->mustRequired) ? $item->value : '';
            if ('email_address' == $item->name) {
                $entries[$item->name] = CommonUtil::obfuscateEmail($item->value);
            }
        }

        return new JsonResponse(
            $entries,
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function getSitemap(RequestInterface $request, ResponseInterface $response)
    {
        return new JsonResponse(
            XpressSupport::getSitemap(),
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function getArticles(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $memberSrl = XpressSupport::checkAuthPermission($request, 'read');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        $queries = $request->getQueryParams();
        $mId = array_key_exists('board', $queries) ? $queries['board'] : '';
        $articleSrl = array_key_exists('article_srl', $queries) ? $queries['article_srl'] : '';

        if ($mId) {
            $moduleInfo = XpressSupport::getModuleInfoByMId($mId);
        } elseif ($articleSrl) {
            $moduleInfo = XpressSupport::getModuleInfoByArticleSrl($articleSrl);
        } else {
            $moduleInfo = null;
        }

        if (!$moduleInfo) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'The resource is gone',
            ], 404);
        } elseif ('board' != $moduleInfo->module) {
            return new JsonResponse([
                'error' => 'module_not_exist',
                'message' => 'board module not exist',
            ], 406);
        }

        if ($articleSrl) {
            \Context::set('document_srl', $articleSrl);
        }

        try {
            XpressSupport::executeModule($moduleInfo, 'dispBoardContent');
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        $page = \Context::get('page');
        $grant = \Context::get('grant');

        /**
         * @var \documentItem $objDocument;
         */
        $objDocument = \Context::get('oDocument');

        if ($articleSrl && $objDocument && $objDocument->isExists()) {
            $entries = XpressUtil::convertSingleDocumentItem($objDocument, $grant);
        } elseif ($articleSrl && (!$objDocument || !$objDocument->isExists())) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'The resource is gone',
            ], 404);
        } else {
            $noticeList = \Context::get('notice_list');
            $documentList = \Context::get('document_list');
            /**
             * @var \PageHandler $pageNavigation
             */
            $pageNavigation = \Context::get('page_navigation');

            if (!$noticeList) $noticeList = [];
            if (!$documentList) $documentList = [];
            if ($page < 2) {
                $entries = array_merge($noticeList, $documentList);
            } else {
                $entries = $documentList;
            }

            $entries = XpressUtil::convertDocumentItems($pageNavigation, $entries, $grant);
        }

        // for stream access
        if (!$articleSrl
            || ($articleSrl && ($objDocument && $objDocument->isExists() && $objDocument->getMemberSrl() != $memberSrl))) {
            try {
                XpressSupport::checkAuthPermission($request, 'stream');
            } catch (AccessDeniedException $exception) {
                return new JsonResponse([
                    'error' => 'permission_denied',
                    'message' => 'scope invalid',
                ], 401);
            }
        }

        return new JsonResponse(
            $entries,
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function postArticle(RequestInterface $request, ResponseInterface $response)
    {
        try {
            XpressSupport::checkAuthPermission($request, 'write');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        $queries = $request->getQueryParams();
        $mId = array_key_exists('board', $queries) ? $queries['board'] : '';

        if ($mId) {
            $moduleInfo = XpressSupport::getModuleInfoByMId($mId);
        } else {
            $moduleInfo = null;
        }

        if (!$moduleInfo) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'The resource is gone',
            ], 404);
        }

        try {
            XpressSupport::temporaryPassCsrf();
            XpressUtil::dispatchPostDocumentRequest($request);
            $status = XpressSupport::executeModule($moduleInfo, 'procBoardInsertDocument', 'controller');
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        /**
         * @var \stdClass $grant
         */
        $grant = \Context::get('grant');
        if (!$grant->write_document) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'write permission denied',
            ], 401);
        }

        return new JsonResponse(
            ['status' => $status],
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function updateArticle(RequestInterface $request, ResponseInterface $response)
    {
        try {
            XpressSupport::checkAuthPermission($request, 'write');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        $queries = $request->getQueryParams();
        $articleSrl = array_key_exists('article_srl', $queries) ? $queries['article_srl'] : '';

        if ($articleSrl) {
            $moduleInfo = XpressSupport::getModuleInfoByArticleSrl($articleSrl);
        } else {
            $moduleInfo = null;
        }

        if (!$moduleInfo) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'The resource is gone',
            ], 404);
        }

        try {
            XpressSupport::temporaryPassCsrf();
            XpressUtil::dispatchPostDocumentRequest($request);
            \Context::set('document_srl', $articleSrl, 1);
            $status = XpressSupport::executeModule($moduleInfo, 'procBoardInsertDocument', 'controller');
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        /**
         * @var \stdClass $grant
         */
        $grant = \Context::get('grant');
        if (!$grant->write_document) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'write permission denied',
            ], 401);
        }

        return new JsonResponse(
            ['status' => $status],
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function deleteArticle(RequestInterface $request, ResponseInterface $response)
    {
        try {
            XpressSupport::checkAuthPermission($request, 'write');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        $queries = $request->getQueryParams();
        $articleSrl = array_key_exists('article_srl', $queries) ? $queries['article_srl'] : '';

        if ($articleSrl) {
            $moduleInfo = XpressSupport::getModuleInfoByArticleSrl($articleSrl);
        } else {
            $moduleInfo = null;
        }

        if (!$moduleInfo) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'The resource is gone',
            ], 404);
        }

        try {
            XpressSupport::temporaryPassCsrf();
            \Context::set('document_srl', $articleSrl, 1);
            $status = XpressSupport::executeModule($moduleInfo, 'procBoardDeleteDocument', 'controller');
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        return new JsonResponse(
            ['status' => $status],
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function getComments(RequestInterface $request, ResponseInterface $response)
    {
        try {
            XpressSupport::checkAuthPermission($request, 'stream');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        $queries = $request->getQueryParams();
        $mId = array_key_exists('board', $queries) ? $queries['board'] : '';
        $articleSrl = array_key_exists('article_srl', $queries) ? $queries['article_srl'] : '';
        $page = array_key_exists('page', $queries) ? $queries['page'] : '';

        if ($mId) {
            $moduleInfo = XpressSupport::getModuleInfoByMId($mId);
        } elseif ($articleSrl) {
            $moduleInfo = XpressSupport::getModuleInfoByArticleSrl($articleSrl);
        } else {
            $moduleInfo = null;
        }

        if (!$moduleInfo || !$articleSrl) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'The resource is gone',
            ], 404);
        }

        if (!$page || !is_numeric($page)) {
            $page = 1;
        }

        /**
         * @var \documentItem $objDocument;
         */
        \Context::set('document_srl', $articleSrl);
        \Context::set('cpage', $page);

        try {
            XpressSupport::executeModule($moduleInfo, 'dispBoardContent');
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        $objDocument = \Context::get('oDocument');
        if (!$objDocument || !$objDocument->isExists()) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'The resource is gone',
            ], 404);
        } else {
            $entries = XpressUtil::convertDocumentComments($objDocument);
        }

        return new JsonResponse(
            $entries,
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function postComment(RequestInterface $request, ResponseInterface $response)
    {
        try {
            XpressSupport::checkAuthPermission($request, 'write');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        $queries = $request->getQueryParams();
        $articleSrl = array_key_exists('article_srl', $queries) ? $queries['article_srl'] : '';
        $parentSrl = array_key_exists('parent_srl', $queries) ? $queries['parent_srl'] : '';

        if ($articleSrl) {
            $moduleInfo = XpressSupport::getModuleInfoByArticleSrl($articleSrl);
        } else {
            $moduleInfo = null;
        }

        if (!$moduleInfo) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'The resource is gone',
            ], 404);
        }

        try {
            XpressSupport::temporaryPassCsrf();
            XpressUtil::dispatchPostCommentRequest($request);
            \Context::set('document_srl', $articleSrl, 1);
            \Context::set('comment_srl', 0, 1);
            if ($parentSrl) \Context::set('parent_srl', $parentSrl, 1);
            $status = XpressSupport::executeModule($moduleInfo, 'procBoardInsertComment', 'controller');
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        /**
         * @var \stdClass $grant
         */
        $grant = \Context::get('grant');
        if (!$grant->write_document) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'write permission denied',
            ], 401);
        }

        return new JsonResponse(
            ['status' => $status],
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function updateComment(RequestInterface $request, ResponseInterface $response)
    {
        try {
            XpressSupport::checkAuthPermission($request, 'write');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        $queries = $request->getQueryParams();
        $articleSrl = array_key_exists('article_srl', $queries) ? $queries['article_srl'] : '';
        $commentSrl = array_key_exists('comment_srl', $queries) ? $queries['comment_srl'] : '';

        if ($articleSrl) {
            $moduleInfo = XpressSupport::getModuleInfoByArticleSrl($articleSrl);
        } else {
            $moduleInfo = null;
        }

        if (!$moduleInfo || !$commentSrl) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'The resource is gone',
            ], 404);
        }

        try {
            XpressSupport::temporaryPassCsrf();
            XpressUtil::dispatchPostCommentRequest($request);
            \Context::set('document_srl', $articleSrl, 1);
            \Context::set('comment_srl', $commentSrl, 1);
            $status = XpressSupport::executeModule($moduleInfo, 'procBoardInsertComment', 'controller');
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        /**
         * @var \stdClass $grant
         */
        $grant = \Context::get('grant');
        if (!$grant->write_document) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'write permission denied',
            ], 401);
        }

        return new JsonResponse(
            ['status' => $status],
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function deleteComment(RequestInterface $request, ResponseInterface $response)
    {
        try {
            XpressSupport::checkAuthPermission($request, 'write');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        $queries = $request->getQueryParams();
        $articleSrl = array_key_exists('article_srl', $queries) ? $queries['article_srl'] : '';
        $commentSrl = array_key_exists('comment_srl', $queries) ? $queries['comment_srl'] : '';

        if ($articleSrl) {
            $moduleInfo = XpressSupport::getModuleInfoByArticleSrl($articleSrl);
        } else {
            $moduleInfo = null;
        }

        if (!$moduleInfo || !$commentSrl) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'The resource is gone',
            ], 404);
        }

        try {
            XpressSupport::temporaryPassCsrf();
            \Context::set('comment_srl', $commentSrl, 1);
            $status = XpressSupport::executeModule($moduleInfo, 'procBoardDeleteComment', 'controller');
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        return new JsonResponse(
            ['status' => $status],
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function getScraps(RequestInterface $request, ResponseInterface $response)
    {
        try {
            XpressSupport::checkAuthPermission($request, 'read');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        try {
            XpressSupport::temporarySessionCheck();
            XpressSupport::executeModuleByName('member', 'dispMemberScrappedDocument');
            XpressSupport::temporarySessionUncheck();
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        /**
         * @var \PageHandler $pageNav
         */
        $pageNav = \Context::get('page_navigation');
        /**
         * @var \documentItem[] $documentList
         */
        $documentList = \Context::get('document_list');

        return new JsonResponse(
            XpressUtil::convertScrapDocumentList($pageNav, $documentList),
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function postScrap(RequestInterface $request, ResponseInterface $response)
    {
        try {
            XpressSupport::checkAuthPermission($request, 'write');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        $queries = $request->getQueryParams();
        $articleSrl = array_key_exists('article_srl', $queries) ? $queries['article_srl'] : '';

        if ($articleSrl) {
            $moduleInfo = XpressSupport::getModuleInfoByArticleSrl($articleSrl);
        } else {
            $moduleInfo = null;
        }

        if (!$moduleInfo || !$articleSrl) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'The resource is gone',
            ], 404);
        }

        try {
            \Context::set('document_srl', $articleSrl);
            $status = XpressSupport::executeModuleByName('member', 'procMemberScrapDocument', 'controller');
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        return new JsonResponse(
            ['status' => $status],
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function deleteScrap(RequestInterface $request, ResponseInterface $response)
    {
        try {
            XpressSupport::checkAuthPermission($request, 'write');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        $queries = $request->getQueryParams();
        $articleSrl = array_key_exists('article_srl', $queries) ? $queries['article_srl'] : '';

        if ($articleSrl) {
            $moduleInfo = XpressSupport::getModuleInfoByArticleSrl($articleSrl);
        } else {
            $moduleInfo = null;
        }

        if (!$moduleInfo || !$articleSrl) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'The resource is gone',
            ], 404);
        }

        try {
            \Context::set('document_srl', $articleSrl);
            $status = XpressSupport::executeModuleByName('member', 'procMemberDeleteScrap', 'controller');
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        return new JsonResponse(
            ['status' => $status],
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function getMyArticles(RequestInterface $request, ResponseInterface $response)
    {
        try {
            XpressSupport::checkAuthPermission($request, 'read');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        try {
            XpressSupport::temporarySessionCheck();
            XpressSupport::executeModuleByName('member', 'dispMemberOwnDocument');
            XpressSupport::temporarySessionUncheck();
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        /**
         * @var \PageHandler $pageNavigation
         */
        $pageNavigation = \Context::get('page_navigation');
        $entries = \Context::get('document_list');
        if (!$entries || !is_array($entries)) {
            $entries = [];
        }

        return new JsonResponse(
            XpressUtil::convertDocumentItems($pageNavigation, $entries),
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function getMyComments(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $memberSrl = XpressSupport::checkAuthPermission($request, 'read');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        $queries = $request->getQueryParams();
        $currentPage = array_key_exists('page', $queries) ? $queries['page'] : 1;
        if (!$currentPage || !is_numeric($currentPage) || 1 > $currentPage) {
            $currentPage = 1;
        }

        $entries = XpressSupport::getCommentListByMemberSrl($memberSrl, $currentPage);
        if ($entries) {
            $entries = XpressUtil::convertMyComments($entries);
        }

        return new JsonResponse(
            $entries,
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function getFriends(RequestInterface $request, ResponseInterface $response)
    {
        try {
            XpressSupport::checkAuthPermission($request, 'message');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        try {
            XpressSupport::executeModuleByName('communication', 'dispCommunicationFriend');
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        /**
         * @var \PageHandler $pageNavigation
         */
        $pageNavigation = \Context::get('page_navigation');

        /**
         * @var \stdClass[] $entries
         */
        $entries = \Context::get('friend_list');

        return new JsonResponse(
            XpressUtil::convertFriends($pageNavigation, $entries),
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function postFriend(RequestInterface $request, ResponseInterface $response)
    {
        try {
            XpressSupport::checkAuthPermission($request, 'message');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        $queries = $request->getQueryParams();
        if (!array_key_exists('member_unique_srl', $queries)) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'The resource is gone',
            ], 404);
        } else {
            $targetSrl = CommonUtil::decodeId($queries['member_unique_srl']);
            if (!$targetSrl || !is_numeric($targetSrl)) {
                return new JsonResponse([
                    'error' => 'not_found',
                    'message' => 'The resource is gone',
                ], 404);
            }
        }

        try {
            \Context::set('target_srl', $targetSrl);
            \Context::setRequestMethod('JSON');
            $status = XpressSupport::executeModuleByName('communication', 'procCommunicationAddFriend', 'controller');
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        return new JsonResponse(
            ['status' => $status],
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function deleteFriend(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $memberSrl = XpressSupport::checkAuthPermission($request, 'message');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        $queries = $request->getQueryParams();
        if (!array_key_exists('member_unique_srl', $queries)) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'The resource is gone',
            ], 404);
        } else {
            $targetSrl = CommonUtil::decodeId($queries['member_unique_srl']);
            if (!$targetSrl || !is_numeric($targetSrl)) {
                return new JsonResponse([
                    'error' => 'not_found',
                    'message' => 'The resource is gone',
                ], 404);
            }
        }

        $targets = [];

        $args = new \stdClass();
        $args->member_srl = $memberSrl;
        $args->target_srl = $targetSrl;
        $output = executeQueryArray('devcenter.findFriendSrlByMemberSrlAndTargetSrl', $args);
        if ($output->data) {
            foreach ($output->data as $entry) {
                $targets[] = $entry->friend_srl;
            }
        }

        try {
            \Context::set('friend_srl_list', $targets);
            $status = XpressSupport::executeModuleByName('communication', 'procCommunicationDeleteFriend', 'controller');
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        return new JsonResponse(
            ['status' => $status],
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function getMessages(RequestInterface $request, ResponseInterface $response)
    {
        try {
            XpressSupport::checkAuthPermission($request, 'message');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        $queries = $request->getQueryParams();

        // temporary
        $loggedInfo = \Context::get('logged_info');
        $loggedInfo->menu_list = ['dispCommunicationMessages' => '__DUMMY__'];
        \Context::set('logged_info', $loggedInfo);

        // hooking
        \Context::set('message_type', null);
        if (array_key_exists('category', $queries) && 'send' == $queries['category']) {
            \Context::set('message_type', 'S');
        } elseif (array_key_exists('category', $queries) && 'store' == $queries['category']) {
            \Context::set('message_type', 'T');
        }

        try {
            XpressSupport::executeModuleByName('communication', 'dispCommunicationMessages');
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        if (!array_key_exists('message_srl', $queries)) {
            /**
             * @var \PageHandler $pageNavigation
             */
            $pageNavigation = \Context::get('page_navigation');

            /**
             * @var \stdClass[] $entries
             */
            $entries = \Context::get('message_list');

            return new JsonResponse(
                XpressUtil::convertMessages($pageNavigation, $entries),
                200,
                $response->getHeaders()
            );
        } else {
            $message = \Context::get('message');
            if (!$message) {
                return new JsonResponse([
                    'error' => 'not_found',
                    'message' => 'The resource is gone',
                ], 404);
            } else {
                return new JsonResponse(
                    XpressUtil::convertMessage($message),
                    200,
                    $response->getHeaders()
                );
            }
        }
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function postMessage(RequestInterface $request, ResponseInterface $response)
    {
        try {
            XpressSupport::checkAuthPermission($request, 'message');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        $queries = $request->getQueryParams();
        if (!array_key_exists('member_unique_srl', $queries)) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'The resource is gone',
            ], 404);
        } else {
            $targetSrl = CommonUtil::decodeId($queries['member_unique_srl']);
            if (!$targetSrl || !is_numeric($targetSrl)) {
                return new JsonResponse([
                    'error' => 'not_found',
                    'message' => 'The resource is gone',
                ], 404);
            }
        }

        try {
            \Context::set('receiver_srl', $targetSrl);
            \Context::setRequestMethod('JSON');
            XpressUtil::dispatchPostMessageRequest($request);
            $status = XpressSupport::executeModuleByName('communication', 'procCommunicationSendMessage', 'controller');
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        return new JsonResponse(
            ['status' => $status],
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function deleteMessage(RequestInterface $request, ResponseInterface $response)
    {
        try {
            XpressSupport::checkAuthPermission($request, 'message');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        $queries = $request->getQueryParams();
        if (!array_key_exists('message_srl', $queries)) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'The resource is gone',
            ], 404);
        }

        try {
            $status = XpressSupport::executeModuleByName('communication', 'procCommunicationDeleteMessage', 'controller');
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        return new JsonResponse(
            ['status' => $status],
            200,
            $response->getHeaders()
        );
    }

    /**
     * @param RequestInterface|ServerRequest $request
     * @param ResponseInterface $response
     * @return JsonResponse
     */
    public static function getLoginHistories(RequestInterface $request, ResponseInterface $response)
    {
        if (!getModule('loginlog', 'class')) {
            return new JsonResponse([
                'error' => 'module_not_exist',
                'message' => 'login log module not exist',
            ], 406);
        }

        try {
            XpressSupport::checkAuthPermission($request, 'read');
        } catch (AccessDeniedException $exception) {
            return new JsonResponse([
                'error' => 'permission_denied',
                'message' => 'scope invalid',
            ], 401);
        }

        try {
            XpressSupport::executeModuleByName('loginlog', 'dispLoginlogHistories');
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => 'internal_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        /**
         * @var \PageHandler $pageNavigation
         */
        $pageNavigation = \Context::get('page_navigation');

        /**
         * @var \stdClass[] $entries
         */
        $entries = \Context::get('histories');

        return new JsonResponse(
            XpressUtil::convertLoginHistories($pageNavigation, $entries),
            200,
            $response->getHeaders()
        );
    }
}