<?php

namespace Gustavus\Concert\Setup\GeneratedEntities;

use Doctrine\ORM\Mapping as ORM;

/**
 * Drafts
 *
 * @Table(name="drafts")
 * @Entity
 */
class Drafts
{
    /**
     * @var string
     *
     * @Column(name="draftFilename", type="string", length=40, nullable=false)
     * @Id
     * @GeneratedValue(strategy="NONE")
     */
    private $draftFilename;

    /**
     * @var string
     *
     * @Column(name="username", type="string", length=32, nullable=false)
     */
    private $username;

    /**
     * @var string
     *
     * @Column(name="destFilepath", type="string", length=2048, nullable=false)
     */
    private $destfilepath;

    /**
     * @var string
     *
     * @Column(name="draftName", type="string", length=40, nullable=false)
     */
    private $draftName;

    /**
     * @var string
     *
     * @Column(name="type", type="string", length=32, nullable=false)
     */
    private $type;

    /**
     * @var \DateTime
     *
     * @Column(name="date", type="datetime", nullable=false)
     */
    private $date;

    /**
     * @var string
     *
     * @Column(name="additionalUsers", type="string", length=256, nullable=true)
     */
    private $additionalUsers;

    /**
     * @var \DateTime
     *
     * @Column(name="publishedDate", type="datetime", nullable=true)
     */
    private $publisheddate;


    /**
     * Set username
     *
     * @param string $username
     * @return Drafts
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
     * Set destfilepath
     *
     * @param string $destfilepath
     * @return Drafts
     */
    public function setDestfilepath($destfilepath)
    {
        $this->destfilepath = $destfilepath;

        return $this;
    }

    /**
     * Get destfilepath
     *
     * @return string
     */
    public function getDestfilepath()
    {
        return $this->destfilepath;
    }

    /**
     * Set srcfilename
     *
     * @param string $srcfilename
     * @return Drafts
     */
    public function setSrcfilename($srcfilename)
    {
        $this->srcfilename = $srcfilename;

        return $this;
    }

    /**
     * Get srcfilename
     *
     * @return string
     */
    public function getSrcfilename()
    {
        return $this->srcfilename;
    }

    /**
     * Set type
     *
     * @param string $type
     * @return Drafts
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set date
     *
     * @param \DateTime $date
     * @return Drafts
     */
    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Get date
     *
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Set publisheddate
     *
     * @param \DateTime $publisheddate
     * @return Drafts
     */
    public function setPublisheddate($publisheddate)
    {
        $this->publisheddate = $publisheddate;

        return $this;
    }

    /**
     * Get publisheddate
     *
     * @return \DateTime
     */
    public function getPublisheddate()
    {
        return $this->publisheddate;
    }
}
