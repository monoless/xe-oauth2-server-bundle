<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-09
 * Time: 오후 1:39
 */

namespace Monoless\Xe\OAuth2\Server\Entities;


class GrantAppEntity
{
    /**
     * @var integer
     */
    private $grantSrl;

    /**
     * @var string
     */
    private $uniqueAppSrl;

    /**
     * @var integer
     */
    private $memberSrl;

    /**
     * @var string
     */
    private $revoked;

    /**
     * @var string
     */
    private $createdAt;

    /**
     * @var string
     */
    private $updatedAt;

    /**
     * @var string
     */
    private $expiredAt;

    /**
     * @return int
     */
    public function getGrantSrl()
    {
        return $this->grantSrl;
    }

    /**
     * @param int $grantSrl
     */
    public function setGrantSrl($grantSrl)
    {
        $this->grantSrl = $grantSrl;
    }

    /**
     * @return string
     */
    public function getUniqueAppSrl()
    {
        return $this->uniqueAppSrl;
    }

    /**
     * @param string $uniqueAppSrl
     */
    public function setUniqueAppSrl($uniqueAppSrl)
    {
        $this->uniqueAppSrl = $uniqueAppSrl;
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
     * @return bool
     */
    public function isRevoked()
    {
        return 'y' == $this->revoked;
    }

    /**
     * @param bool $revoked
     */
    public function setRevoked($revoked)
    {
        $this->revoked = $revoked ? 'y' : 'n';
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

    /**
     * @return string
     */
    public function getExpiredAt()
    {
        return $this->expiredAt;
    }

    /**
     * @param string $expiredAt
     */
    public function setExpiredAt($expiredAt)
    {
        $this->expiredAt = $expiredAt;
    }
}