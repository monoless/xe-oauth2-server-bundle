<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-08
 * Time: 오후 12:01
 */

namespace Monoless\Xe\OAuth2\Server\Entities;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\ClientTrait;

class ClientEntity implements ClientEntityInterface
{
    /**
     * @var integer
     */
    private $memberSrl;

    /**
     * @var string
     */
    private $thumbnail;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string
     */
    private $websiteUrl;

    /**
     * @var string[]
     */
    private $scope;

    /**
     * @var string
     */
    private $clientSecret;

    /**
     * @var string
     */
    private $createdAt;

    /**
     * @var string
     */
    private $updatedAt;

    use EntityTrait, ClientTrait;

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setRedirectUri($uri)
    {
        $this->redirectUri = $uri;
    }

    /**
     * @return int
     */
    public function getMemberSrl()
    {
        return $this->memberSrl;
    }

    /**
     * @param int $memberSrl
     */
    public function setMemberSrl($memberSrl)
    {
        $this->memberSrl = $memberSrl;
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
    public function getClientSecret()
    {
        return $this->clientSecret;
    }

    /**
     * @param string $clientSecret
     */
    public function setClientSecret($clientSecret)
    {
        $this->clientSecret = $clientSecret;
    }

    /**
     * @return string
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param string $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return string
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param string $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }
}