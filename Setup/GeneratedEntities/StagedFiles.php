<?php

namespace Gustavus\Concert\Setup\GeneratedEntities;

/**
 * Pendingupdates
 *
 * @Table(name="stagedFiles", indexes={@Index(name="srcFilename", columns={"srcFilename"})})
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
     * @var boolean
     *
     * @Column(name="movedDate", type="datetime", nullable=true)
     */
    private $movedDate = '0';


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
     * @return Pendingupdates
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
     * Set srcfilepath
     *
     * @param string $srcfilepath
     * @return Pendingupdates
     */
    public function setSrcfilepath($srcfilepath)
    {
        $this->srcfilepath = $srcfilepath;

        return $this;
    }

    /**
     * Get srcfilepath
     *
     * @return string
     */
    public function getSrcfilepath()
    {
        return $this->srcfilepath;
    }

    /**
     * Set username
     *
     * @param string $username
     * @return Pendingupdates
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
     * Set groupname
     *
     * @param string $groupname
     * @return Pendingupdates
     */
    public function setGroupname($groupname)
    {
        $this->groupname = $groupname;

        return $this;
    }

    /**
     * Get groupname
     *
     * @return string
     */
    public function getGroupname()
    {
        return $this->groupname;
    }

    /**
     * Set date
     *
     * @param \DateTime $date
     * @return Pendingupdates
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
     * Set moved
     *
     * @param boolean $moved
     * @return Pendingupdates
     */
    public function setMoved($moved)
    {
        $this->moved = $moved;

        return $this;
    }

    /**
     * Get moved
     *
     * @return boolean
     */
    public function getMoved()
    {
        return $this->moved;
    }
}
