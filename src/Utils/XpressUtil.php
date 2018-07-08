<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-20
 * Time: 오후 1:56
 */

namespace Monoless\Xe\OAuth2\Server\Utils;


use Monoless\Xe\OAuth2\Server\Supports\XpressSupport;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Psr7\ServerRequest;

class XpressUtil
{
    /**
     * @param string $uniqueAppSrl
     * @param integer $memberSrl
     * @return string
     */
    public static function getThumbnailFolderPath($uniqueAppSrl, $memberSrl)
    {
        return sprintf(
            './files/member_extra_info/profile_image/%s%s/',
            getNumberingPath($memberSrl),
            sha1($uniqueAppSrl)
        );
    }

    public static function getThumbnailFilePath($path)
    {
        $random = new \Password();

        $result = implode([
            $path,
            $random->createSecureSalt(32, 'hex'),
            '.jpg'
        ]);

        while (file_exists($result)) {
            $result = implode([
                $path,
                $random->createSecureSalt(32, 'hex'),
                '.jpg'
            ]);
        }

        return $result;
    }

    /**
     * @param integer $memberSrl
     * @return string
     */
    public static function getSecretKey($memberSrl)
    {
        return hash('sha512', implode('|', [
            $memberSrl,
            time(),
            mt_rand()
        ]));
    }

    /**
     * @param integer $memberSrl
     * @return string
     */
    public static function getUniqueSrl($memberSrl)
    {
        static $max = 60466175;
        return strtoupper(sprintf(
            "%05s-%05s-%05s-%05s-%05s",
            base_convert($memberSrl, 10, 36),
            base_convert(date('Ymd'), 10, 36),
            base_convert(random_int(0, $max), 10, 36),
            base_convert(random_int(0, $max), 10, 36),
            base_convert(random_int(0, $max), 10, 36)
        ));
    }

    /**
     * @param $items
     * @return array|null
     */
    public static function convertMenuItem($items)
    {
        $output = [];
        if (!count($items) || !$items) {
            $output = null;
        }

        foreach ($items as $item) {
            $module = XpressSupport::getModuleInfoByMId($item['url']);

            $entry = [
                'text' => $item['text'],
                'url' => $item['url'],
                'is_shortcut' => $item['is_shortcut'],
                'module' => $module ? $module->module : '',
                'browser_title' => $module ? $module->browser_title : '',
            ];

            if (array_key_exists('list', $item) && 0 < count($item['list'])) {
                $entry['children'] = self::convertMenuItem($item['list']);
            }

            $output[] = $entry;
        }

        return $output;
    }

    /**
     * @param \PageHandler $pageNavigation
     * @param array $documentItems
     * @param \stdClass|null $grant
     * @return array
     */
    public static function convertDocumentItems(\PageHandler $pageNavigation, array $documentItems, $grant = null)
    {
        $count = 0;
        $currentPage = 1;
        $totalPage = 1;
        if ($pageNavigation instanceof \PageHandler) {
            $count = $pageNavigation->total_count;
            $currentPage = $pageNavigation->cur_page;
            $totalPage = $pageNavigation->total_page;
        }

        $output = [];
        foreach ($documentItems as $item) {
            if (!($item instanceof \documentItem)) {
                continue;
            }

            $output[] = [
                'article_srl' => $item->document_srl,
                'title' => $item->getTitle(),
                'nick_name' => $item->getNickName(),
                'member_unique_srl' => CommonUtil::encodeId($item->getMemberSrl()),
                'member_profile_image' => $item->getProfileImage(),
                'member_signature' => $item->getSignature(),
                'comment_count' => $item->getCommentCount(),
                'readed_count' => $item->get('readed_count'),
                'voted_count' => $item->get('voted_count'),
                'is_notice' => $item->isNotice(),
                'register_at' => $item->getRegdateTime(),
                'updated_at' => $item->getUpdateTime(),
            ];
        }

        return [
            'count' => $count,
            'current_page' => $currentPage,
            'total_page' => $totalPage,
            'grant' => $grant,
            'entries' => $output
        ];
    }

    /**
     * @param \documentItem $item
     * @param \stdClass $grant
     * @return array
     */
    public static function convertSingleDocumentItem(\documentItem $item, $grant)
    {
        if ($item->isSecret() && !$item->isGranted()) {
            $content = null;
            $tags = null;
        } else {
            $content = $item->getContent(
                false,
                false,
                true,
                true,
                false);

            $tags = $item->get('tag_list');
        }

        return [
            'article_srl' => $item->document_srl,
            'title' => $item->getTitle(),
            'nick_name' => $item->getNickName(),
            'member_unique_srl' => CommonUtil::encodeId($item->getMemberSrl()),
            'member_profile_image' => $item->getProfileImage(),
            'member_signature' => $item->getSignature(),
            'content' => $content,
            'tags' => $tags,
            'comment_count' => $item->getCommentCount(),
            'readed_count' => $item->get('readed_count'),
            'voted_count' => $item->get('voted_count'),
            'is_notice' => $item->isNotice(),
            'allow_comment' => ($item->allowComment() && $grant->write_comment),
            'register_at' => $item->getRegdateTime(),
            'updated_at' => $item->getUpdateTime(),
        ];
    }

    /**
     * @param \documentItem $item
     * @return array
     */
    public static function convertDocumentComments(\documentItem $item)
    {
        $count = $item->getCommentCount();
        $currentPage = 1;
        $totalPage = 1;

        if ($item->comment_page_navigation) {
            $currentPage = $item->comment_page_navigation->cur_page;
            $totalPage = $item->comment_page_navigation->total_page;
        }

        $comments = $item->getComments();

        return [
            'count' => $count,
            'current_page' => $currentPage,
            'total_page' => $totalPage,
            'entries' => $comments ? self::convertComments($item->getComments()) : null
        ];
    }

    /**
     * @param \commentItem[] $comments
     * @return array
     */
    public static function convertComments(array $comments)
    {
        $entries = [];
        foreach ($comments as $comment) {
            if (!$comment instanceof \commentItem) {
                continue;
            }

            $content = $comment->isAccessible() ?
                $comment->getContent(false, false, true) :
                null;

            $entries[] = [
                'comment_srl' => $comment->comment_srl,
                'article_srl' => $comment->get('document_srl'),
                'nick_name' => $comment->getNickName(),
                'member_unique_srl' => $comment->getMemberSrl() ? CommonUtil::encodeId($comment->getMemberSrl()) : null,
                'member_profile_image' => $comment->getProfileImage(),
                'member_signature' => $comment->getSignature(),
                'content' => $content,
                'depth' => $comment->get('depth'),
                'voted_count' => $comment->get('voted_count'),
                'register_at' => $comment->getRegdateTime(),
                'updated_at' => $comment->getUpdateTime(),
            ];
        }

        return $entries;
    }

    /**
     * @param \BaseObject $output
     * @return array
     */
    public static function convertMyComments(\BaseObject $output)
    {
        $commentLists = $output->data;
        if (!$commentLists) {
            $commentLists = [];
        } elseif (!is_array($commentLists)) {
            $commentLists = [$commentLists];
        }

        $accessible = [];
        $entries = [];
        foreach ($commentLists as $commentList) {
            $item = new \commentItem();
            $item->setAttribute($commentList);
            if ($item->isGranted()) {
                $accessible[$commentList->comment_srl] = true;
            }

            if (0 < $commentList->parent_srl && 'Y' == $commentList->is_secret && !$item->isAccessible()
                && true === $accessible[$commentList->parent_srl]) {
                $item->setAccessible();
            }

            $entries[$commentList->comment_srl] = $item;
        }

        return [
            'count' => $count = $output->page_navigation->total_count,
            'current_page' => $output->page_navigation->cur_page,
            'total_page' => $output->page_navigation->total_page,
            'entries' => self::convertComments($entries)
        ];
    }

    /**
     * @param \PageHandler $pageNavigation
     * @param \stdClass[] $documentItems
     * @return array
     */
    public static function convertScrapDocumentList(\PageHandler $pageNavigation, array $documentItems)
    {
        $count = 0;
        $currentPage = 1;
        $totalPage = 1;
        if ($pageNavigation instanceof \PageHandler) {
            $count = $pageNavigation->total_count;
            $currentPage = $pageNavigation->cur_page;
            $totalPage = $pageNavigation->total_page;
        }

        $output = [];
        foreach ($documentItems as $item) {
            if (!($item instanceof \stdClass)) {
                continue;
            }

            $output[] = [
                'article_srl' => $item->document_srl,
                'title' => $item->title,
                'nick_name' => $item->nick_name,
                'member_unique_srl' => CommonUtil::encodeId($item->member_srl),
                'member_profile_image' => null,
                'member_signature' => null,
                'comment_count' => null,
                'readed_count' => null,
                'voted_count' => null,
                'is_notice' => null,
                'register_at' => strtotime($item->regdate),
                'updated_at' => null,
            ];
        }

        return [
            'count' => $count,
            'current_page' => $currentPage,
            'total_page' => $totalPage,
            'entries' => $output
        ];
    }

    /**
     * @param \PageHandler $pageNavigation
     * @param array $friends
     * @return array
     */
    public static function convertFriends(\PageHandler $pageNavigation, array $friends)
    {
        $entries = [];

        foreach ($friends as $friend) {
            $entries[] = [
                'member_unique_srl' => CommonUtil::encodeId($friend->target_srl),
                'nick_name' => $friend->nick_name,
                'group_srl' => $friend->friend_group_srl,
                'group_title' => $friend->group_title,
                'register_at' => strtotime($friend->regdate),
            ];
        }

        return [
            'count' => $pageNavigation->total_count,
            'current_page' => $pageNavigation->cur_page,
            'total_page' => $pageNavigation->total_page,
            'entries' => $entries
        ];
    }

    /**
     * @param \PageHandler $pageNavigation
     * @param array $messages
     * @return array
     */
    public static function convertMessages(\PageHandler $pageNavigation, array $messages)
    {
        $entries = [];

        foreach ($messages as $message) {
            $entries[] = [
                'message_srl' => $message->message_srl,
                'title' => $message->title,
                'member_unique_srl' => CommonUtil::encodeId($message->member_srl),
                'nick_name' => $message->nick_name,
                'is_readed' => ('Y' == $message->readed),
                'register_at' => strtotime($message->regdate),
                'readed_at' => strtotime($message->readed_date)
            ];
        }

        return [
            'count' => $pageNavigation->total_count,
            'current_page' => $pageNavigation->cur_page,
            'total_page' => $pageNavigation->total_page,
            'entries' => $entries
        ];
    }

    /**
     * @param \stdClass $message
     * @return array
     */
    public static function convertMessage(\stdClass $message)
    {
        return [
            'message_srl' => $message->message_srl,
            'title' => $message->title,
            'member_unique_srl' => CommonUtil::encodeId($message->member_srl),
            'nick_name' => $message->nick_name,
            'content' => $message->content,
            'is_readed' => ('Y' == $message->readed),
            'register_at' => strtotime($message->regdate),
        ];
    }

    /**
     * @param \PageHandler $pageNavigation
     * @param array $histories
     * @return array
     */
    public static function convertLoginHistories(\PageHandler $pageNavigation, array $histories)
    {
        $entries = [];

        foreach ($histories as $history) {
            $entries[] = [
                'log_srl' => $history->log_srl,
                'address' => $history->ipaddress,
                'is_success' => 'Y' == $history->is_succeed,
                'platform' => $history->platform,
                'browser' => $history->browser,
                'register_at' => strtotime($history->regdate)
            ];
        }

        return [
            'count' => $pageNavigation->total_count,
            'current_page' => $pageNavigation->cur_page,
            'total_page' => $pageNavigation->total_page,
            'entries' => $entries
        ];
    }

    /**
     * @param RequestInterface|ServerRequest $request
     */
    public static function dispatchPostDocumentRequest(RequestInterface $request)
    {
        $params = $request->getParsedBody();
        if ('PUT' == $request->getMethod()) {
            parse_str(file_get_contents('php://input'), $params);
        }

        $args = [
            'title' => array_key_exists('title', $params) ? $params['title'] : '',
            'content' => array_key_exists('content', $params) ? $params['content'] : '',
            'comment_status' => array_key_exists('allow_comment', $params) ?
                ($params['allow_comment'] ? 'ALLOW' : 'DENY') : 'DENY',
            'allow_trackback' => array_key_exists('allow_trackback', $params) ?
                ($params['allow_trackback'] ? 'Y' : 'N') : 'N',
            'notify_message' => array_key_exists('allow_notify', $params) ?
                ($params['allow_notify'] ? 'Y' : 'N') : 'N',
            'status' => 'PUBLIC', // always public
            'tags' => array_key_exists('tags', $params) ? $params['tags'] : '',
        ];

        // ignore all post
        foreach (array_keys($params) as $key) {
            unset($_POST[$key]);
            unset($GLOBALS[$key]);
            \Context::set($key, '');
        }

        foreach ($args as $key => $value) {
            $_POST[$key] = $value;
            $GLOBALS[$key] = $value;
            \Context::set($key, $value, 1);
        }
    }

    /**
     * @param RequestInterface|ServerRequest $request
     */
    public static function dispatchPostCommentRequest(RequestInterface $request)
    {
        $params = $request->getParsedBody();
        if ('PUT' == $request->getMethod()) {
            parse_str(file_get_contents('php://input'), $params);
        }

        $args = [
            'content' => array_key_exists('content', $params) ? $params['content'] : '',
        ];

        // ignore all post
        foreach (array_keys($params) as $key) {
            unset($_POST[$key]);
            unset($GLOBALS[$key]);
            \Context::set($key, '');
        }

        foreach ($args as $key => $value) {
            $_POST[$key] = $value;
            $GLOBALS[$key] = $value;
            \Context::set($key, $value, 1);
        }
    }

    /**
     * @param RequestInterface|ServerRequest $request
     */
    public static function dispatchPostMessageRequest(RequestInterface $request)
    {
        $params = $request->getParsedBody();
        if ('PUT' == $request->getMethod()) {
            parse_str(file_get_contents('php://input'), $params);
        }

        $args = [
            'title' => array_key_exists('title', $params) ? $params['title'] : '',
            'content' => array_key_exists('content', $params) ? $params['content'] : '',
            'send_mail' => array_key_exists('allow_mail', $params) ?
                $params['allow_mail'] ? 'Y' : 'N' : 'N'
        ];

        // ignore all post
        foreach (array_keys($params) as $key) {
            unset($_POST[$key]);
            unset($GLOBALS[$key]);
            \Context::set($key, '');
        }

        foreach ($args as $key => $value) {
            $_POST[$key] = $value;
            $GLOBALS[$key] = $value;
            \Context::set($key, $value, 1);
        }
    }
}