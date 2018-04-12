<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-09
 * Time: 오후 1:39
 */

namespace Monoless\Xe\OAuth2\Server\Entities;


class GrantAppExtendEntity extends GrantAppEntity
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $thumbnail;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string[]
     */
    private $scope;

    /**
     * @var string
     */
    private $websiteUrl;

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getThumbnail()
    {
        return $this->thumbnail;
    }

    /**
     * @param string $thumbnail
     */
    public function setThumbnail($thumbnail)
    {
        $this->thumbnail = $thumbnail;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return string[]
     */
    public function getScope()
    {
        return $this->scope;
    }

    /**
     * @param string[] $scope
     */
    public function setScope($scope)
    {
        $this->scope = $scope;
    }

    /**
     * @return string
     */
    public function getWebsiteUrl()
    {
        return $this->websiteUrl;
    }

    /**
     * @param string $websiteUrl
     */
    public function setWebsiteUrl($websiteUrl)
    {
        $this->websiteUrl = $websiteUrl;
    }
}