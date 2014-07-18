<?php

namespace Gustavus\Concert\Setup\GeneratedEntities;

use Doctrine\ORM\Mapping as ORM;

/**
 * Stagedfiles
 *
 * @Table(name="stagedFiles", indexes={@Index(name="srcFilepath", columns={"srcFilename"})})
 * @Entity
 */
class StagedFiles
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
     * @Column(name="destFilepath", type="string", length=2048, nullable=false)
     */
    private $destfilepath;

    /**
     * @var string
     *
     * @Column(name="srcFilename", type="string", length=40, nullable=false)
     */
    private $srcfilename;

    /**
     * @var string
     *
     * @Column(name="username", type="string", length=32, nullable=false)
     */
    private $username;

    /**
     * @var \DateTime
     *
     * @Column(name="date", type="datetime", nullable=false)
     */
    private $date;

    /**
     * @var \DateTime
     *
     * @Column(name="publishedDate", type="datetime", nullable=true)
     */
    private $publisheddate;


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
     * Set destfilepath
     *
     * @param string $destfilepath
     * @return Stagedfiles
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
     * @return Stagedfiles
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
     * Set username
     *
     * @param string $username
     * @return Stagedfiles
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
     * Set date
     *
     * @param \DateTime $date
     * @return Stagedfiles
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
     * @return Stagedfiles
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
