<?php

namespace Gustavus\Concert\Setup\GeneratedEntities;

use Doctrine\ORM\Mapping as ORM;

/**
 * Sites
 *
 * @Table(name="sites")
 * @Entity
 */
class Sites
{
    /**
     * @var integer
     *
     * @Column(name="id", type="integer", nullable=false)
     * @Id
     * @GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @Column(name="siteRoot", type="string", length=1024, nullable=false)
     */
    private $siteroot;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set siteroot
     *
     * @param string $siteroot
     * @return Sites
     */
    public function setSiteroot($siteroot)
    {
        $this->siteroot = $siteroot;

        return $this;
    }

    /**
     * Get siteroot
     *
     * @return string
     */
    public function getSiteroot()
    {
        return $this->siteroot;
    }
}
