<?php

namespace Gustavus\Concert\Setup\GeneratedEntities;

/**
 * Permissions
 *
 * @Table(name="permissions", indexes={@Index(name="userSites_idx", columns={"site_id"})})
 * @Entity
 */
class Permissions
{
    /**
     * @var string
     *
     * @Column(name="username", type="string", length=32, nullable=false)
     * @Id
     * @GeneratedValue(strategy="NONE")
     */
    private $username;

    /**
     * @var string
     *
     * @Column(name="accessLevel", type="string", length=24, nullable=false)
     */
    private $accesslevel;

    /**
     * @var string
     *
     * @Column(name="includedFiles", type="string", length=512, nullable=true)
     */
    private $includedfiles;

    /**
     * @var string
     *
     * @Column(name="excludedFiles", type="string", length=512, nullable=true)
     */
    private $excludedfiles;

    /**
     * @var \Sites
     *
     * @Id
     * @GeneratedValue(strategy="NONE")
     * @OneToOne(targetEntity="Sites")
     * @JoinColumns({
     *   @JoinColumn(name="site_id", referencedColumnName="id")
     * })
     */
    private $site;


    /**
     * Set username
     *
     * @param string $username
     * @return Permissions
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Get username
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set accesslevel
     *
     * @param string $accesslevel
     * @return Permissions
     */
    public function setAccesslevel($accesslevel)
    {
        $this->accesslevel = $accesslevel;

        return $this;
    }

    /**
     * Get accesslevel
     *
     * @return string
     */
    public function getAccesslevel()
    {
        return $this->accesslevel;
    }

    /**
     * Set includedfiles
     *
     * @param string $includedfiles
     * @return Permissions
     */
    public function setIncludedfiles($includedfiles)
    {
        $this->includedfiles = $includedfiles;

        return $this;
    }

    /**
     * Get includedfiles
     *
     * @return string
     */
    public function getIncludedfiles()
    {
        return $this->includedfiles;
    }

    /**
     * Set excludedfiles
     *
     * @param string $excludedfiles
     * @return Permissions
     */
    public function setExcludedfiles($excludedfiles)
    {
        $this->excludedfiles = $excludedfiles;

        return $this;
    }

    /**
     * Get excludedfiles
     *
     * @return string
     */
    public function getExcludedfiles()
    {
        return $this->excludedfiles;
    }

    /**
     * Set site
     *
     * @param \Sites $site
     * @return Permissions
     */
    public function setSite(\Sites $site)
    {
        $this->site = $site;

        return $this;
    }

    /**
     * Get site
     *
     * @return \Sites
     */
    public function getSite()
    {
        return $this->site;
    }
}
