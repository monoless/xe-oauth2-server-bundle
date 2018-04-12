<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-09
 * Time: 오후 1:37
 */

namespace Monoless\Xe\OAuth2\Server\Entities;


class PageContainerEntity
{
    /**
     * @var \PageHandler
     */
    private $pageHandler;

    /**
     * @var array
     */
    private $entries;

    /**
     * @return \PageHandler
     */
    public function getPageHandler()
    {
        return $this->pageHandler;
    }

    /**
     * @param \PageHandler $pageHandler
     */
    public function setPageHandler($pageHandler)
    {
        $this->pageHandler = $pageHandler;
    }

    /**
     * @return array
     */
    public function getEntries()
    {
        return $this->entries;
    }

    /**
     * @param array $entries
     */
    public function setEntries($entries)
    {
        $this->entries = $entries;
    }
}